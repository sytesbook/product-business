package main

import (
	"context"
	"encoding/json"
	"log/slog"
	"net/http"
	"net/http/httputil"
	"net/url"
	"strings"
	"time"
)

// contextKey is an unexported type for context keys in this package,
// preventing collisions with keys from other packages.
type contextKey string

// targetPathKey is the context key used to pass the rewritten proxy path
// from the request handler to the ReverseProxy Director.
const targetPathKey contextKey = "targetPath"

// newHandler builds the main HTTP handler for the router service.
// It handles health checks, domain-level redirects, page path routing,
// and reverse proxying to wp-customer-sites-nginx.
func newHandler(table *RoutingTable, reconciler *Reconciler, wpTarget *url.URL, wpCustomerSitesHost string, ttl time.Duration) http.Handler {
	// The ReverseProxy forwards requests to wp-customer-sites-nginx.
	// The Director is responsible for rewriting the outgoing request URL.
	// The target path (/{site-uid}/{page-uid}/) is passed via request context
	// to avoid mutating the original incoming request.
	proxy := &httputil.ReverseProxy{
		Director: func(req *http.Request) {
			req.URL.Scheme = wpTarget.Scheme
			req.URL.Host = wpTarget.Host
			if path, ok := req.Context().Value(targetPathKey).(string); ok {
				req.URL.Path = path
				req.URL.RawPath = ""
			}
			// X-Forwarded-Host preserves the original customer domain so ModifyResponse
			// can reconstruct the correct Location URL on WordPress redirects.
			req.Header.Set("X-Forwarded-Host", req.Host)
			// Set the Host header to the WordPress WP_HOME hostname so that
			// redirect_canonical() sees a host that matches WP_HOME and does not
			// issue a redirect. The actual TCP connection still goes to wpTarget.Host
			// (wp-customer-sites-nginx) via req.URL.Host — the two are independent.
			req.Host = wpCustomerSitesHost
		},
		// ModifyResponse rewrites any 3xx Location header that WordPress emits using the
		// internal wp-customer-sites-nginx hostname back to the original customer domain
		// and customer path, so the browser never sees the internal service name.
		// WordPress should not redirect for trailing slashes (the Director always appends one),
		// but this handles any other redirect WordPress may issue.
		ModifyResponse: func(resp *http.Response) error {
			if resp.StatusCode < 300 || resp.StatusCode >= 400 {
				return nil
			}
			loc := resp.Header.Get("Location")
			if loc == "" {
				return nil
			}
			locURL, err := url.Parse(loc)
			if err != nil || locURL.Host == "" {
				return nil
			}

			// Recover the original customer domain that Director stored.
			customerHost := resp.Request.Header.Get("X-Forwarded-Host")

			// If the redirect already targets the customer domain, nothing to do.
			// This handles any WordPress hostname (internal service name or public
			// WP_HOME domain) without needing to hard-code it here.
			if customerHost == "" || locURL.Host == customerHost {
				return nil
			}

			// Reverse-map /{site-uid}/{page-uid}[/] → customer path.
			entry := resolveEntry(table, reconciler, customerHost, ttl)
			if entry == nil || entry.Site == nil {
				return nil
			}

			// Strip the /{site-uid} prefix; remainder is /{page-uid} or /{page-uid}/.
			wpPath := strings.TrimPrefix(locURL.Path, "/"+entry.Site.SiteUID)
			pageUID := strings.Trim(wpPath, "/")

			customerPath := ""
			for path, uid := range entry.Site.Pages {
				if uid == pageUID {
					customerPath = path
					break
				}
			}
			if customerPath == "" {
				return nil
			}

			// Honour the scheme Traefik forwards (http locally, https in production).
			scheme := resp.Request.Header.Get("X-Forwarded-Proto")
			if scheme == "" {
				scheme = "http"
			}
			resp.Header.Set("Location", (&url.URL{
				Scheme: scheme,
				Host:   customerHost,
				Path:   customerPath,
			}).String())
			return nil
		},
	}

	mux := http.NewServeMux()

	// Health check — returns the same JSON shape used by content-service.
	mux.HandleFunc("GET /health", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]any{
			"status": map[string]int{"code": 200},
		})
	})

	// All other requests go through domain lookup and proxying.
	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		host := stripPort(r.Host)

		entry := resolveEntry(table, reconciler, host, ttl)
		if entry == nil {
			slog.Info("domain not found", "host", host)
			http.Error(w, "domain not found", http.StatusNotFound)
			return
		}

		// Alias domain: issue a permanent redirect to the primary domain,
		// normalising any trailing slash in one round-trip.
		if entry.Redirect != "" {
			scheme := r.Header.Get("X-Forwarded-Proto")
			if scheme == "" {
				scheme = "http"
			}
			path := r.URL.Path
			if path != "/" && strings.HasSuffix(path, "/") {
				path = strings.TrimSuffix(path, "/")
			}
			target := scheme + "://" + entry.Redirect + path
			if r.URL.RawQuery != "" {
				target += "?" + r.URL.RawQuery
			}
			http.Redirect(w, r, target, http.StatusMovedPermanently)
			return
		}

		// Redirect trailing-slash paths to their canonical no-slash form.
		if r.URL.Path != "/" && strings.HasSuffix(r.URL.Path, "/") {
			canonical := strings.TrimSuffix(r.URL.Path, "/")
			if r.URL.RawQuery != "" {
				canonical += "?" + r.URL.RawQuery
			}
			http.Redirect(w, r, canonical, http.StatusMovedPermanently)
			return
		}

		// Primary domain: look up the page UID for the requested path.
		pageUID, ok := entry.Site.Pages[r.URL.Path]
		if !ok {
			slog.Info("page not found", "host", host, "path", r.URL.Path)
			http.Error(w, "page not found", http.StatusNotFound)
			return
		}

		// Rewrite to /{site-uid}/{page-uid}/ and proxy to wp-customer-sites-nginx.
		// The trailing slash is required: without it WordPress issues a redirect to add
		// it, which would expose the internal wp-customer-sites-nginx hostname.
		targetPath := "/" + entry.Site.SiteUID + "/" + pageUID + "/"
		slog.Debug("proxying request", "host", host, "path", r.URL.Path, "target", targetPath)

		ctx := context.WithValue(r.Context(), targetPathKey, targetPath)
		proxy.ServeHTTP(w, r.WithContext(ctx))
	})

	return mux
}

// resolveEntry returns the route entry for a domain. If the entry is absent from the
// table or its TTL has expired, it fetches the domain from content-service on demand
// and updates the table before returning.
func resolveEntry(table *RoutingTable, reconciler *Reconciler, host string, ttl time.Duration) *RouteEntry {
	if entry, ok := table.Get(host); ok && time.Since(entry.LoadedAt) < ttl {
		return entry
	}

	// Cache miss or expired TTL: fetch this specific domain from content-service.
	entry := reconciler.FetchDomain(host)
	if entry == nil {
		return nil
	}

	table.Set(host, entry)
	return entry
}

// stripPort removes the port component from a host string (e.g. "example.com:8080" → "example.com").
func stripPort(host string) string {
	if i := strings.LastIndex(host, ":"); i != -1 {
		return host[:i]
	}
	return host
}

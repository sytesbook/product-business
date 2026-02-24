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
func newHandler(table *RoutingTable, reconciler *Reconciler, wpTarget *url.URL, ttl time.Duration) http.Handler {
	// The ReverseProxy forwards requests to wp-customer-sites-nginx.
	// The Director is responsible for rewriting the outgoing request URL.
	// The target path (/{site-uid}/{page-uid}) is passed via request context
	// to avoid mutating the original incoming request.
	proxy := &httputil.ReverseProxy{
		Director: func(req *http.Request) {
			req.URL.Scheme = wpTarget.Scheme
			req.URL.Host = wpTarget.Host
			if path, ok := req.Context().Value(targetPathKey).(string); ok {
				req.URL.Path = path
				req.URL.RawPath = ""
			}
			// X-Forwarded-Host preserves the original customer domain for any downstream use.
			req.Header.Set("X-Forwarded-Host", req.Host)
			// Clear req.Host so the outgoing request uses req.URL.Host (wp-customer-sites-nginx)
			// as the Host header. WordPress only needs the rewritten path, not the customer domain.
			req.Host = ""
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

		// Alias domain: issue a permanent redirect to the primary domain.
		if entry.Redirect != "" {
			target := "https://" + entry.Redirect + r.RequestURI
			http.Redirect(w, r, target, http.StatusMovedPermanently)
			return
		}

		// Primary domain: look up the page UID for the requested path.
		pageUID, ok := entry.Site.Pages[r.URL.Path]
		if !ok {
			slog.Info("page not found", "host", host, "path", r.URL.Path)
			http.Error(w, "page not found", http.StatusNotFound)
			return
		}

		// Rewrite to /{site-uid}/{page-uid} and proxy to wp-customer-sites-nginx.
		targetPath := "/" + entry.Site.SiteUID + "/" + pageUID
		slog.Debug("proxying request", "host", host, "path", r.URL.Path, "target_path", targetPath)

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

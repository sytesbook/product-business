package main

import (
	"encoding/json"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"net/url"
	"time"
)

// Reconciler fetches routing data from the content-service and populates the routing table.
type Reconciler struct {
	contentServiceURL string
	httpClient        *http.Client
}

// NewReconciler creates a Reconciler that fetches from the given content-service base URL.
func NewReconciler(contentServiceURL string) *Reconciler {
	return &Reconciler{
		contentServiceURL: contentServiceURL,
		httpClient:        &http.Client{Timeout: 10 * time.Second},
	}
}

// rawSiteEntry is the JSON shape for a primary domain entry returned by content-service.
type rawSiteEntry struct {
	Site  string            `json:"site"`
	Pages map[string]string `json:"pages"`
}

// rawRedirectEntry is the JSON shape for an alias domain entry returned by content-service.
type rawRedirectEntry struct {
	Redirect string `json:"redirect"`
}

// ReconcileAll fetches all routing tables from content-service and replaces the in-memory
// table atomically. Used for startup pre-warming.
func (r *Reconciler) ReconcileAll(table *RoutingTable) error {
	endpoint := r.contentServiceURL + "/api/v1/routing-tables"
	entries, err := r.fetch(endpoint)
	if err != nil {
		return err
	}
	table.ReplaceAll(entries)
	slog.Info("routing table pre-warmed", "domains", len(entries))
	return nil
}

// FetchDomain fetches the routing entry for a single domain from content-service.
// Returns nil if the domain is not found or an error occurs.
func (r *Reconciler) FetchDomain(domain string) *RouteEntry {
	endpoint := r.contentServiceURL + "/api/v1/routing-tables?domains[]=" + url.QueryEscape(domain)
	entries, err := r.fetch(endpoint)
	if err != nil {
		slog.Error("failed to fetch domain routing entry", "domain", domain, "error", err)
		return nil
	}
	entry, ok := entries[domain]
	if !ok {
		return nil
	}
	return entry
}

// fetch performs the HTTP GET request and parses the routing table JSON response.
// The response is a map of domain -> raw JSON value (either a site entry or a redirect entry).
func (r *Reconciler) fetch(endpoint string) (map[string]*RouteEntry, error) {
	resp, err := r.httpClient.Get(endpoint)
	if err != nil {
		return nil, fmt.Errorf("GET %s: %w", endpoint, err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("GET %s: unexpected status %d", endpoint, resp.StatusCode)
	}

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("read response body: %w", err)
	}

	// PHP encodes an empty associative array as [] rather than {}, so we must handle
	// both. A non-empty response is always a JSON object keyed by domain name.
	if len(body) > 0 && body[0] == '[' {
		return make(map[string]*RouteEntry), nil
	}

	var raw map[string]json.RawMessage
	if err := json.Unmarshal(body, &raw); err != nil {
		return nil, fmt.Errorf("parse response JSON: %w", err)
	}

	now := time.Now()
	entries := make(map[string]*RouteEntry, len(raw))

	for domain, rawEntry := range raw {
		// Try redirect entry first — it carries a "redirect" key with a non-empty value.
		var redirect rawRedirectEntry
		if err := json.Unmarshal(rawEntry, &redirect); err == nil && redirect.Redirect != "" {
			entries[domain] = &RouteEntry{Redirect: redirect.Redirect, LoadedAt: now}
			continue
		}

		// Try site entry — it carries a "site" key with a non-empty value.
		var site rawSiteEntry
		if err := json.Unmarshal(rawEntry, &site); err == nil && site.Site != "" {
			entries[domain] = &RouteEntry{
				Site: &SiteRoute{
					SiteUID: site.Site,
					Pages:   site.Pages,
				},
				LoadedAt: now,
			}
			continue
		}

		slog.Warn("unrecognized routing entry format, skipping", "domain", domain)
	}

	return entries, nil
}

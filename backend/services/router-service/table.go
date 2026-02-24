package main

import (
	"sync"
	"time"
)

// SiteRoute holds routing information for a primary domain.
type SiteRoute struct {
	SiteUID string
	Pages   map[string]string // page path -> page UID
}

// RouteEntry represents the routing configuration for a single domain.
// Either Site or Redirect will be set, not both.
type RouteEntry struct {
	Site     *SiteRoute // set for primary domains
	Redirect string     // set for alias domains (value = primary domain name)
	LoadedAt time.Time  // timestamp used for per-domain TTL checks
}

// RoutingTable is a thread-safe in-memory store mapping domain names to route entries.
// Multiple goroutines can read concurrently; writes get exclusive access.
type RoutingTable struct {
	mu      sync.RWMutex
	entries map[string]*RouteEntry
}

// NewRoutingTable creates an empty routing table.
func NewRoutingTable() *RoutingTable {
	return &RoutingTable{
		entries: make(map[string]*RouteEntry),
	}
}

// Get retrieves the route entry for a domain. Returns false if not found.
func (t *RoutingTable) Get(domain string) (*RouteEntry, bool) {
	t.mu.RLock()
	defer t.mu.RUnlock()
	entry, ok := t.entries[domain]
	return entry, ok
}

// Set upserts a single domain entry.
func (t *RoutingTable) Set(domain string, entry *RouteEntry) {
	t.mu.Lock()
	defer t.mu.Unlock()
	t.entries[domain] = entry
}

// ReplaceAll atomically swaps the entire routing table with a new set of entries.
func (t *RoutingTable) ReplaceAll(entries map[string]*RouteEntry) {
	t.mu.Lock()
	defer t.mu.Unlock()
	t.entries = entries
}

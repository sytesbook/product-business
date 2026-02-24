package main

import (
	"log/slog"
	"net/http"
	"net/url"
	"os"
	"time"
)

func main() {
	contentServiceURL := getEnv("CONTENT_SERVICE_URL", "http://content-service-nginx")
	wpCustomerSitesURL := getEnv("WP_CUSTOMER_SITES_URL", "http://wp-customer-sites-nginx")
	port := getEnv("PORT", "80")
	domainTTLStr := getEnv("DOMAIN_TTL", "5m")

	domainTTL, err := time.ParseDuration(domainTTLStr)
	if err != nil {
		slog.Error("invalid DOMAIN_TTL value", "value", domainTTLStr, "error", err)
		os.Exit(1)
	}

	wpTarget, err := url.Parse(wpCustomerSitesURL)
	if err != nil {
		slog.Error("invalid WP_CUSTOMER_SITES_URL value", "error", err)
		os.Exit(1)
	}

	table := NewRoutingTable()
	reconciler := NewReconciler(contentServiceURL)

	// Pre-warm the routing table asynchronously. The service starts immediately and
	// handles requests right away; domains not yet in the table are fetched on demand.
	go func() {
		if err := reconciler.ReconcileAll(table); err != nil {
			slog.Warn("startup pre-warm failed; domains will be fetched on demand", "error", err)
		}
	}()

	handler := newHandler(table, reconciler, wpTarget, domainTTL)

	addr := ":" + port
	slog.Info("router-service starting", "addr", addr, "domain_ttl", domainTTL)

	if err := http.ListenAndServe(addr, handler); err != nil {
		slog.Error("server stopped", "error", err)
		os.Exit(1)
	}
}

// getEnv returns the value of the named environment variable, or fallback if it is unset or empty.
func getEnv(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

<?php
/**
 * Plugin Name: Sytesbook Site Taxonomy
 * Description: Registers the 'site' taxonomy for pages with custom /{site-uid}/{page-uid}/ routing and admin UI enhancements.
 * Version: 1.0.0
 * Author: Sytesbook
 */

declare(strict_types=1);

if (!function_exists('add_action')) {
    return;
}

// ============================================================================
// Taxonomy Registration
// ============================================================================

add_action('init', function (): void {
    register_taxonomy('site', 'page', [
        'labels' => [
            'name'              => 'Sites',
            'singular_name'     => 'Site',
            'search_items'      => 'Search Sites',
            'all_items'         => 'All Sites',
            'edit_item'         => 'Edit Site',
            'update_item'       => 'Update Site',
            'add_new_item'      => 'Add New Site',
            'new_item_name'     => 'New Site Name',
            'menu_name'         => 'Sites',
        ],
        'hierarchical'      => false,
        'public'            => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => false, // managed manually for sortability
        'rewrite'           => ['slug' => 'site', 'with_front' => false],
        'query_var'         => true,
    ]);
}, 5);

// ============================================================================
// Custom Rewrite Rules for /{site-uid}/{page-uid}/ URLs
// ============================================================================

add_action('init', function (): void {
    add_rewrite_tag('%site_uid%', '([^/]+)');
    add_rewrite_rule(
        '^([^/]+)/([^/]+)/?$',
        'index.php?pagename=$matches[2]&site_uid=$matches[1]',
        'top'
    );
}, 10);

add_filter('query_vars', function (array $vars): array {
    $vars[] = 'site_uid';
    return $vars;
});

// ============================================================================
// Page Link Filter — generate /{site-uid}/{page-uid}/ URLs
// ============================================================================

add_filter('page_link', function (string $link, int $postId): string {
    $terms = get_the_terms($postId, 'site');

    if (empty($terms) || is_wp_error($terms)) {
        return $link;
    }

    $post = get_post($postId);
    if (!$post instanceof WP_Post) {
        return $link;
    }

    $term = reset($terms);

    return home_url('/' . $term->slug . '/' . $post->post_name . '/');
}, 10, 2);

// ============================================================================
// Frontend Routing — constrain query to the correct site term
// ============================================================================

add_action('pre_get_posts', function (WP_Query $query): void {
    if (is_admin()) {
        return;
    }

    $siteUid = $query->get('site_uid');
    if (empty($siteUid)) {
        return;
    }

    $query->set('post_type', 'page');
    $query->set('tax_query', [
        [
            'taxonomy' => 'site',
            'field'    => 'slug',
            'terms'    => $siteUid,
        ],
    ]);
});

// ============================================================================
// Admin: Sortable "Site" Column on Pages List
// ============================================================================

add_filter('manage_pages_columns', function (array $columns): array {
    $reordered = [];
    foreach ($columns as $key => $label) {
        $reordered[$key] = $label;
        if ($key === 'title') {
            $reordered['site_taxonomy'] = 'Site';
        }
    }
    return $reordered;
});

add_action('manage_pages_custom_column', function (string $column, int $postId): void {
    if ($column !== 'site_taxonomy') {
        return;
    }

    $terms = get_the_terms($postId, 'site');

    if (empty($terms) || is_wp_error($terms)) {
        echo '&mdash;';
        return;
    }

    $names = array_map(fn(WP_Term $term): string => esc_html($term->name), $terms);
    echo implode(', ', $names);
}, 10, 2);

add_filter('manage_edit-page_sortable_columns', function (array $columns): array {
    $columns['site_taxonomy'] = 'site_taxonomy';
    return $columns;
});

add_action('pre_get_posts', function (WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->get('orderby') !== 'site_taxonomy') {
        return;
    }

    $query->set('orderby', 'meta_value');
    $query->set('tax_query', [
        'relation' => 'OR',
        [
            'taxonomy' => 'site',
            'operator' => 'EXISTS',
        ],
        [
            'taxonomy' => 'site',
            'operator' => 'NOT EXISTS',
        ],
    ]);

    // Sort by term name via a meta-like approach using a join
    add_filter('posts_clauses', function (array $clauses, WP_Query $q) use ($query): array {
        if ($q !== $query) {
            return $clauses;
        }

        global $wpdb;

        $order = strtoupper($q->get('order')) === 'DESC' ? 'DESC' : 'ASC';

        $clauses['join'] .= "
            LEFT JOIN {$wpdb->term_relationships} AS str ON ({$wpdb->posts}.ID = str.object_id)
            LEFT JOIN {$wpdb->term_taxonomy} AS stt ON (str.term_taxonomy_id = stt.term_taxonomy_id AND stt.taxonomy = 'site')
            LEFT JOIN {$wpdb->terms} AS st ON (stt.term_id = st.term_id)
        ";
        $clauses['orderby'] = "st.name {$order}";
        $clauses['groupby'] = "{$wpdb->posts}.ID";

        return $clauses;
    }, 10, 2);
});

// ============================================================================
// Admin: Text Filter for Site Slug on Pages List
// ============================================================================

add_action('restrict_manage_posts', function (string $postType): void {
    if ($postType !== 'page') {
        return;
    }

    $value = isset($_GET['site_slug_filter']) ? sanitize_text_field(wp_unslash($_GET['site_slug_filter'])) : '';

    printf(
        '<input type="text" name="site_slug_filter" value="%s" placeholder="%s" style="margin-left:4px;" />',
        esc_attr($value),
        esc_attr('Filter by site slug\u2026')
    );
});

add_action('parse_query', function (WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $siteSlug = isset($_GET['site_slug_filter']) ? sanitize_text_field(wp_unslash($_GET['site_slug_filter'])) : '';

    if (empty($siteSlug)) {
        return;
    }

    $query->set('tax_query', [
        [
            'taxonomy' => 'site',
            'field'    => 'slug',
            'terms'    => $siteSlug,
        ],
    ]);
});

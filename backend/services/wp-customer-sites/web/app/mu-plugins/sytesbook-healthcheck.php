<?php
/**
 * Plugin Name: Sytesbook Healthcheck
 * Description: Provides healthcheck endpoint at /wp-json/api/v1/health
 * Version: 1.0.0
 * Author: Sytesbook
 */

declare(strict_types=1);

if (!function_exists('add_action')) {
    return;
}

add_action('rest_api_init', function (): void {
    register_rest_route('api/v1', '/health', [
        'methods' => 'GET',
        'callback' => function () {
            return new WP_REST_Response(['status' => ['code' => 200]], 200);
        },
        'permission_callback' => '__return_true',
    ]);
});

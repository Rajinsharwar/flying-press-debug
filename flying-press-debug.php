<?php

/**
 * Plugin Name: (DEBUG) FlyingPress
 * Plugin URI: https://profiles.wordpress.org/rajinsharwar
 * Description: Helper to DEBUG FlyingPress.
 * Version: 1.0.1
 * Requires PHP: 7.4
 * Requires at least: 4.7
 * Author: Rajin Sharwar
 * Author URI: https://www.linkedin.com/in/rajinsharwar
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FLP_DEBUG_PLUGIN_FILE', __FILE__ );
define( 'FLP_DEBUG_PLUGIN_DIR', plugin_dir_path( FLP_DEBUG_PLUGIN_FILE ) );
define( 'FLP_DEBUG_PLUGIN_DIR_URL', plugin_dir_url( FLP_DEBUG_PLUGIN_FILE ) );

require_once FLP_DEBUG_PLUGIN_DIR . 'functions.php';
add_action('admin_menu', 'flp_debug_custom_admin_menu');

function flp_debug_custom_admin_menu() {
    add_menu_page(
        'FlyingPress DEBUG',
        'FlyingPress DEBUG',
        'manage_options',
        'flp-debug-menu',
        'flp_debug_custom_menu_page_html',
        'dashicons-admin-generic',
        25
    );
}

function flp_debug_custom_menu_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    require FLP_DEBUG_PLUGIN_DIR . 'templates/admin-page.php';
}

add_action('wp_ajax_flp_debug_run_step', 'flp_debug_run_step');

function flp_debug_run_step() {
    check_ajax_referer('flp_debug_nonce');
    $step = sanitize_text_field($_POST['step'] ?? '');
    $url = sanitize_url( $_POST['url'] ?? home_url() );

    switch ($step) {
        case 'clear_home_url':
            $response = flp_debug_clear_home_url( $url );

            if ( true === $response[ 0 ] ) {
                wp_send_json_success([
                    'message' => $response[ 1 ],
                    'log'     => trim( $response[ 2 ] ) ?? false
                ]);
            } else {
                wp_send_json_error([
                    'message' => $response[ 1 ],
                    'log'     => trim( $response[ 2 ] ) ?? false
                ]);
            }
            break;

        case 'queue_home_url':
            $response = flp_debug_queue_home_url( $url );

            if ( true === $response[ 0 ] ) {
                wp_send_json_success([
                    'message' => $response[ 1 ],
                    'log'     => trim( $response[ 2 ] ) ?? false
                ]);
            } else {
                wp_send_json_error([
                    'message' => $response[ 1 ],
                    'log'     => trim( $response[ 2 ] ) ?? false
                ]);
            }
            break;

        case 'start_queue_home_url':
            $response = flp_debug_start_queue_home_url( $url );

            if ( true === $response[ 0 ] ) {
                wp_send_json_success([
                    'message' => $response[ 1 ],
                    'log'     => trim( $response[ 2 ] ) ?? false
                ]);
            } else {
                wp_send_json_error([
                    'message' => $response[ 1 ],
                    'log'     => trim( $response[ 2 ] ) ?? false
                ]);
            }
            break;
        
        case 'process_home_url':
            $response = flp_debug_process_home_url( $url );

            if ( true === $response[ 0 ] ) {
                wp_send_json_success([
                    'message' => $response[ 1 ],
                    'log'     => trim( $response[ 2 ] ) ?? false
                ]);
            } else {
                wp_send_json_error([
                    'message' => $response[ 1 ],
                    'log'     => trim( $response[ 2 ] ) ?? false
                ]);
            }
            break;
        case 'verify_cache_home_url':
            $response = flp_debug_verify_cache_home_url( $url );

            if ( true === $response[ 0 ] ) {
                wp_send_json_success([
                    'message' => $response[ 1 ],
                    'log'     => trim( $response[ 2 ] ) ?? false
                ]);
            } else {
                wp_send_json_error([
                    'message' => $response[ 1 ],
                    'log'     => trim( $response[ 2 ] ) ?? false
                ]);
            }
            break;

        default:
            wp_send_json_error([
                'message' => "Unknown step: $step",
                'log' => "No matching handler found for step: $step"
            ]);
    }
}


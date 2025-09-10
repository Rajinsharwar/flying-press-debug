<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Single URL Checkers.
 * 
 * Uses the Home URL to check if the cache can be created and be saved on the server.
 * Runs the checks steps by steps and logs each step to help in debugging.
 */

// Clear Home URL cache file
function flp_debug_clear_home_url( $url ) {
    // $url = home_url();
    $log = [];

    if (!defined('FLYING_PRESS_CACHE_DIR')) {
        $log[] = 'Constant FLYING_PRESS_CACHE_DIR is not defined.';
        return [false, 'FLYING_PRESS_CACHE_DIR is not defined.', implode("\n", $log)];
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        $log[] = 'Could not parse host from home_url: ' . $url;
        return [false, 'Could not parse host from URL.', implode("\n", $log)];
    }

    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    $path = urldecode($path);
    $page_cache_dir = rtrim(FLYING_PRESS_CACHE_DIR, '/') . '/' . trim($host, '/') . $path;
    $page_cache_dir = rtrim($page_cache_dir, '/') . '/';

    $log[] = "Checking cache directory: $page_cache_dir";

    if (!is_dir($page_cache_dir)) {
        $log[] = "Directory check failed: $page_cache_dir is not a directory.";
        mkdir($page_cache_dir, 0755, true);
    }

    if (!is_readable($page_cache_dir)) {
        $log[] = "Directory is not readable: $page_cache_dir";
        return [false, "Cache directory is not readable: $page_cache_dir", implode("\n", $log)];
    }

    $pages = glob($page_cache_dir . '*.html.gz');
    if ($pages === false) {
        $log[] = "glob() failed in $page_cache_dir";
        return [false, "Failed to list cache files.", implode("\n", $log)];
    }

    $file_count = count($pages);
    $log[] = "Found $file_count .html.gz file(s) to delete.";

    if ($file_count === 0) {
        $log[] = "No cache files were found. Nothing to delete.";
        return [true, "No cache files found in $page_cache_dir", implode("\n", $log)];
    }

    $deleted = 0;
    foreach ($pages as $file) {
        if (is_file($file) && file_exists($file)) {
            if (!unlink($file)) {
                $log[] = "Failed to delete file: $file";
                return [false, "Failed to delete one or more cache files.", implode("\n", $log)];
            }
            $log[] = "Deleted file: $file";
            $deleted++;
        } else {
            $log[] = "Skipped non-file or missing file: $file";
        }
    }

    return [true, "Successfully cleared $deleted file(s) from $page_cache_dir", implode("\n", $log)];
}

// Queue the Home URL in the wp-tasks.
function flp_debug_queue_home_url( $url ) {
    $urls = [ $url ];
    $url = $urls[ 0 ];
    $log = [];

    add_filter('pre_http_request', function ($preempt, $r, $url) {
        if (strpos($url, 'action=task_runner_preload-urls') !== false) {
            return new WP_Error( 'http_request_block', __( "This request is not allowed", "flp_debug" ) );
        }
        return $preempt;
    }, 10, 3);

    global $wpdb;
    $clear = $wpdb->get_row(
        $wpdb->prepare(
            "TRUNCATE TABLE %1s",
            $wpdb->prefix . 'tasks',
        ),
    ARRAY_A );

    if ( class_exists( '\FlyingPress\Preload' ) ) {
        \FlyingPress\Preload::preload_urls($urls, time());
    }

    $log[] = 'Last Query: ' . $wpdb->last_query;
    $log[] = '';
    $log[] = 'Last Query Error (if any): ' . $wpdb->last_error;

    $serialized_snippet = 's:3:"url";s:' . strlen( $urls[ 0 ] ) . ':"' . $urls[ 0 ] . '";';

    $check = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}tasks WHERE data LIKE '%1s' LIMIT 2",
            '%' . $serialized_snippet . '%',
        ),
    ARRAY_A );

    $clear = $wpdb->get_row(
        $wpdb->prepare(
            "TRUNCATE TABLE %1s",
            $wpdb->prefix . 'tasks',
        ),
    ARRAY_A );

    remove_all_filters('pre_http_request');
    
    if ( isset( $check[ 0 ] ) ) {
        return [true, "URL $url was added to the queue successfully for preload", implode("\n", $log)];
    } else {
        return [false, "URL $url were not able to be added to the queue for preload", implode("\n", $log)];
    }
}

// Start running the Queue by Hiting the endpoint.
function flp_debug_start_queue_home_url( $url ) {
    $log = [];

    global $wpdb;
    $clear = $wpdb->get_row(
        $wpdb->prepare(
            "TRUNCATE TABLE %1s",
            $wpdb->prefix . 'tasks',
        ),
    ARRAY_A );

    $url = admin_url( 'admin-ajax.php?action=task_runner_preload-urls' );

    usleep( 0.2 * 1_000_000 );

    $response = wp_remote_post($url, [
        'timeout' => 10,
        'blocking' => true,
        'cookies' => $_COOKIE,
        'sslverify' => false,
    ]);

    $log[] = 'POST call to: ' . $url;
    $log[] = 'POST response Code: ' . wp_remote_retrieve_response_code( $response );
    $log[] = 'Response POST: (might timeout because it\'s blocking call, ignore and check if cache is getting created) ' . print_r( $response, true );
    $log[] = '';
    $log[] = 'Response POST Body: ' . wp_remote_retrieve_body( $response );

    $response2 = wp_remote_get($url, [
        'timeout' => 10,
        'blocking' => true,
        'cookies' => $_COOKIE,
        'sslverify' => false,
    ]);

    $log[] = '';
    $log[] = '';
    $log[] = 'GET call to: ' . $url;
    $log[] = 'GET response Code: ' . wp_remote_retrieve_response_code( $response2 );
    $log[] = 'Response GET (just debug, will fail with cURL timeout if all PHP workers are busy): ' . print_r( $response2, true );
    $log[] = '';
    $log[] = 'Response GET Body: ' . wp_remote_retrieve_body( $response2 );

    if ( isset( $response ) && 'done' === wp_remote_retrieve_body( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
        return [true, "The Queue was able to Start sucessfully without issues", implode("\n", $log)];
    } else {
        return [false, "The Queue was NOT able to Start sucessfully. Issues Found, please check the log.", implode("\n", $log)];
    }
}

// Process the cache creation for the Home URL.
function flp_debug_process_home_url( $url ) {
    $log = [];
    // $url = home_url();

    $user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    $args = [
      'headers' => [
        'Range' => 'bytes=0-0',
        'x-flying-press-preload' => '1',
        'Cookie' => 'wordpress_logged_in_1=1;',
        'User-Agent' => $user_agent,
      ],
      'timeout' => 60,
      'sslverify' => false,
    ];

    // single, sequential request
    $response = wp_remote_get($url, $args);

    $log[] = 'GET call to: ' . $url;
    $log[] = 'GET response Code: ' . wp_remote_retrieve_response_code( $response );
    $log[] = 'Response GET: ' . print_r( $response, true );
    $log[] = '';
    $log[] = 'Response GET Body: ' . wp_remote_retrieve_body( $response );

    if ( isset( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
        return [true, "The Home URL was successfully processed.", implode("\n", $log)];
    } else {
        return [false, "The Home URL was not able to be processed. Issues Found, please check the log.", implode("\n", $log)];
    }
}

// Finally: Check if the cache is created.
function flp_debug_verify_cache_home_url( $url ) {
    $log = [];
    // $url = home_url();

    // File paths for cache files
    $host = parse_url( $url, PHP_URL_HOST );
    $parsed    = parse_url( $url, PHP_URL_PATH );
    $parsed    = urldecode( $parsed );
    $last_part = basename( rtrim( $parsed, '/' ) );
    $cache_file_path = WP_CONTENT_DIR . "/cache/flying-press/$host/$last_part/index.html.gz";

    // Check if the gzipped cache file exists
    $log[] = 'Searched for Cache file path: ' . $cache_file_path;
    if ( file_exists( $cache_file_path ) ) {
        return [true, "The Home URL was successfully cached.", implode("\n", $log)];
    } else {
        return [false, "The Home URL was not able to be cached. Cache file not saved.", implode("\n", $log)];
    }
}


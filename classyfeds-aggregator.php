<?php
/**
 * Plugin Name: Classyfeds Aggregator (MVP)
 * Description: Standalone aggregator page for federated classifieds.
 * Version: 0.1.0
 * Author: thomi@etik.com + amis
 */

namespace ClassyFeds;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Load shared post type definitions.
require_once __DIR__ . '/includes/post-types.php';

// Load aggregator modules.
require_once __DIR__ . '/includes/aggregator/post-types.php';
require_once __DIR__ . '/includes/aggregator/settings.php';
require_once __DIR__ . '/includes/aggregator/frontend.php';
require_once __DIR__ . '/includes/aggregator/rest.php';

/**
 * Ensure the Classifieds page exists on activation.
 */
function aggregator_activate() {
    $page_id = (int) get_option( 'classyfeds_page_id' );

    if ( $page_id && get_post( $page_id ) ) {
        // Page already exists.
    } else {
        $page    = get_page_by_path( 'classifieds' );
        $page_id = $page ? $page->ID : 0;

        if ( ! $page_id ) {
            $page_id = wp_insert_post(
                [
                    'post_title'  => __( 'Classifieds', 'classyfeds-aggregator' ),
                    'post_name'   => 'classifieds',
                    'post_status' => 'publish',
                    'post_type'   => 'page',
                ]
            );
        }

        if ( $page_id ) {
            update_option( 'classyfeds_page_id', $page_id );
        }
    }

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\aggregator_activate' );

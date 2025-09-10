<?php
/**
 * Plugin Name: Classyfeds Aggregator (MVP)
 * Description: Standalone aggregator page for federated classifieds.
 * Version: 0.1.3
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

        flush_rewrite_rules();
    }
}

/**
 * Register settings menu.
 */
function register_settings_menu() {
    add_menu_page(
        __( 'Classifieds Aggregator', 'classyfeds-aggregator' ),
        __( 'Classifieds', 'classyfeds-aggregator' ),
        'manage_options',
        'classyfeds-aggregator',
        __NAMESPACE__ . '\\settings_page',
        'dashicons-megaphone',
        25
    );
}

/**
 * Render settings page.
 */
function settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $message           = '';
    $current_page      = (int) get_option( 'classyfeds_page_id' );
    $remote_inbox      = get_option( 'classyfeds_remote_inbox', '' );
    $filter_posts      = (int) get_option( 'classyfeds_filter_posts', 0 );

    if ( isset( $_POST['classyfeds_save'] ) && check_admin_referer( 'classyfeds_save_settings', 'classyfeds_nonce' ) ) {
        $page_id = isset( $_POST['classyfeds_page_id'] ) ? absint( wp_unslash( $_POST['classyfeds_page_id'] ) ) : 0;
        $slug    = isset( $_POST['classyfeds_slug'] ) ? sanitize_title( wp_unslash( $_POST['classyfeds_slug'] ) ) : '';

        if ( $slug ) {
            $page = get_page_by_path( $slug );
            if ( $page ) {
                $page_id = $page->ID;
            } else {
                $page_id = wp_insert_post(
                    [
                        'post_title'  => ucwords( str_replace( '-', ' ', $slug ) ),
                        'post_name'   => $slug,
                        'post_status' => 'publish',
                        'post_type'   => 'page',
                    ]
                );
            }
        }

        $remote_inbox = isset( $_POST['classyfeds_remote_inbox'] ) ? esc_url_raw( wp_unslash( $_POST['classyfeds_remote_inbox'] ) ) : '';
        $filter_posts = isset( $_POST['classyfeds_filter_posts'] ) ? absint( wp_unslash( $_POST['classyfeds_filter_posts'] ) ) : 0;

        // Add new category if provided.
        if ( ! empty( $_POST['classyfeds_new_cat_name'] ) ) {
            $new_cat_name   = sanitize_text_field( wp_unslash( $_POST['classyfeds_new_cat_name'] ) );
            $new_cat_parent = isset( $_POST['classyfeds_new_cat_parent'] ) ? absint( $_POST['classyfeds_new_cat_parent'] ) : 0;
            wp_insert_term( $new_cat_name, 'listing_category', [ 'parent' => $new_cat_parent ] );
        }

        // Rename existing category if requested.
        if ( ! empty( $_POST['classyfeds_edit

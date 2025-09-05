<?php
/**
 * Plugin Name: Fed Classifieds (MVP)
 * Description: Custom post type "listing" with JSON-LD output and auto-expiration for a federated classifieds network.
 * Version: 0.1.0
 * Author: thomi@etik.com + amis
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register the "listing" custom post type.
 */
add_action( 'init', function() {
    register_post_type( 'listing', [
        'labels' => [
            'name'          => __( 'Listings', 'fed-classifieds' ),
            'singular_name' => __( 'Listing', 'fed-classifieds' ),
        ],
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor', 'thumbnail' ],
        'taxonomies'   => [ 'category', 'post_tag' ],
        'rewrite'      => [ 'slug' => 'listings' ],
    ] );
});

/**
 * Register the "external_listing" custom post type for remote listings.
 */
add_action( 'init', function() {
    register_post_type( 'external_listing', [
        'labels' => [
            'name'          => __( 'External Listings', 'fed-classifieds' ),
            'singular_name' => __( 'External Listing', 'fed-classifieds' ),
        ],
        'public'       => true,
        'has_archive'  => false,
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor', 'thumbnail' ],
        'rewrite'      => [ 'slug' => 'external-listings' ],
    ] );
});

/**
 * Add "Typ" dropdown to the listing editor.
 */
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'listing_type',
        __( 'Typ', 'fed-classifieds' ),
        function( $post ) {
            $value = get_post_meta( $post->ID, '_listing_type', true );
            wp_nonce_field( 'save_listing_type', 'listing_type_nonce' );
            echo '<select name="listing_type" id="listing_type" style="width:100%">';
            echo '<option value="Angebot"' . selected( $value, 'Angebot', false ) . '>' . esc_html__( 'Angebot', 'fed-classifieds' ) . '</option>';
            echo '<option value="Gesuch"' . selected( $value, 'Gesuch', false ) . '>' . esc_html__( 'Gesuch', 'fed-classifieds' ) . '</option>';
            echo '</select>';
        },
        'listing',
        'side'
    );
});

/**
 * Register a custom post type for storing incoming ActivityPub objects.
 *
 * These objects are used to represent remote listings that arrive through
 * the federated inbox endpoint. The posts are not public but can be queried
 * so they may appear alongside local listings on the listings page template.
 */
add_action( 'init', function() {
    register_post_type( 'ap_object', [
        'labels' => [
            'name'          => __( 'ActivityPub Objects', 'fed-classifieds' ),
            'singular_name' => __( 'ActivityPub Object', 'fed-classifieds' ),
        ],
        'public'       => false,
        'show_ui'      => false,
        'show_in_rest' => false,
        'supports'     => [ 'title', 'editor' ],
    ] );
});

/**
 * Register custom post status "expired".
 */
add_action( 'init', function() {
    register_post_status( 'expired', [
        'label'                     => _x( 'Expired', 'post', 'fed-classifieds' ),
        'public'                    => false,
        'internal'                  => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'fed-classifieds' ),
    ] );
});

/**
 * Add a meta box for external listing URLs and save the value.
 */
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'external_listing_url',
        __( 'External URL', 'fed-classifieds' ),
        function( $post ) {
            $url = get_post_meta( $post->ID,

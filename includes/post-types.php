<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register the "listing" custom post type and its taxonomy.
add_action( 'init', function() {
    register_post_type( 'listing', [
        'labels' => [
            'name'          => __( 'Listings', 'classyfeds' ),
            'singular_name' => __( 'Listing', 'classyfeds' ),
        ],
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor', 'thumbnail' ],
        'taxonomies'   => [ 'listing_category', 'post_tag' ],
        'rewrite'      => [ 'slug' => 'listings' ],
        'register_meta_box_cb' => 'classyfeds_register_listing_metaboxes',
    ] );

    register_taxonomy(
        'listing_category',
        'listing',
        [
            'labels'       => [
                'name'          => __( 'Listing Categories', 'classyfeds' ),
                'singular_name' => __( 'Listing Category', 'classyfeds' ),
            ],
            'public'       => true,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'capabilities' => [
                'manage_terms' => 'manage_listing_categories',
                'edit_terms'   => 'manage_listing_categories',
                'delete_terms' => 'manage_listing_categories',
                'assign_terms' => 'publish_listings',
            ],
        ]
    );
} );

// Register custom post type for incoming ActivityPub objects.
add_action( 'init', function() {
    register_post_type( 'ap_object', [
        'labels' => [
            'name'          => __( 'ActivityPub Objects', 'classyfeds' ),
            'singular_name' => __( 'ActivityPub Object', 'classyfeds' ),
        ],
        'public'       => false,
        'show_ui'      => false,
        'show_in_rest' => false,
        'supports'     => [ 'title', 'editor' ],
    ] );
} );

// Register custom post status "expired".
add_action( 'init', function() {
    register_post_status( 'expired', [
        'label'                     => _x( 'Expired', 'post', 'classyfeds' ),
        'public'                    => false,
        'internal'                  => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'classyfeds' ),
    ] );
} );


<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function() {
    register_post_type( 'ap_object', [
        'labels' => [
            'name'          => __( 'ActivityPub Objects', 'classyfeds-aggregator' ),
            'singular_name' => __( 'ActivityPub Object', 'classyfeds-aggregator' ),
        ],
        'public'       => false,
        'show_ui'      => false,
        'show_in_rest' => false,
        'supports'     => [ 'title', 'editor' ],
    ] );

    register_taxonomy(
        'listing_category',
        [ 'listing', 'ap_object' ],
        [
            'labels'       => [
                'name'          => __( 'Listing Categories', 'classyfeds-aggregator' ),
                'singular_name' => __( 'Listing Category', 'classyfeds-aggregator' ),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ]
    );
} );

/**
 * Store an ActivityPub object as an `ap_object` post.
 *
 * @param array $object ActivityPub object.
 * @return int|WP_Error Post ID on success or error.
 */
function classyfeds_aggregator_store_ap_object( array $object ) {
    $title = '';
    if ( isset( $object['name'] ) ) {
        $title = sanitize_text_field( $object['name'] );
    } elseif ( isset( $object['summary'] ) ) {
        $title = sanitize_text_field( $object['summary'] );
    } else {
        $title = __( 'Remote ActivityPub Object', 'classyfeds-aggregator' );
    }

    return wp_insert_post(
        [
            'post_type'   => 'ap_object',
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_content'=> wp_json_encode( $object ),
        ],
        true
    );
}

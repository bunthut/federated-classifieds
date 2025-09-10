<?php
namespace ClassyFeds;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register REST API routes.
 */
function register_rest_routes() {
    register_rest_route(
        'classyfeds/v1',
        '/inbox',
        [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => __NAMESPACE__ . '\\inbox_handler',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'classyfeds/v1',
        '/listings',
        [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => __NAMESPACE__ . '\\listings_handler',
            'permission_callback' => '__return_true',
        ]
    );
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_routes' );

/**
 * Handle incoming ActivityPub objects.
 */
function inbox_handler( \WP_REST_Request $request ) {
    $activity = $request->get_json_params();

    if ( empty( $activity ) || ! is_array( $activity ) ) {
        return new \WP_REST_Response( [ 'error' => 'Invalid object' ], 400 );
    }

    $object = $activity;
    if ( isset( $activity['type'] ) && 'Create' === $activity['type'] && ! empty( $activity['object'] ) ) {
        $object = $activity['object'];
    }

    $title = '';
    if ( isset( $object['name'] ) ) {
        $title = sanitize_text_field( $object['name'] );
    } elseif ( isset( $object['summary'] ) ) {
        $title = sanitize_text_field( $object['summary'] );
    } else {
        $title = __( 'Remote ActivityPub Object', 'classyfeds-aggregator' );
    }

    $post_id = wp_insert_post(
        [
            'post_type'   => 'ap_object',
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_content'=> wp_json_encode( $object ),
        ],
        true
    );

    if ( is_wp_error( $post_id ) ) {
        return new \WP_REST_Response( [ 'error' => 'Could not store object' ], 500 );
    }

    return new \WP_REST_Response( [ 'stored' => $post_id ], 202 );
}

/**
 * Output aggregated listings as ActivityStreams collection.
 */
function listings_handler() {
    $remote_inbox      = get_option( 'classyfeds_remote_inbox', '' );
    $filter_categories = get_option( 'classyfeds_filter_categories', '' );
    $filter_posts      = (int) get_option( 'classyfeds_filter_posts', 0 );

    $args = [
        'post_type'      => [ 'listing', 'ap_object' ],
        'post_status'    => 'publish',
        'posts_per_page' => $filter_posts > 0 ? $filter_posts : -1,
    ];

    $cats = [];
    if ( $filter_categories ) {
        $cats = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', $filter_categories ) ) ) );
        if ( $cats ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'listing_category',
                    'field'    => 'slug',
                    'terms'    => $cats,
                ],
            ];
        }
    }

    $query = new \WP_Query( $args );

    $items = [];

    foreach ( $query->posts as $post ) {
        if ( 'ap_object' === $post->post_type ) {
            $data = json_decode( $post->post_content, true );
            if ( is_array( $data ) ) {
                if ( empty( $data['@context'] ) ) {
                    $data['@context'] = 'https://www.w3.org/ns/activitystreams';
                }
                $items[] = $data;
                continue;
            }
        }

        $post_cats = wp_get_post_terms( $post->ID, 'listing_category', [ 'fields' => 'names' ] );
        $items[]   = [
            '@context'     => 'https://www.w3.org/ns/activitystreams',
            'id'           => get_permalink( $post ),
            'type'         => 'Note',
            'name'         => get_the_title( $post ),
            'content'      => apply_filters( 'the_content', $post->post_content ),
            'url'          => get_permalink( $post ),
            'published'    => mysql2date( 'c', $post->post_date_gmt, false ),
            'attributedTo' => home_url(),
            'category'     => $post_cats,
            'listingType'  => get_post_meta( $post->ID, '_listing_type', true ),
        ];
    }

    if ( $remote_inbox ) {
        $response = wp_remote_get( $remote_inbox );
        if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            if ( isset( $data['orderedItems'] ) && is_array( $data['orderedItems'] ) ) {
                foreach ( $data['orderedItems'] as $item ) {
                    if ( ! empty( $cats ) ) {
                        $item_cats = [];
                        if ( isset( $item['category'] ) ) {
                            $item_cats = (array) $item['category'];
                        }
                        if ( ! array_intersect( $cats, $item_cats ) ) {
                            continue;
                        }
                    }
                    $items[] = $item;
                }
            }
        }
    }

    if ( $filter_posts > 0 ) {
        $items = array_slice( $items, 0, $filter_posts );
    }

    $collection = [
        '@context'     => 'https://www.w3.org/ns/activitystreams',
        'id'           => rest_url( 'classyfeds/v1/listings' ),
        'type'         => 'OrderedCollection',
        'totalItems'   => count( $items ),
        'orderedItems' => $items,
    ];

    return new \WP_REST_Response( $collection );
}

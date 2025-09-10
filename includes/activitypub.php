<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register ActivityPub REST endpoints.
add_action( 'rest_api_init', function() {
    register_rest_route(
        'classyfeds/v1',
        '/inbox',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'classyfeds_inbox_handler',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'classyfeds/v1',
        '/listings',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'classyfeds_listings_handler',
            'permission_callback' => '__return_true',
        ]
    );
} );

// Handle incoming ActivityPub objects and store them as "ap_object" posts.
function classyfeds_inbox_handler( WP_REST_Request $request ) {
    $activity = $request->get_json_params();

    if ( empty( $activity ) || ! is_array( $activity ) ) {
        return new WP_REST_Response( [ 'error' => 'Invalid object' ], 400 );
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
        $title = __( 'Remote ActivityPub Object', 'classyfeds' );
    }

    $post_id = wp_insert_post(
        [
            'post_type'    => 'ap_object',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => wp_json_encode( $object ),
        ],
        true
    );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'error' => 'Could not store object' ], 500 );
    }

    return new WP_REST_Response( [ 'stored' => $post_id ], 202 );
}

// Retrieve listings and ActivityPub objects as an ActivityStreams collection.
function classyfeds_listings_handler( WP_REST_Request $request ) {
    $query = new WP_Query(
        [
            'post_type'      => [ 'listing', 'ap_object' ],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]
    );

    $items = [];

    foreach ( $query->posts as $post ) {
        if ( 'ap_object' === $post->post_type ) {
            $data = json_decode( $post->post_content, true );
            if ( is_array( $data ) ) {
                $data['@context'] = [
                    'https://www.w3.org/ns/activitystreams',
                    [
                        'price'    => 'https://schema.org/price',
                        'shipping' => 'https://schema.org/shippingDetails',
                        'category' => 'https://schema.org/category',
                    ],
                ];
                $data['to'] = $data['to'] ?? 'https://www.w3.org/ns/activitystreams#Public';
                $data['cc'] = $data['cc'] ?? 'https://www.w3.org/ns/activitystreams#Public';

                if ( empty( $data['price'] ) || empty( $data['shipping'] ) || empty( $data['category'] ) ) {
                    continue;
                }

                $items[] = $data;
                continue;
            }
        }

        $cats     = wp_get_post_terms( $post->ID, 'listing_category', [ 'fields' => 'names' ] );
        $price    = get_post_meta( $post->ID, '_price', true );
        $shipping = get_post_meta( $post->ID, '_shipping', true );

        if ( empty( $price ) || empty( $shipping ) || empty( $cats ) ) {
            continue;
        }

        $items[] = [
            '@context'     => [
                'https://www.w3.org/ns/activitystreams',
                [
                    'price'    => 'https://schema.org/price',
                    'shipping' => 'https://schema.org/shippingDetails',
                    'category' => 'https://schema.org/category',
                ],
            ],
            'id'           => get_permalink( $post ),
            'type'         => 'Note',
            'name'         => get_the_title( $post ),
            'content'      => apply_filters( 'the_content', $post->post_content ),
            'url'          => get_permalink( $post ),
            'published'    => mysql2date( 'c', $post->post_date_gmt, false ),
            'attributedTo' => home_url(),
            'to'           => 'https://www.w3.org/ns/activitystreams#Public',
            'cc'           => 'https://www.w3.org/ns/activitystreams#Public',
            'price'        => $price,
            'shipping'     => $shipping,
            'category'     => $cats,
            'listingType'  => get_post_meta( $post->ID, '_listing_type', true ),
        ];
    }

    $collection = [
        '@context'     => 'https://www.w3.org/ns/activitystreams',
        'id'           => rest_url( 'classyfeds/v1/listings' ),
        'type'         => 'OrderedCollection',
        'totalItems'   => count( $items ),
        'orderedItems' => $items,
    ];

    return new WP_REST_Response( $collection );
}

// Send a listing to a remote ActivityPub inbox.
function classyfeds_notify_remote( $post_id, $title, $content, $cat_names, $price, $shipping, $image_id = 0 ) {
    $remote = get_option( 'classyfeds_remote_inbox' );
    if ( ! $remote ) {
        return;
    }

    $payload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type'     => 'Create',
        'actor'    => home_url(),
        'object'   => [
            'type'     => 'Note',
            'name'     => $title,
            'content'  => $content,
            'url'      => get_permalink( $post_id ),
            'category' => $cat_names,
            'price'    => $price,
            'shipping' => $shipping,
        ],
    ];

    if ( $image_id ) {
        $payload['object']['image'] = wp_get_attachment_url( $image_id );
    }

    wp_remote_post(
        $remote,
        [
            'headers' => [ 'Content-Type' => 'application/activity+json' ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ]
    );
}


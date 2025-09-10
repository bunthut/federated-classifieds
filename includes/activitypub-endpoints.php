<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verify HTTP signature of an ActivityPub request.
 *
 * @param WP_REST_Request $request Request object.
 * @return bool True on valid signature or if none provided.
 */
function classyfeds_aggregator_verify_signature( WP_REST_Request $request ) {
    $signature = $request->get_header( 'signature' );
    if ( empty( $signature ) ) {
        return true;
    }

    $sig_params = [];
    foreach ( explode( ',', $signature ) as $part ) {
        if ( strpos( $part, '=' ) !== false ) {
            list( $k, $v ) = array_map( 'trim', explode( '=', $part, 2 ) );
            $sig_params[ strtolower( $k ) ] = trim( $v, '"' );
        }
    }

    if ( empty( $sig_params['keyid'] ) || empty( $sig_params['signature'] ) ) {
        return false;
    }

    $key_res = wp_remote_get( $sig_params['keyid'] );
    if ( 200 !== wp_remote_retrieve_response_code( $key_res ) ) {
        return false;
    }

    $body = wp_remote_retrieve_body( $key_res );
    $data = json_decode( $body, true );
    if ( empty( $data['publicKey']['publicKeyPem'] ) ) {
        return false;
    }

    $headers        = array_change_key_case( $request->get_headers(), CASE_LOWER );
    $signed_headers = explode( ' ', $sig_params['headers'] ?? '(request-target)' );

    $signing_string = '';
    foreach ( $signed_headers as $header ) {
        $header = trim( $header );
        if ( '(request-target)' === $header ) {
            $signing_string .= '(request-target): ' . strtolower( $request->get_method() ) . ' ' . $request->get_route() . "\n";
        } elseif ( isset( $headers[ $header ] ) ) {
            $signing_string .= $header . ': ' . implode( ', ', $headers[ $header ] ) . "\n";
        }
    }
    $signing_string = rtrim( $signing_string, "\n" );

    $pubkey = openssl_pkey_get_public( $data['publicKey']['publicKeyPem'] );
    if ( ! $pubkey ) {
        return false;
    }

    $verified = openssl_verify( $signing_string, base64_decode( $sig_params['signature'] ), $pubkey, OPENSSL_ALGO_SHA256 );
    openssl_free_key( $pubkey );

    return 1 === $verified;
}

add_action( 'rest_api_init', function() {
    register_rest_route(
        'classyfeds/v1',
        '/inbox',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'classyfeds_aggregator_inbox_handler',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'classyfeds/v1',
        '/listings',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'classyfeds_aggregator_listings_handler',
            'permission_callback' => '__return_true',
        ]
    );
} );

function classyfeds_aggregator_inbox_handler( WP_REST_Request $request ) {
    if ( ! classyfeds_aggregator_verify_signature( $request ) ) {
        return new WP_REST_Response( [ 'error' => 'Invalid signature' ], 401 );
    }

    $activity = $request->get_json_params();

    if ( empty( $activity ) || ! is_array( $activity ) ) {
        return new WP_REST_Response( [ 'error' => 'Invalid object' ], 400 );
    }

    $object = $activity;
    if ( isset( $activity['type'] ) && 'Create' === $activity['type'] && ! empty( $activity['object'] ) ) {
        $object = $activity['object'];
    }

    $post_id = classyfeds_aggregator_store_ap_object( $object );
    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'error' => 'Could not store object' ], 500 );
    }

    return new WP_REST_Response( [ 'stored' => $post_id ], 202 );
}

function classyfeds_aggregator_listings_handler( WP_REST_Request $request ) {
    $remote_inbox      = get_option( 'classyfeds_remote_inbox', '' );
    $filter_categories = get_option( 'classyfeds_filter_categories', '' );
    $filter_posts      = (int) get_option( 'classyfeds_filter_posts', 0 );

    $args = [
        'post_type'      => [ 'listing', 'ap_object' ],
        'post_status'    => 'publish',
        'posts_per_page' => $filter_posts > 0 ? $filter_posts : -1,
    ];

    $filter_cat_slugs = [];
    if ( $filter_categories ) {
        $filter_cat_slugs = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', $filter_categories ) ) ) );
        if ( $filter_cat_slugs ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'listing_category',
                    'field'    => 'slug',
                    'terms'    => $filter_cat_slugs,
                ],
            ];
        }
    }

    $query = new WP_Query( $args );
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
                    if ( $filter_cat_slugs ) {
                        $item_cats = [];
                        if ( isset( $item['category'] ) ) {
                            $item_cats = (array) $item['category'];
                        }
                        if ( ! array_intersect( $filter_cat_slugs, $item_cats ) ) {
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

    return new WP_REST_Response( $collection );
}

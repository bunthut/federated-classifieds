<?php
/**
 * Plugin Name: Fed Classifieds Aggregator (MVP)
 * Description: Standalone aggregator page for federated classifieds.
 * Version: 0.1.0
 * Author: thomi@etik.com + amis
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register a custom post type for storing incoming ActivityPub objects.
 */
add_action( 'init', function() {
    register_post_type( 'ap_object', [
        'labels' => [
            'name'          => __( 'ActivityPub Objects', 'fed-classifieds-aggregator' ),
            'singular_name' => __( 'ActivityPub Object', 'fed-classifieds-aggregator' ),
        ],
        'public'       => false,
        'show_ui'      => false,
        'show_in_rest' => false,
        'supports'     => [ 'title', 'editor' ],
    ] );
} );

/**
 * Handle plugin activation: ensure the Classifieds page exists.
 */
function fed_classifieds_aggregator_activate() {
    $page_id = (int) get_option( 'fed_classifieds_page_id' );

    if ( $page_id && get_post( $page_id ) ) {
        // Page already exists.
    } else {
        $page    = get_page_by_path( 'classifieds' );
        $page_id = $page ? $page->ID : 0;

        if ( ! $page_id ) {
            $page_id = wp_insert_post( [
                'post_title'  => __( 'Classifieds', 'fed-classifieds-aggregator' ),
                'post_name'   => 'classifieds',
                'post_status' => 'publish',
                'post_type'   => 'page',
            ] );
        }

        if ( $page_id ) {
            update_option( 'fed_classifieds_page_id', $page_id );
        }
    }
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'fed_classifieds_aggregator_activate' );

/**
 * Register settings page in the admin under Settings → Classifieds Aggregator.
 */
add_action( 'admin_menu', function() {
    add_options_page(
        __( 'Classifieds Aggregator', 'fed-classifieds-aggregator' ),
        __( 'Classifieds Aggregator', 'fed-classifieds-aggregator' ),
        'manage_options',
        'fed-classifieds-aggregator',
        'fed_classifieds_aggregator_settings_page'
    );
} );

/**
 * Render settings page for selecting the aggregator page.
 */
function fed_classifieds_aggregator_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $message = '';

    if ( isset( $_POST['fed_classifieds_save'] ) && check_admin_referer( 'fed_classifieds_save_settings', 'fed_classifieds_nonce' ) ) {
        $page_id = isset( $_POST['fed_classifieds_page_id'] ) ? absint( wp_unslash( $_POST['fed_classifieds_page_id'] ) ) : 0;
        $slug    = isset( $_POST['fed_classifieds_slug'] ) ? sanitize_title( wp_unslash( $_POST['fed_classifieds_slug'] ) ) : '';

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

        if ( $page_id ) {
            update_option( 'fed_classifieds_page_id', $page_id );
            $message = __( 'Settings saved.', 'fed-classifieds-aggregator' );
        }
    }

    $current_page = (int) get_option( 'fed_classifieds_page_id' );
    $page_link    = $current_page ? get_permalink( $current_page ) : '';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Classifieds Aggregator', 'fed-classifieds-aggregator' ) . '</h1>';

    if ( $message ) {
        echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field( 'fed_classifieds_save_settings', 'fed_classifieds_nonce' );

    echo '<table class="form-table">';
    echo '<tr><th scope="row">' . esc_html__( 'Page', 'fed-classifieds-aggregator' ) . '</th><td>';
    wp_dropdown_pages(
        [
            'name'             => 'fed_classifieds_page_id',
            'selected'         => $current_page,
            'show_option_none' => __( '— Select —', 'fed-classifieds-aggregator' ),
        ]
    );
    echo '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'or Slug', 'fed-classifieds-aggregator' ) . '</th><td><input type="text" name="fed_classifieds_slug" value="" class="regular-text" /></td></tr>';
    echo '</table>';

    submit_button( __( 'Save Changes', 'fed-classifieds-aggregator' ), 'primary', 'fed_classifieds_save' );

    if ( $page_link ) {
        echo '<p><a class="button" href="' . esc_url( $page_link ) . '" target="_blank">' . esc_html__( 'Open Aggregator Page', 'fed-classifieds-aggregator' ) . '</a></p>';
    }

    echo '</form>';
    echo '</div>';
}

/**
 * Load template for the Classifieds page.
 */
add_filter( 'template_include', function( $template ) {
    $page_id = (int) get_option( 'fed_classifieds_page_id' );
    if ( $page_id && (int) get_queried_object_id() === $page_id ) {
        $new_template = plugin_dir_path( __FILE__ ) . 'templates/aggregator-page.php';
        if ( file_exists( $new_template ) ) {
            return $new_template;
        }
    }
    return $template;
} );

/**
 * Enqueue frontend assets for the Classifieds page.
 */
add_action( 'wp_enqueue_scripts', function() {
    $page_id = (int) get_option( 'fed_classifieds_page_id' );
    if ( $page_id && (int) get_queried_object_id() === $page_id ) {
        wp_enqueue_style( 'fed-classifieds', plugin_dir_url( __FILE__ ) . 'assets/css/fed-classifieds.css', [], '0.1.0' );
        wp_enqueue_script( 'fed-classifieds', plugin_dir_url( __FILE__ ) . 'assets/js/fed-classifieds.js', [ 'jquery' ], '0.1.0', true );
    }
} );

/**
 * Register ActivityPub REST endpoints.
 */
add_action( 'rest_api_init', function() {
    register_rest_route(
        'fed-classifieds/v1',
        '/inbox',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'fed_classifieds_aggregator_inbox_handler',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'fed-classifieds/v1',
        '/listings',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'fed_classifieds_aggregator_listings_handler',
            'permission_callback' => '__return_true',
        ]
    );
} );

/**
 * Handle incoming ActivityPub objects and store them as "ap_object" posts.
 *
 * Supports bare objects as well as "Create" activities.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response.
 */
function fed_classifieds_aggregator_inbox_handler( WP_REST_Request $request ) {
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
        $title = __( 'Remote ActivityPub Object', 'fed-classifieds-aggregator' );
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
        return new WP_REST_Response( [ 'error' => 'Could not store object' ], 500 );
    }

    return new WP_REST_Response( [ 'stored' => $post_id ], 202 );
}

/**
 * Expose aggregated listings as an ActivityStreams Collection.
 *
 * @return WP_REST_Response Response.
 */
function fed_classifieds_aggregator_listings_handler() {
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
                if ( empty( $data['@context'] ) ) {
                    $data['@context'] = 'https://www.w3.org/ns/activitystreams';
                }
                $items[] = $data;
                continue;
            }
        }

        $cats = wp_get_post_terms( $post->ID, 'category', [ 'fields' => 'names' ] );
        $items[] = [
            '@context'     => 'https://www.w3.org/ns/activitystreams',
            'id'           => get_permalink( $post ),
            'type'         => 'Note',
            'name'         => get_the_title( $post ),
            'content'      => apply_filters( 'the_content', $post->post_content ),
            'url'          => get_permalink( $post ),
            'published'    => mysql2date( 'c', $post->post_date_gmt, false ),
            'attributedTo' => home_url(),
            'category'     => $cats,
            'listingType'  => get_post_meta( $post->ID, '_listing_type', true ),
        ];
    }

    $collection = [
        '@context'     => 'https://www.w3.org/ns/activitystreams',
        'id'           => rest_url( 'fed-classifieds/v1/listings' ),
        'type'         => 'OrderedCollection',
        'totalItems'   => count( $items ),
        'orderedItems' => $items,
    ];

    return new WP_REST_Response( $collection );
}

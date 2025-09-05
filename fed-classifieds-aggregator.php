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
}
register_activation_hook( __FILE__, 'fed_classifieds_aggregator_activate' );

/**
 * Load template for the Classifieds page.
 */
add_filter( 'template_include', function( $template ) {
    $page_id = (int) get_option( 'fed_classifieds_page_id' );
    if ( $page_id && is_page( $page_id ) ) {
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
    if ( $page_id && is_page( $page_id ) ) {
        wp_enqueue_style( 'fed-classifieds', plugin_dir_url( __FILE__ ) . 'assets/css/fed-classifieds.css', [], '0.1.0' );
        wp_enqueue_script( 'fed-classifieds', plugin_dir_url( __FILE__ ) . 'assets/js/fed-classifieds.js', [ 'jquery' ], '0.1.0', true );
    }
} );

/**
 * Register ActivityPub inbox endpoint to accept federated objects.
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
} );

/**
 * Handle incoming ActivityPub objects and store them as "ap_object" posts.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response.
 */
function fed_classifieds_aggregator_inbox_handler( WP_REST_Request $request ) {
    $object = $request->get_json_params();

    if ( empty( $object ) || ! is_array( $object ) ) {
        return new WP_REST_Response( [ 'error' => 'Invalid object' ], 400 );
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

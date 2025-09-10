<?php
/**
 * Plugin Name: Classyfeds Aggregator (MVP)
 * Description: Standalone aggregator page for federated classifieds.
 * Version: 0.1.0
 * Author: thomi@etik.com + amis
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Bootstrap components.
require_once plugin_dir_path( __FILE__ ) . 'includes/ap-object-store.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/activitypub-endpoints.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/render.php';

/**
 * Handle plugin activation: ensure the Classifieds page exists.
 */
function classyfeds_aggregator_activate() {
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
register_activation_hook( __FILE__, 'classyfeds_aggregator_activate' );

/**
 * Register settings page as top-level admin menu.
 */
add_action( 'admin_menu', function() {
    add_menu_page(
        __( 'Classifieds Aggregator', 'classyfeds-aggregator' ),
        __( 'Classifieds', 'classyfeds-aggregator' ),
        'manage_options',
        'classyfeds-aggregator',
        'classyfeds_aggregator_settings_page',
        'dashicons-megaphone',
        25
    );
} );

/**
 * Render settings page for selecting the aggregator page.
 */
function classyfeds_aggregator_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $message           = '';
    $current_page      = (int) get_option( 'classyfeds_page_id' );
    $remote_inbox      = get_option( 'classyfeds_remote_inbox', '' );
    $filter_categories = get_option( 'classyfeds_filter_categories', '' );
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

        $remote_inbox      = isset( $_POST['classyfeds_remote_inbox'] ) ? esc_url_raw( wp_unslash( $_POST['classyfeds_remote_inbox'] ) ) : '';
        $filter_categories = isset( $_POST['classyfeds_filter_categories'] ) ? sanitize_text_field( wp_unslash( $_POST['classyfeds_filter_categories'] ) ) : '';
        $filter_posts      = isset( $_POST['classyfeds_filter_posts'] ) ? absint( wp_unslash( $_POST['classyfeds_filter_posts'] ) ) : 0;

        if ( $page_id ) {
            update_option( 'classyfeds_page_id', $page_id );
        }
        update_option( 'classyfeds_remote_inbox', $remote_inbox );
        update_option( 'classyfeds_filter_categories', $filter_categories );
        update_option( 'classyfeds_filter_posts', $filter_posts );

        $message = __( 'Settings saved.', 'classyfeds-aggregator' );

        $current_page = $page_id;
    }

    $page_link = $current_page ? get_permalink( $current_page ) : '';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Classifieds Aggregator', 'classyfeds-aggregator' ) . '</h1>';

    if ( $message ) {
        echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field( 'classyfeds_save_settings', 'classyfeds_nonce' );

    echo '<table class="form-table">';
    echo '<tr><th scope="row">' . esc_html__( 'Page', 'classyfeds-aggregator' ) . '</th><td>';
    wp_dropdown_pages(
        [
            'name'             => 'classyfeds_page_id',
            'selected'         => $current_page,
            'show_option_none' => __( '— Select —', 'classyfeds-aggregator' ),
        ]
    );
    echo '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'or Slug', 'classyfeds-aggregator' ) . '</th><td><input type="text" name="classyfeds_slug" value="" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'Remote Inbox URL', 'classyfeds-aggregator' ) . '</th><td><input type="url" name="classyfeds_remote_inbox" value="' . esc_attr( $remote_inbox ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'Filter Categories', 'classyfeds-aggregator' ) . '</th><td><input type="text" name="classyfeds_filter_categories" value="' . esc_attr( $filter_categories ) . '" class="regular-text" /><p class="description">' . esc_html__( 'Comma-separated category slugs', 'classyfeds-aggregator' ) . '</p></td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'Posts Per Page', 'classyfeds-aggregator' ) . '</th><td><input type="number" name="classyfeds_filter_posts" value="' . esc_attr( $filter_posts ) . '" class="small-text" min="0" /></td></tr>';
    echo '</table>';

    submit_button( __( 'Save Changes', 'classyfeds-aggregator' ), 'primary', 'classyfeds_save' );

    if ( $page_link ) {
        echo '<p><a class="button" href="' . esc_url( $page_link ) . '" target="_blank">' . esc_html__( 'Open Aggregator Page', 'classyfeds-aggregator' ) . '</a></p>';
    }

    echo '</form>';
    echo '</div>';
}

/**
 * Enqueue frontend assets for the Classifieds page.
 */
add_action( 'wp_enqueue_scripts', function() {
    $page_id = (int) get_option( 'classyfeds_page_id' );
    if ( $page_id && (int) get_queried_object_id() === $page_id ) {
        wp_enqueue_style( 'classyfeds', plugin_dir_url( __FILE__ ) . 'assets/css/classyfeds.css', [], '0.1.0' );
        wp_enqueue_script( 'classyfeds', plugin_dir_url( __FILE__ ) . 'assets/js/classyfeds.js', [ 'jquery' ], '0.1.0', true );

        wp_localize_script(
            'classyfeds',
            'classyfedsOptions',
            [
                'remoteInbox' => get_option( 'classyfeds_remote_inbox', '' ),
                'categories'  => get_option( 'classyfeds_filter_categories', '' ),
                'posts'       => (int) get_option( 'classyfeds_filter_posts', 0 ),
            ]
        );
    }
} );

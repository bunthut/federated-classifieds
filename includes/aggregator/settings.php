<?php
namespace ClassyFeds;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
add_action( 'admin_menu', __NAMESPACE__ . '\\register_settings_menu' );

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

        update_option( 'classyfeds_page_id', $page_id );
        update_option( 'classyfeds_remote_inbox', $remote_inbox );
        update_option( 'classyfeds_filter_categories', $filter_categories );
        update_option( 'classyfeds_filter_posts', $filter_posts );

        $current_page = $page_id;
        $message      = __( 'Settings saved.', 'classyfeds-aggregator' );
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

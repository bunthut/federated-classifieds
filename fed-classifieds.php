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
} );

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
} );

/**
 * Set default expiration date (60 days) when a listing is saved.
 */
add_action( 'save_post_listing', function( $post_id, $post, $update ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    $expires = get_post_meta( $post_id, '_expires_at', true );
    if ( ! $expires ) {
        $expires = strtotime( '+60 days', current_time( 'timestamp' ) );
        update_post_meta( $post_id, '_expires_at', $expires );
    }
}, 10, 3 );

/**
 * Handle plugin activation tasks.
 *
 * Creates the "Classifieds" page if it does not exist and schedules the
 * daily expiration event.
 */
function fed_classifieds_activate() {
    $page_id = (int) get_option( 'fed_classifieds_page_id' );

    if ( $page_id && get_post( $page_id ) ) {
        // Page already exists and is stored in options.
    } else {
        $page    = get_page_by_path( 'classifieds' );
        $page_id = $page ? $page->ID : 0;

        if ( ! $page_id ) {
            $page_id = wp_insert_post( [
                'post_title'  => __( 'Classifieds', 'fed-classifieds' ),
                'post_name'   => 'classifieds',
                'post_status' => 'publish',
                'post_type'   => 'page',
            ] );
        }

        if ( $page_id ) {
            update_option( 'fed_classifieds_page_id', $page_id );
        }
    }

    if ( ! wp_next_scheduled( 'fed_classifieds_expire_event' ) ) {
        wp_schedule_event( time(), 'daily', 'fed_classifieds_expire_event' );
    }
}

register_activation_hook( __FILE__, 'fed_classifieds_activate' );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'fed_classifieds_expire_event' );
} );

add_action( 'fed_classifieds_expire_event', function() {
    $now   = current_time( 'timestamp' );
    $posts = get_posts( [
        'post_type'   => 'listing',
        'post_status' => 'publish',
        'meta_key'    => '_expires_at',
        'meta_value'  => $now,
        'meta_compare'=> '<=',
        'fields'      => 'ids',
        'numberposts' => -1,
    ] );

    foreach ( $posts as $post_id ) {
        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'expired' ] );
    }
} );

/**
 * Output JSON-LD structured data for listings.
 */
add_action( 'wp_head', function() {
    if ( ! is_singular( 'listing' ) ) {
        return;
    }

    $post    = get_queried_object();
    $price   = get_post_meta( $post->ID, '_price', true );
    $expires = get_post_meta( $post->ID, '_expires_at', true );
    $image   = get_the_post_thumbnail_url( $post->ID, 'full' );

    $data = [
        '@context' => 'https://schema.org/',
        '@type'    => 'Offer',
        'name'     => get_the_title( $post ),
        'description' => wp_strip_all_tags( get_the_excerpt( $post ) ),
        'url'      => get_permalink( $post ),
    ];

    if ( $image ) {
        $data['image'] = $image;
    }
    if ( $price ) {
        $data['price'] = $price;
    }
    if ( $expires ) {
        $data['expires'] = gmdate( 'c', (int) $expires );
    }

    echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
} );

/**
 * Load template for the Classifieds page.
 */
add_filter( 'template_include', function( $template ) {
    $page_id = (int) get_option( 'fed_classifieds_page_id' );
    if ( $page_id && is_page( $page_id ) ) {
        $new_template = plugin_dir_path( __FILE__ ) . 'templates/listings-page.php';
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
 * Register the Fed Classifieds admin dashboard.
 */
add_action( 'admin_menu', function() {
    add_menu_page(
        __( 'Fed Classifieds', 'fed-classifieds' ),
        __( 'Fed Classifieds', 'fed-classifieds' ),
        'manage_options',
        'fed_classifieds_dashboard',
        'fed_classifieds_render_dashboard',
        'dashicons-list-view',
        26
    );
} );

/**
 * Render the admin dashboard page.
 */
function fed_classifieds_render_dashboard() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $counts  = wp_count_posts( 'listing' );
    $publish = isset( $counts->publish ) ? $counts->publish : 0;
    $expired = isset( $counts->expired ) ? $counts->expired : 0;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Fed Classifieds', 'fed-classifieds' ); ?></h1>
        <p><?php esc_html_e( 'Manage your classified listings. Listings automatically expire after 60 days.', 'fed-classifieds' ); ?></p>
        <h2><?php esc_html_e( 'Statistics', 'fed-classifieds' ); ?></h2>
        <ul>
            <li><?php printf( esc_html__( 'Published listings: %s', 'fed-classifieds' ), number_format_i18n( $publish ) ); ?></li>
            <li><?php printf( esc_html__( 'Expired listings: %s', 'fed-classifieds' ), number_format_i18n( $expired ) ); ?></li>
        </ul>
    </div>
    <?php
}


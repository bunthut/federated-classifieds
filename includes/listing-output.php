<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Cron: expire listings.
add_action( 'classyfeds_expire_event', function() {
    $now   = current_time( 'timestamp' );
    $posts = get_posts( [
        'post_type'    => 'listing',
        'post_status'  => 'publish',
        'meta_key'     => '_expires_at',
        'meta_value'   => $now,
        'meta_compare' => '<=',
        'fields'       => 'ids',
        'numberposts'  => -1,
    ] );

    foreach ( $posts as $id ) {
        wp_update_post( [ 'ID' => $id, 'post_status' => 'expired' ] );
    }
} );

// Output JSON-LD structured data for listings.
add_action( 'wp_head', function() {
    if ( ! is_singular( 'listing' ) ) {
        return;
    }

    $post    = get_queried_object();
    $price   = get_post_meta( $post->ID, '_price', true );
    $expires = get_post_meta( $post->ID, '_expires_at', true );
    $image   = get_the_post_thumbnail_url( $post->ID, 'full' );

    $data = [
        '@context'    => 'https://schema.org/',
        '@type'       => 'Offer',
        'name'        => get_the_title( $post ),
        'description' => wp_strip_all_tags( get_the_excerpt( $post ) ),
        'url'         => get_permalink( $post ),
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

// Load template for the Classifieds page.
add_filter( 'template_include', function( $template ) {
    $page_id = (int) get_option( 'classyfeds_page_id' );
    if ( $page_id && is_page( $page_id ) ) {
        $new_template = plugin_dir_path( __DIR__ ) . 'templates/listings-page.php';
        if ( file_exists( $new_template ) ) {
            return $new_template;
        }
    }
    return $template;
} );

// Enqueue frontend assets for the Classifieds page.
add_action( 'wp_enqueue_scripts', function() {
    $page_id = (int) get_option( 'classyfeds_page_id' );
    if ( $page_id && is_page( $page_id ) ) {
        wp_enqueue_style( 'classyfeds', plugin_dir_url( __DIR__ ) . 'assets/css/classyfeds.css', [], '0.1.0' );
        wp_enqueue_script( 'classyfeds', plugin_dir_url( __DIR__ ) . 'assets/js/classyfeds.js', [ 'jquery' ], '0.1.0', true );
    }
} );


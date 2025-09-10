<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Upload an image from a temporary path and attach it to a listing.
function classyfeds_upload_listing_image( $image_path, $post_id ) {
    if ( ! $image_path || ! file_exists( $image_path ) ) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file = [
        'name'     => basename( $image_path ),
        'tmp_name' => $image_path,
    ];

    $image_id = media_handle_sideload( $file, $post_id );

    return is_wp_error( $image_id ) ? 0 : (int) $image_id;
}

// Validate form data, create the listing and notify any remote inbox.
function classyfeds_process_listing_submission( array $data, array $files ) {
    $title      = isset( $data['listing_title'] ) ? sanitize_text_field( $data['listing_title'] ) : '';
    $content    = isset( $data['listing_description'] ) ? wp_kses_post( $data['listing_description'] ) : '';
    $price      = isset( $data['listing_price'] ) ? sanitize_text_field( $data['listing_price'] ) : '';
    $shipping   = isset( $data['listing_shipping'] ) ? sanitize_text_field( $data['listing_shipping'] ) : '';
    $cat_names  = isset( $data['listing_category'] ) ? (array) $data['listing_category'] : [];
    $image_path = isset( $files['listing_image'][0] ) ? $files['listing_image'][0] : '';

    if ( '' === $title || '' === $content || '' === $price || '' === $shipping || empty( $cat_names ) ) {
        return new WP_Error( 'classyfeds_incomplete', __( 'Required fields are missing.', 'classyfeds' ) );
    }

    $post_id = wp_insert_post(
        [
            'post_type'    => 'listing',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $content,
        ],
        true
    );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    $cat_ids = [];
    foreach ( $cat_names as $cat_name ) {
        $term = get_term_by( 'name', $cat_name, 'listing_category' );
        if ( $term ) {
            $cat_ids[] = (int) $term->term_id;
        }
    }
    if ( $cat_ids ) {
        wp_set_post_terms( $post_id, $cat_ids, 'listing_category' );
    }

    $image_id = classyfeds_upload_listing_image( $image_path, $post_id );

    update_post_meta( $post_id, '_price', $price );
    update_post_meta( $post_id, '_shipping', $shipping );

    classyfeds_notify_remote( $post_id, $title, $content, $cat_names, $price, $shipping, $image_id );

    return $post_id;
}

// Handle Contact Form 7 submissions and store them as listings.
function classyfeds_cf7_handle_submission( $contact_form ) {
    $expected_form_id = (int) get_option( 'classyfeds_cf7_form_id' );
    if ( $contact_form->id() !== $expected_form_id ) {
        return;
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'publish_listings' ) ) {
        return;
    }

    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) {
        return;
    }

    classyfeds_process_listing_submission( $submission->get_posted_data(), $submission->uploaded_files() );
}

if ( class_exists( 'WPCF7' ) ) {
    add_action( 'wpcf7_mail_sent', 'classyfeds_cf7_handle_submission' );
}


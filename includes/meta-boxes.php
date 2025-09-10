<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register custom meta boxes for the listing post type.
function classyfeds_register_listing_metaboxes() {
    remove_meta_box( 'listing_categorydiv', 'listing', 'side' );

    add_meta_box(
        'classyfeds_listing_categorydiv',
        __( 'Listing Categories', 'classyfeds' ),
        'classyfeds_listing_category_meta_box',
        'listing',
        'side'
    );
}

// Output the category checklist without term management options.
function classyfeds_listing_category_meta_box( $post ) {
    wp_terms_checklist( $post->ID, [ 'taxonomy' => 'listing_category' ] );
}

// Add "Typ" dropdown to the listing editor.
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'listing_type',
        __( 'Typ', 'classyfeds' ),
        function( $post ) {
            $value = get_post_meta( $post->ID, '_listing_type', true );
            wp_nonce_field( 'save_listing_type', 'listing_type_nonce' );
            echo '<select name="listing_type" id="listing_type" style="width:100%">';
            echo '<option value="Angebot"' . selected( $value, 'Angebot', false ) . '>' . esc_html__( 'Angebot', 'classyfeds' ) . '</option>';
            echo '<option value="Gesuch"' . selected( $value, 'Gesuch', false ) . '>' . esc_html__( 'Gesuch', 'classyfeds' ) . '</option>';
            echo '</select>';
        },
        'listing',
        'side'
    );
} );

// Save listing type and set default expiration date.
add_action( 'save_post_listing', function( $post_id, $post, $update ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    if ( isset( $_POST['listing_type_nonce'] ) && wp_verify_nonce( $_POST['listing_type_nonce'], 'save_listing_type' ) ) {
        $type = isset( $_POST['listing_type'] ) ? sanitize_text_field( wp_unslash( $_POST['listing_type'] ) ) : '';
        if ( $type ) {
            update_post_meta( $post_id, '_listing_type', $type );
        } else {
            delete_post_meta( $post_id, '_listing_type' );
        }
    }

    $expires = get_post_meta( $post_id, '_expires_at', true );
    if ( ! $expires ) {
        $expires = strtotime( '+60 days', current_time( 'timestamp' ) );
        update_post_meta( $post_id, '_expires_at', $expires );
    }
}, 10, 3 );


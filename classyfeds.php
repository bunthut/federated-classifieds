<?php
/**
 * Plugin Name: Classyfeds (MVP)
 * Description: Custom post type "listing" with JSON-LD output and auto-expiration for a federated classifieds network.
 * Version: 0.1.4
 * Author: thomi@etik.com + amis
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once __DIR__ . '/includes/post-types.php';
require_once __DIR__ . '/includes/meta-boxes.php';
require_once __DIR__ . '/includes/listing-output.php';
require_once __DIR__ . '/includes/activitypub.php';
require_once __DIR__ . '/includes/submissions.php';

// Handle plugin activation tasks.
function classyfeds_activate() {
    $page_id = (int) get_option( 'classyfeds_page_id' );

    if ( ! ( $page_id && get_post( $page_id ) ) ) {
        $page    = get_page_by_path( 'classifieds' );
        $page_id = $page ? $page->ID : 0;

        if ( ! $page_id ) {
            $page_id = wp_insert_post( [
                'post_title'  => __( 'Classifieds', 'classyfeds' ),
                'post_name'   => 'classifieds',
                'post_status' => 'publish',
                'post_type'   => 'page',
            ] );
        }

        if ( $page_id ) {
            update_option( 'classyfeds_page_id', $page_id );
        }
    }

    if ( ! wp_next_scheduled( 'classyfeds_expire_event' ) ) {
        wp_schedule_event( time(), 'daily', 'classyfeds_expire_event' );
    }

    // Insert default categories and optional subcategories.
    $default_categories = [
        'Auto, Rad & Boot'                => [ 'Autos', 'Motorräder', 'Boote', 'Fahrräder' ],
        'Elektronik'                      => [ 'Computer', 'Handys & Telefone', 'TV, Video & Audio' ],
        'Haus & Garten'                   => [ 'Möbel & Wohnen', 'Haushaltsgeräte', 'Heimwerker & Bau' ],
        'Mode & Beauty'                   => [],
        'Freizeit, Hobby & Nachbarschaft' => [],
        'Familie, Kind & Baby'            => [],
        'Dienstleistungen'                => [],
        'Jobs'                            => [],
        'Immobilien'                      => [],
    ];
    foreach ( $default_categories as $parent => $children ) {
        $existing  = term_exists( $parent, 'listing_category' );
        $parent_id = $existing ? ( is_array( $existing ) ? $existing['term_id'] : $existing ) : 0;

        if ( ! $parent_id ) {
            $term      = wp_insert_term( $parent, 'listing_category' );
            $parent_id = is_wp_error( $term ) ? 0 : $term['term_id'];
        }

        if ( $parent_id && ! empty( $children ) ) {
            foreach ( $children as $child ) {
                if ( ! term_exists( $child, 'listing_category' ) ) {
                    wp_insert_term( $child, 'listing_category', [ 'parent' => $parent_id ] );
                }
            }
        }
    }

    // Create or update a Contact Form 7 form for submissions.
    $cf7_form_id = (int) get_option( 'classyfeds_cf7_form_id' );
    if ( class_exists( 'WPCF7_ContactForm' ) && ( ! $cf7_form_id || ! get_post( $cf7_form_id ) ) ) {
        $cats = get_terms( [
            'taxonomy'   => 'listing_category',
            'hide_empty' => false,
            'fields'     => 'names',
        ] );
        $cat_options = '';
        foreach ( $cats as $cat ) {
            $cat_options .= '"' . $cat . '" ';
        }
        $form_markup = implode( "\n", [
            '<label>' . __( 'Title', 'classyfeds' ) . ' [text* listing_title]</label>',
            '<label>' . __( 'Description', 'classyfeds' ) . ' [textarea* listing_description]</label>',
            '<label>' . __( 'Category', 'classyfeds' ) . ' [select* listing_category multiple ' . trim( $cat_options ) . ']</label>',
            '<label>' . __( 'Image', 'classyfeds' ) . ' [file listing_image]</label>',
            '<label>' . __( 'Price', 'classyfeds' ) . ' [text* listing_price]</label>',
            '<label>' . __( 'Versandart', 'classyfeds' ) . ' [text* listing_shipping]</label>',
            '[submit "' . esc_attr__( 'Submit', 'classyfeds' ) . '"]',
        ] );
        $form = WPCF7_ContactForm::get_template( [ 'title' => __( 'Submit Listing', 'classyfeds' ) ] );
        $form->set_properties( [ 'form' => $form_markup ] );
        $cf7_form_id = $form->save();
        if ( $cf7_form_id ) {
            update_option( 'classyfeds_cf7_form_id', $cf7_form_id );
        }
    }

    // Ensure a submission page exists with the Contact Form 7 shortcode.
    $form_page_id = (int) get_option( 'classyfeds_form_page_id' );
    $shortcode    = $cf7_form_id ? '[contact-form-7 id="' . $cf7_form_id . '"]' : '';
    if ( ! ( $form_page_id && get_post( $form_page_id ) ) ) {
        $page         = get_page_by_path( 'submit-listing' );
        $form_page_id = $page ? $page->ID : 0;

        if ( ! $form_page_id ) {
            $form_page_id = wp_insert_post(
                [
                    'post_title'   => __( 'Submit Listing', 'classyfeds' ),
                    'post_name'    => 'submit-listing',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_content' => $shortcode,
                ]
            );
        }

        if ( $form_page_id ) {
            update_option( 'classyfeds_form_page_id', $form_page_id );
        }
    } elseif ( $shortcode ) {
        wp_update_post( [ 'ID' => $form_page_id, 'post_content' => $shortcode ] );
    }

    // Register custom capability and assign to default roles.
    add_role(
        'listing_contributor',
        __( 'Listing Contributor', 'classyfeds' ),
        [
            'read'             => true,
            'publish_listings' => true,
        ]
    );

    $default_roles = [ 'author', 'listing_contributor' ];
    update_option( 'classyfeds_publish_roles', $default_roles );

    foreach ( $default_roles as $role_name ) {
        $role = get_role( $role_name );
        if ( $role ) {
            $role->add_cap( 'publish_listings' );
            $role->remove_cap( 'manage_listing_categories' );
        }
    }

    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $admin->add_cap( 'manage_listing_categories' );
    }

    // Flush rewrite rules.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'classyfeds_activate' );

// Handle plugin deactivation tasks.
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'classyfeds_expire_event' );
    flush_rewrite_rules();
} );


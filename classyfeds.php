<?php
/**
 * Plugin Name: Classyfeds (MVP)
 * Description: Custom post type "listing" with JSON-LD output and auto-expiration for a federated classifieds network.
 * Version: 0.1.3
 * Author: thomi@etik.com + amis
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register the "listing" custom post type and taxonomy.
 */
add_action( 'init', function() {
    register_post_type( 'listing', [
        'labels' => [
            'name'          => __( 'Listings', 'classyfeds' ),
            'singular_name' => __( 'Listing', 'classyfeds' ),
        ],
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor', 'thumbnail' ],
        'taxonomies'   => [ 'listing_category', 'post_tag' ],
        'rewrite'      => [ 'slug' => 'listings' ],
    ] );

    register_taxonomy(
        'listing_category',
        'listing',
        [
            'labels'       => [
                'name'          => __( 'Listing Categories', 'classyfeds' ),
                'singular_name' => __( 'Listing Category', 'classyfeds' ),
            ],
            'public'       => true,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ]
    );
} );

/**
 * Add "Typ" dropdown to the listing editor.
 */
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

/**
 * Register a custom post type for storing incoming ActivityPub objects.
 * These posts are not public but can be queried.
 */
add_action( 'init', function() {
    register_post_type( 'ap_object', [
        'labels' => [
            'name'          => __( 'ActivityPub Objects', 'classyfeds' ),
            'singular_name' => __( 'ActivityPub Object', 'classyfeds' ),
        ],
        'public'       => false,
        'show_ui'      => false,
        'show_in_rest' => false,
        'supports'     => [ 'title', 'editor' ],
    ] );
} );

/**
 * Register custom post status "expired".
 */
add_action( 'init', function() {
    register_post_status( 'expired', [
        'label'                     => _x( 'Expired', 'post', 'classyfeds' ),
        'public'                    => false,
        'internal'                  => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'classyfeds' ),
    ] );
} );

/**
 * Save listing type and set default expiration date (60 days).
 */
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

/**
 * Migrate legacy option names to their classyfeds_* equivalents.
 */
function classyfeds_migrate_options() {
    $map = [
        'fed_classifieds_form_page_id'  => 'classyfeds_form_page_id',
        'fed_classifieds_publish_roles' => 'classyfeds_publish_roles',
        'fed_classifieds_remote_inbox'  => 'classyfeds_remote_inbox',
    ];

    foreach ( $map as $old => $new ) {
        $old_value = get_option( $old, null );
        if ( null !== $old_value && false === get_option( $new, false ) ) {
            update_option( $new, $old_value );
        }
        if ( null !== $old_value ) {
            delete_option( $old );
        }
    }
}
add_action( 'plugins_loaded', 'classyfeds_migrate_options' );

/**
 * Handle plugin activation tasks.
 */
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

    // Ensure a submission page exists with the listing form shortcode.
    $form_page_id = (int) get_option( 'classyfeds_form_page_id' );
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
                    'post_content' => '[classyfeds_form]',
                ]
            );
        }

        if ( $form_page_id ) {
            update_option( 'classyfeds_form_page_id', $form_page_id );
        }
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
        }
    }

    // Flush rewrite rules.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'classyfeds_activate' );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'classyfeds_expire_event' );
    flush_rewrite_rules();
} );

/**
 * Add settings page under Options → Classifieds.
 */
add_action( 'admin_menu', function() {
    add_options_page(
        __( 'Classifieds', 'classyfeds' ),
        __( 'Classifieds', 'classyfeds' ),
        'manage_options',
        'classyfeds',
        'classyfeds_settings_page'
    );
} );

/**
 * Render settings page for selecting roles that may publish listings.
 */
function classyfeds_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $editable_roles = get_editable_roles();
    $selected       = (array) get_option( 'classyfeds_publish_roles', [] );
    $message        = '';

    if ( isset( $_POST['classyfeds_save'] ) && check_admin_referer( 'classyfeds_save_settings', 'classyfeds_nonce' ) ) {
        $selected = isset( $_POST['publish_roles'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['publish_roles'] ) ) : [];
        update_option( 'classyfeds_publish_roles', $selected );

        foreach ( $editable_roles as $role_key => $details ) {
            $role = get_role( $role_key );
            if ( ! $role ) {
                continue;
            }
            if ( in_array( $role_key, $selected, true ) ) {
                $role->add_cap( 'publish_listings' );
            } else {
                $role->remove_cap( 'publish_listings' );
            }
        }
        $message = __( 'Settings saved.', 'classyfeds' );
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Classifieds', 'classyfeds' ) . '</h1>';

    if ( $message ) {
        echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field( 'classyfeds_save_settings', 'classyfeds_nonce' );

    echo '<table class="form-table">';
    echo '<tr><th scope="row">' . esc_html__( 'Roles that can publish listings', 'classyfeds' ) . '</th><td>';
    foreach ( $editable_roles as $role_key => $details ) {
        echo '<label><input type="checkbox" name="publish_roles[]" value="' . esc_attr( $role_key ) . '" ' . checked( in_array( $role_key, $selected, true ), true, false ) . ' /> ' . esc_html( $details['name'] ) . '</label><br />';
    }
    echo '</td></tr>';
    echo '</table>';

    submit_button( __( 'Save Changes', 'classyfeds' ), 'primary', 'classyfeds_save' );

    echo '</form>';
    echo '</div>';
}

// Backward-compatibility wrapper.
function fed_classifieds_settings_page() {
    classyfeds_settings_page();
}

/**
 * Cron: expire listings.
 */
add_action( 'classyfeds_expire_event', function() {
    $now   = current_time( 'timestamp' );
    $posts = get_posts( [
        'post_type'    => 'listing',
        'post_status'  => 'publish',
        'meta_key'     => '_expires_at_

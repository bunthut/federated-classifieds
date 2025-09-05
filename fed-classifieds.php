<?php
/**
 * Plugin Name: Fed Classifieds (MVP)
 * Description: Custom post type "listing" with JSON-LD output and auto-expiration for a federated classifieds network.
 * Version: 0.1.3
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
 * Add "Typ" dropdown to the listing editor.
 */
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'listing_type',
        __( 'Typ', 'fed-classifieds' ),
        function( $post ) {
            $value = get_post_meta( $post->ID, '_listing_type', true );
            wp_nonce_field( 'save_listing_type', 'listing_type_nonce' );
            echo '<select name="listing_type" id="listing_type" style="width:100%">';
            echo '<option value="Angebot"' . selected( $value, 'Angebot', false ) . '>' . esc_html__( 'Angebot', 'fed-classifieds' ) . '</option>';
            echo '<option value="Gesuch"' . selected( $value, 'Gesuch', false ) . '>' . esc_html__( 'Gesuch', 'fed-classifieds' ) . '</option>';
            echo '</select>';
        },
        'listing',
        'side'
    );
} );

/**
 * Register a custom post type for storing incoming ActivityPub objects.
 */
add_action( 'init', function() {
    register_post_type( 'ap_object', [
        'labels' => [
            'name'          => __( 'ActivityPub Objects', 'fed-classifieds' ),
            'singular_name' => __( 'ActivityPub Object', 'fed-classifieds' ),
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
 * Handle plugin activation tasks.
 *
 * Creates the "Classifieds" page if it does not exist, ensures a submission page,
 * registers roles/capabilities and schedules the daily expiration event.
 */
function fed_classifieds_activate() {
    // Create/find "Classifieds" page.
    $page_id = (int) get_option( 'fed_classifieds_page_id' );
    if ( ! ( $page_id && get_post( $page_id ) ) ) {
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

    // Seed default categories + children (ein petit aperçu populärer Rubriken).
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
        $existing  = term_exists( $parent, 'category' );
        $parent_id = $existing ? ( is_array( $existing ) ? $existing['term_id'] : $existing ) : 0;
        if ( ! $parent_id ) {
            $term      = wp_insert_term( $parent, 'category' );
            $parent_id = is_wp_error( $term ) ? 0 : $term['term_id'];
        }
        if ( $parent_id && ! empty( $children ) ) {
            foreach ( $children as $child ) {
                if ( ! term_exists( $child, 'category' ) ) {
                    wp_insert_term( $child, 'category', [ 'parent' => $parent_id ] );
                }
            }
        }
    }

    // Ensure a submission page exists with the listing form shortcode.
    $form_page_id = (int) get_option( 'fed_classifieds_form_page_id' );
    if ( ! ( $form_page_id && get_post( $form_page_id ) ) ) {
        $page         = get_page_by_path( 'submit-listing' );
        $form_page_id = $page ? $page->ID : 0;
        if ( ! $form_page_id ) {
            $form_page_id = wp_insert_post( [
                'post_title'   => __( 'Submit Listing', 'fed-classifieds' ),
                'post_name'    => 'submit-listing',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '[fed_classifieds_form]',
            ] );
        }
        if ( $form_page_id ) {
            update_option( 'fed_classifieds_form_page_id', $form_page_id );
        }
    }

    // Register custom role & capability, and assign to default roles.
    if ( ! get_role( 'listing_contributor' ) ) {
        add_role( 'listing_contributor', __( 'Listing Contributor', 'fed-classifieds' ), [
            'read'             => true,
            'publish_listings' => true,
        ] );
    }

    $default_roles = [ 'author', 'listing_contributor' ];
    update_option( 'fed_classifieds_publish_roles', $default_roles );
    foreach ( $default_roles as $role_name ) {
        $role = get_role( $role_name );
        if ( $role ) {
            $role->add_cap( 'publish_listings' );
        }
    }

    // Schedule daily expiration.
    if ( ! wp_next_scheduled( 'fed_classifieds_expire_event' ) ) {
        wp_schedule_event( time(), 'daily', 'fed_classifieds_expire_event' );
    }

    // Flush rewrite rules to ensure custom routes are registered.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'fed_classifieds_activate' );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'fed_classifieds_expire_event' );
    flush_rewrite_rules();
} );

/**
 * Add settings page under Options → Classifieds.
 */
add_action( 'admin_menu', function() {
    add_options_page(
        __( 'Classifieds', 'fed-classifieds' ),
        __( 'Classifieds', 'fed-classifieds' ),
        'manage_options',
        'fed-classifieds',
        'fed_classifieds_settings_page'
    );
} );

/**
 * Render settings page for selecting roles that may publish listings.
 */
function fed_classifieds_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $editable_roles = get_editable_roles();
    $selected       = (array) get_option( 'fed_classifieds_publish_roles', [] );
    $message        = '';

    if ( isset( $_POST['fed_classifieds_save'] ) && check_admin_referer( 'fed_classifieds_save_settings', 'fed_classifieds_nonce' ) ) {
        $selected = isset( $_POST['publish_roles'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['publish_roles'] ) ) : [];
        update_option( 'fed_classifieds_publish_roles', $selected );

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
        $message = __( 'Settings saved.', 'fed-classifieds' );
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Classifieds', 'fed-classifieds' ) . '</h1>';

    if ( $message ) {
        echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field( 'fed_classifieds_save_settings', 'fed_classifieds_nonce' );

    echo '<table class="form-table">';
    echo '<tr><th scope="row">' . esc_html__( 'Roles that can publish listings', 'fed-classifieds' ) . '</th><td>';
    foreach ( $editable_roles as $role_key => $details ) {
        echo '<label><input type="checkbox" name="publish_roles[]" value="' . esc_attr( $role_key ) . '" ' . checked( in_array( $role_key, $selected, true ), true, false ) . ' /> ' . esc_html( $details['name'] ) . '</label><br />';
    }
    echo '</td></tr>';
    echo '</table>';

    submit_button( __( 'Save Changes', 'fed-classifieds' ), 'primary', 'fed_classifieds_save' );

    echo '</form>';
    echo '</div>';
}

/**
 * Cron: expire old listings.
 */
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

    foreach ( $posts as $id ) {
        wp_update_post( [ 'ID' => $id, 'post_status' => 'expired' ] );
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
        '@context'   => 'https://schema.org/',
        '@type'      => 'Offer',
        'name'       => get_the_title( $post ),
        'description'=> wp_strip_all_tags( get_the_excerpt( $post ) ),
        'url'        => get_permalink( $post ),
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
    $pa


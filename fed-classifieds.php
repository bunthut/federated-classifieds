<?php
/**
 * Plugin Name: Fed Classifieds (MVP)
 * Description: Custom post type "listing" with JSON-LD output and auto-expiration for a federated classifieds network.
 * Version: 0.1.1
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
 *
 * These objects are used to represent remote listings that arrive through
 * the federated inbox endpoint. The posts are not public but can be queried
 * so they may appear alongside local listings on the listings page template.
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

    // Insert some default categories similar to popular classifieds sites.
    $default_categories = [
        'Auto & Motorrad',
        'Immobilien',
        'Jobs',
        'Elektronik',
        'Haushalt',
        'Mode & Beauty',
        'Freizeit & Sport',
    ];
    foreach ( $default_categories as $cat ) {
        if ( ! term_exists( $cat, 'category' ) ) {
            wp_insert_term( $cat, 'category' );
        }
    }

    // Flush rewrite rules to ensure custom routes are registered.
    flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'fed_classifieds_activate' );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'fed_classifieds_expire_event' );
    flush_rewrite_rules();
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
 * Register ActivityPub REST endpoints.
 */
add_action( 'rest_api_init', function() {
    register_rest_route(
        'fed-classifieds/v1',
        '/inbox',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'fed_classifieds_inbox_handler',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'fed-classifieds/v1',
        '/listings',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'fed_classifieds_listings_handler',
            'permission_callback' => '__return_true',
        ]
    );
} );

/**
 * Handle incoming ActivityPub objects and store them as "ap_object" posts.
 *
 * Supports bare objects as well as "Create" activities wrapping an object.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response.
 */
function fed_classifieds_inbox_handler( WP_REST_Request $request ) {
    $activity = $request->get_json_params();

    if ( empty( $activity ) || ! is_array( $activity ) ) {
        return new WP_REST_Response( [ 'error' => 'Invalid object' ], 400 );
    }

    $object = $activity;
    if ( isset( $activity['type'] ) && 'Create' === $activity['type'] && ! empty( $activity['object'] ) ) {
        $object = $activity['object'];
    }

    $title = '';
    if ( isset( $object['name'] ) ) {
        $title = sanitize_text_field( $object['name'] );
    } elseif ( isset( $object['summary'] ) ) {
        $title = sanitize_text_field( $object['summary'] );
    } else {
        $title = __( 'Remote ActivityPub Object', 'fed-classifieds' );
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

/**
 * Expose listings as an ActivityStreams Collection.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response containing the collection.
 */
function fed_classifieds_listings_handler( WP_REST_Request $request ) {
    $posts = get_posts(
        [
            'post_type'   => [ 'listing', 'ap_object' ],
            'post_status' => 'publish',
            'numberposts' => -1,
        ]
    );

    $items = [];

    foreach ( $posts as $post ) {
        if ( 'listing' === $post->post_type ) {
            $cats   = wp_get_post_terms( $post->ID, 'category', [ 'fields' => 'names' ] );
            $object = [
                'id'          => get_permalink( $post ),
                'type'        => 'Note',
                'name'        => get_the_title( $post ),
                'content'     => wp_kses_post( $post->post_content ),
                'url'         => get_permalink( $post ),
                'published'   => get_post_time( 'c', true, $post ),
                'attributedTo'=> home_url(),
                'category'    => $cats,
                'listingType' => get_post_meta( $post->ID, '_listing_type', true ),
            ];
            $items[] = $object;
        } else {
            $data = json_decode( $post->post_content, true );
            if ( is_array( $data ) ) {
                $items[] = $data;
            }
        }
    }

    $collection = [
        '@context'     => 'https://www.w3.org/ns/activitystreams',
        'id'           => home_url( '/wp-json/fed-classifieds/v1/listings' ),
        'type'         => 'OrderedCollection',
        'totalItems'   => count( $items ),
        'orderedItems' => $items,
    ];

    return new WP_REST_Response( $collection );
}

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

/**
 * Settings page for configuring remote ActivityPub inbox.
 */
add_action( 'admin_menu', function() {
    add_options_page(
        __( 'Classifieds Settings', 'fed-classifieds' ),
        __( 'Classifieds', 'fed-classifieds' ),
        'manage_options',
        'fed-classifieds-settings',
        'fed_classifieds_settings_page'
    );
} );

/**
 * Render the settings page.
 */
function fed_classifieds_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $message = '';

    if ( isset( $_POST['fed_classifieds_settings_save'] ) && check_admin_referer( 'fed_classifieds_settings', 'fed_classifieds_settings_nonce' ) ) {
        $url = isset( $_POST['fed_classifieds_remote_inbox'] ) ? esc_url_raw( wp_unslash( $_POST['fed_classifieds_remote_inbox'] ) ) : '';
        update_option( 'fed_classifieds_remote_inbox', $url );
        $message = __( 'Settings saved.', 'fed-classifieds' );
    }

    $current = get_option( 'fed_classifieds_remote_inbox', '' );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Classifieds Settings', 'fed-classifieds' ) . '</h1>';

    if ( $message ) {
        echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field( 'fed_classifieds_settings', 'fed_classifieds_settings_nonce' );
    echo '<table class="form-table">';
    echo '<tr><th scope="row">' . esc_html__( 'Remote inbox URL', 'fed-classifieds' ) . '</th><td><input type="url" name="fed_classifieds_remote_inbox" value="' . esc_attr( $current ) . '" class="regular-text" /></td></tr>';
    echo '</table>';
    submit_button( __( 'Save Changes', 'fed-classifieds' ), 'primary', 'fed_classifieds_settings_save' );
    echo '</form>';
    echo '</div>';
}

/**
 * Shortcode for frontend listing submission form.
 */
add_shortcode( 'fed_classifieds_form', 'fed_classifieds_form_shortcode' );

/**
 * Render and process the listing submission form.
 *
 * @return string
 */
function fed_classifieds_form_shortcode() {
    $success = false;
    $error   = false;

    if ( isset( $_POST['fed_classifieds_submit'] ) && isset( $_POST['fed_classifieds_nonce'] ) && wp_verify_nonce( $_POST['fed_classifieds_nonce'], 'fed_classifieds_new_listing' ) ) {
        $title   = isset( $_POST['listing_title'] ) ? sanitize_text_field( wp_unslash( $_POST['listing_title'] ) ) : '';
        $content = isset( $_POST['listing_content'] ) ? wp_kses_post( wp_unslash( $_POST['listing_content'] ) ) : '';
        $type    = isset( $_POST['listing_type'] ) ? sanitize_text_field( wp_unslash( $_POST['listing_type'] ) ) : '';
        $cat_id  = isset( $_POST['listing_category'] ) ? (int) $_POST['listing_category'] : 0;

        $post_id = wp_insert_post( [
            'post_type'   => 'listing',
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_content'=> $content,
            'tax_input'   => [ 'category' => [ $cat_id ] ],
        ], true );

        if ( ! is_wp_error( $post_id ) ) {
            if ( $type ) {
                update_post_meta( $post_id, '_listing_type', $type );
            }

            $remote = get_option( 'fed_classifieds_remote_inbox', '' );
            if ( $remote ) {
                $cat_name   = $cat_id ? get_term_field( 'name', $cat_id, 'category' ) : '';
                $object_id  = get_permalink( $post_id );
                $object     = [
                    'id'          => $object_id,
                    'type'        => 'Note',
                    'name'        => $title,
                    'content'     => $content,
                    'url'         => $object_id,
                    'published'   => gmdate( 'c' ),
                    'attributedTo'=> home_url(),
                    'category'    => $cat_name,
                    'listingType' => $type,
                ];
                $payload = [
                    '@context' => 'https://www.w3.org/ns/activitystreams',
                    'type'     => 'Create',
                    'actor'    => home_url(),
                    'to'       => [ $remote ],
                    'object'   => $object,
                ];
                wp_remote_post( $remote, [
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'body'    => wp_json_encode( $payload ),
                    'timeout' => 15,
                ] );
            }

            $success = true;
        } else {
            $error = true;
        }
    }

    wp_enqueue_style( 'fed-classifieds', plugin_dir_url( __FILE__ ) . 'assets/css/fed-classifieds.css', [], '0.1.0' );

    $cats = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] );

    ob_start();

    if ( $success ) {
        echo '<p class="fed-classifieds-success">' . esc_html__( 'Listing submitted.', 'fed-classifieds' ) . '</p>';
    } elseif ( $error ) {
        echo '<p class="fed-classifieds-error">' . esc_html__( 'Could not submit listing.', 'fed-classifieds' ) . '</p>';
    }

    echo '<form method="post" class="fed-classifieds-form">';
    wp_nonce_field( 'fed_classifieds_new_listing', 'fed_classifieds_nonce' );
    echo '<p><label for="listing_title">' . esc_html__( 'Title', 'fed-classifieds' ) . '</label><br />';
    echo '<input type="text" id="listing_title" name="listing_title" required /></p>';

    echo '<p><label for="listing_content">' . esc_html__( 'Description', 'fed-classifieds' ) . '</label><br />';
    echo '<textarea id="listing_content" name="listing_content" rows="5" required></textarea></p>';

    echo '<p><label for="listing_type">' . esc_html__( 'Typ', 'fed-classifieds' ) . '</label><br />';
    echo '<select id="listing_type" name="listing_type">';
    echo '<option value="Angebot">' . esc_html__( 'Angebot', 'fed-classifieds' ) . '</option>';
    echo '<option value="Gesuch">' . esc_html__( 'Gesuch', 'fed-classifieds' ) . '</option>';
    echo '</select></p>';

    echo '<p><label for="listing_category">' . esc_html__( 'Category', 'fed-classifieds' ) . '</label><br />';
    echo '<select id="listing_category" name="listing_category">';
    foreach ( $cats as $cat ) {
        echo '<option value="' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . '</option>';
    }
    echo '</select></p>';

    echo '<p><input type="submit" name="fed_classifieds_submit" value="' . esc_attr__( 'Submit', 'fed-classifieds' ) . '" /></p>';
    echo '</form>';

    return ob_get_clean();
}

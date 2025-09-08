<?php
/**
 * Plugin Name: Classyfeds (MVP)
 * Description: Custom post type "listing" with JSON-LD output and auto-expiration for a federated classifieds network.
 * Version: 0.1.2
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
 *
 * These objects are used to represent remote listings that arrive through
 * the federated inbox endpoint. The posts are not public but can be queried
 * so they may appear alongside local listings on the listings page template.
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
 *
 * Creates the "Classifieds" page if it does not exist and schedules the
 * daily expiration event.
 */
function classyfeds_activate() {
    $page_id = (int) get_option( 'classyfeds_page_id' );

    if ( $page_id && get_post( $page_id ) ) {
        // Page already exists and is stored in options.
    } else {
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

    // Insert default categories and optional subcategories similar to popular classifieds sites.
    $default_categories = [
        'Auto, Rad & Boot'              => [ 'Autos', 'Motorräder', 'Boote', 'Fahrräder' ],
        'Elektronik'                    => [ 'Computer', 'Handys & Telefone', 'TV, Video & Audio' ],
        'Haus & Garten'                 => [ 'Möbel & Wohnen', 'Haushaltsgeräte', 'Heimwerker & Bau' ],
        'Mode & Beauty'                 => [],
        'Freizeit, Hobby & Nachbarschaft' => [],
        'Familie, Kind & Baby'          => [],
        'Dienstleistungen'              => [],
        'Jobs'                          => [],
        'Immobilien'                    => [],
    ];
    foreach ( $default_categories as $parent => $children ) {
        $existing  = term_exists( $parent, 'listing_category' );
        $parent_id = 0;

        if ( $existing ) {
            $parent_id = is_array( $existing ) ? $existing['term_id'] : $existing;
        } else {
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
    if ( $form_page_id && get_post( $form_page_id ) ) {
        // Page already exists and is stored.
    } else {
        $page        = get_page_by_path( 'submit-listing' );
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

    // Flush rewrite rules to ensure custom routes are registered.
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

add_action( 'classyfeds_expire_event', function() {
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
    $page_id = (int) get_option( 'classyfeds_page_id' );
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
    $page_id = (int) get_option( 'classyfeds_page_id' );
    if ( $page_id && is_page( $page_id ) ) {
        wp_enqueue_style( 'classyfeds', plugin_dir_url( __FILE__ ) . 'assets/css/classyfeds.css', [], '0.1.0' );
        wp_enqueue_script( 'classyfeds', plugin_dir_url( __FILE__ ) . 'assets/js/classyfeds.js', [ 'jquery' ], '0.1.0', true );
    }
} );

/**
 * Register ActivityPub REST endpoints.
 */
add_action( 'rest_api_init', function() {
    register_rest_route(
        'classyfeds/v1',
        '/inbox',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'classyfeds_inbox_handler',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'classyfeds/v1',
        '/listings',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'classyfeds_listings_handler',
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
function classyfeds_inbox_handler( WP_REST_Request $request ) {
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
        $title = __( 'Remote ActivityPub Object', 'classyfeds' );
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
/**
 * Retrieve listings and ActivityPub objects as an ActivityStreams collection.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response.
 */
function classyfeds_listings_handler( WP_REST_Request $request ) {
    $query = new WP_Query(
        [
            'post_type'      => [ 'listing', 'ap_object' ],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]
    );

    $items = [];

    foreach ( $query->posts as $post ) {
        if ( 'ap_object' === $post->post_type ) {
            $data = json_decode( $post->post_content, true );
            if ( is_array( $data ) ) {
                // Force context to include required metadata definitions.
                $data['@context'] = [
                    'https://www.w3.org/ns/activitystreams',
                    [
                        'price'    => 'https://schema.org/price',
                        'location' => 'https://schema.org/location',
                        'category' => 'https://schema.org/category',
                    ],
                ];

                // Ensure visibility fields are present.
                $data['to'] = $data['to'] ?? 'https://www.w3.org/ns/activitystreams#Public';
                $data['cc'] = $data['cc'] ?? 'https://www.w3.org/ns/activitystreams#Public';

                // Skip objects missing mandatory metadata.
                if ( empty( $data['price'] ) || empty( $data['location'] ) || empty( $data['category'] ) ) {
                    continue;
                }

                $items[] = $data;
                continue;
            }
        }

        $cats     = wp_get_post_terms( $post->ID, 'listing_category', [ 'fields' => 'names' ] );
        $price    = get_post_meta( $post->ID, '_price', true );
        $location = get_post_meta( $post->ID, '_location', true );

        // Skip listings missing required metadata.
        if ( empty( $price ) || empty( $location ) || empty( $cats ) ) {
            continue;
        }

        $items[] = [
            '@context'     => [
                'https://www.w3.org/ns/activitystreams',
                [
                    'price'    => 'https://schema.org/price',
                    'location' => 'https://schema.org/location',
                    'category' => 'https://schema.org/category',
                ],
            ],
            'id'           => get_permalink( $post ),
            'type'         => 'Note',
            'name'         => get_the_title( $post ),
            'content'      => apply_filters( 'the_content', $post->post_content ),
            'url'          => get_permalink( $post ),
            'published'    => mysql2date( 'c', $post->post_date_gmt, false ),
            'attributedTo' => home_url(),
            'to'           => 'https://www.w3.org/ns/activitystreams#Public',
            'cc'           => 'https://www.w3.org/ns/activitystreams#Public',
            'price'        => $price,
            'location'     => $location,
            'category'     => $cats,
            'listingType'  => get_post_meta( $post->ID, '_listing_type', true ),
        ];
    }

    $collection = [
        '@context'     => 'https://www.w3.org/ns/activitystreams',
        'id'           => rest_url( 'classyfeds/v1/listings' ),
        'type'         => 'OrderedCollection',
        'totalItems'   => count( $items ),
        'orderedItems' => $items,
    ];

    return new WP_REST_Response( $collection );
}

add_shortcode( 'classyfeds_form', 'classyfeds_form_shortcode' );
// Backward compatibility.
add_shortcode( 'fed_classifieds_form', 'classyfeds_form_shortcode' );

/**
 * Shortcode handler for `[classyfeds_form]`.
 *
 * Displays a submission form that allows visitors to create new listings.
 * Submitted listings are stored locally and optionally forwarded to a
 * configurable ActivityPub inbox via the `classyfeds_remote_inbox`
 * option.
 *
 * @return string Form HTML.
 */
function classyfeds_form_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in to submit a listing.', 'classyfeds' ) . '</a></p>';
    }

    if ( ! current_user_can( 'publish_listings' ) ) {
        return '<p>' . esc_html__( 'You do not have permission to submit listings.', 'classyfeds' ) . '</p>';
    }
    $success = false;
    $error   = false;

    if ( isset( $_POST['classyfeds_submit'] ) ) {
        if ( ! isset( $_POST['classyfeds_nonce'] ) || ! wp_verify_nonce( $_POST['classyfeds_nonce'], 'classyfeds_new_listing' ) ) {
            $error = true;
        } else {
            $title    = isset( $_POST['listing_title'] ) ? sanitize_text_field( wp_unslash( $_POST['listing_title'] ) ) : '';
            $content  = isset( $_POST['listing_content'] ) ? wp_kses_post( wp_unslash( $_POST['listing_content'] ) ) : '';
            $type     = isset( $_POST['listing_type'] ) ? sanitize_text_field( wp_unslash( $_POST['listing_type'] ) ) : '';
            $cat      = isset( $_POST['listing_category'] ) ? absint( wp_unslash( $_POST['listing_category'] ) ) : 0;
            $price    = isset( $_POST['listing_price'] ) ? sanitize_text_field( wp_unslash( $_POST['listing_price'] ) ) : '';
            $location = isset( $_POST['listing_location'] ) ? sanitize_text_field( wp_unslash( $_POST['listing_location'] ) ) : '';
            $image_id = 0;

            if ( '' === $title || '' === $content || '' === $price || '' === $location ) {
                $error = true;
            } else {
                $post_id = wp_insert_post(
                    [
                        'post_type'    => 'listing',
                        'post_status'  => 'publish',
                        'post_title'   => $title,
                        'post_content' => $content,
                    ],
                    true
                );

                if ( ! is_wp_error( $post_id ) ) {
                    if ( $cat ) {
                        wp_set_post_terms( $post_id, [ $cat ], 'listing_category' );
                    }
                    if ( $type ) {
                        update_post_meta( $post_id, '_listing_type', $type );
                    }
                    if ( ! empty( $_FILES['listing_image']['name'] ) ) {
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';

                        $image_id = media_handle_upload( 'listing_image', $post_id );
                        if ( ! is_wp_error( $image_id ) ) {
                            set_post_thumbnail( $post_id, $image_id );
                        } else {
                            $image_id = 0;
                        }
                    }

                    update_post_meta( $post_id, '_price', $price );
                    update_post_meta( $post_id, '_location', $location );

                    $remote = get_option( 'classyfeds_remote_inbox' );
                    if ( $remote ) {
                        $payload = [
                            '@context' => 'https://www.w3.org/ns/activitystreams',
                            'type'     => 'Create',
                            'actor'    => home_url(),
                            'object'   => [
                                'type'        => 'Note',
                                'name'        => $title,
                                'content'     => $content,
                                'url'         => get_permalink( $post_id ),
                                'category'    => array_values( wp_get_post_terms( $post_id, 'listing_category', [ 'fields' => 'names' ] ) ),
                                'listingType' => $type,
                                'price'       => $price,
                                'location'    => $location,
                            ],
                        ];
                        if ( $image_id ) {
                            $payload['object']['image'] = wp_get_attachment_url( $image_id );
                        }
                        wp_remote_post(
                            $remote,
                            [
                                'headers' => [ 'Content-Type' => 'application/activity+json' ],
                                'body'    => wp_json_encode( $payload ),
                                'timeout' => 15,
                            ]
                        );
                    }

                    $success = true;
                } else {
                    $error = true;
                }
            }
        }
    }

    wp_enqueue_style( 'classyfeds', plugin_dir_url( __FILE__ ) . 'assets/css/classyfeds.css', [], '0.1.0' );

    $cat_slugs = get_option( 'classyfeds_filter_categories', '' );
    if ( $cat_slugs ) {
        $slug_list = array_filter( array_map( 'trim', explode( ',', $cat_slugs ) ) );
        if ( $slug_list ) {
            $cats = get_terms(
                [
                    'taxonomy'   => 'listing_category',
                    'slug'       => $slug_list,
                    'hide_empty' => false,
                ]
            );
        } else {
            $cats = get_terms( [ 'taxonomy' => 'listing_category', 'hide_empty' => false ] );
        }
    } else {
        $cats = get_terms( [ 'taxonomy' => 'listing_category', 'hide_empty' => false ] );
    }

    ob_start();

    if ( $success ) {
        echo '<p class="classyfeds-success">' . esc_html__( 'Listing submitted.', 'classyfeds' ) . '</p>';
    } elseif ( $error ) {
        echo '<p class="classyfeds-error">' . esc_html__( 'Could not submit listing.', 'classyfeds' ) . '</p>';
    }

    echo '<form method="post" class="classyfeds-form" enctype="multipart/form-data">';
    wp_nonce_field( 'classyfeds_new_listing', 'classyfeds_nonce' );

    echo '<div class="wp-block"><label for="listing_title">' . esc_html__( 'Title', 'classyfeds' ) . '</label>';
    echo '<input type="text" id="listing_title" name="listing_title" class="regular-text" placeholder="' . esc_attr__( 'Short title', 'classyfeds' ) . '" required /></div>';

    echo '<div class="wp-block"><label for="listing_content">' . esc_html__( 'Description', 'classyfeds' ) . '</label>';
    $editor_settings = [
        'textarea_name' => 'listing_content',
        'textarea_rows' => 5,
        'media_buttons' => false,
    ];
    $editor_content = '';
    ob_start();
    wp_editor( '', 'listing_content', $editor_settings );
    $editor_content = ob_get_clean();
    // Add required attribute to the editor textarea.
    $editor_content = str_replace( '<textarea', '<textarea required', $editor_content );
    echo $editor_content;
    echo '</div>';

    echo '<div class="wp-block"><label for="listing_type">' . esc_html__( 'Typ', 'classyfeds' ) . '</label>';
    echo '<select id="listing_type" name="listing_type" class="regular-text">';
    echo '<option value="Angebot">' . esc_html__( 'Angebot', 'classyfeds' ) . '</option>';
    echo '<option value="Gesuch">' . esc_html__( 'Gesuch', 'classyfeds' ) . '</option>';
    echo '</select></div>';

    echo '<div class="wp-block"><label for="listing_category">' . esc_html__( 'Category', 'classyfeds' ) . '</label>';
    $cat_args = [
        'taxonomy'   => 'listing_category',
        'hide_empty' => false,
        'name'       => 'listing_category',
        'id'         => 'listing_category',
        'class'      => 'regular-text',
        'echo'       => 0,
    ];
    if ( ! empty( $cats ) ) {
        $cat_args['include'] = wp_list_pluck( $cats, 'term_id' );
    }
    echo wp_dropdown_categories( $cat_args );
    echo '</div>';

    echo '<div class="wp-block"><label for="listing_image">' . esc_html__( 'Image', 'classyfeds' ) . '</label>';
    echo '<input type="file" id="listing_image" name="listing_image" accept="image/*" /></div>';

    echo '<div class="wp-block"><label for="listing_price">' . esc_html__( 'Price', 'classyfeds' ) . '</label>';
    echo '<input type="number" id="listing_price" name="listing_price" class="regular-text" step="0.01" placeholder="' . esc_attr__( '0.00', 'classyfeds' ) . '" required /></div>';

    echo '<div class="wp-block"><label for="listing_location">' . esc_html__( 'Location', 'classyfeds' ) . '</label>';
    echo '<input type="text" id="listing_location" name="listing_location" class="regular-text" required /></div>';

    echo '<div class="wp-block"><input type="submit" name="classyfeds_submit" class="button button-primary" value="' . esc_attr__( 'Submit', 'classyfeds' ) . '" /></div>';
    echo '</form>';

    return ob_get_clean();
}

<?php
/**
 * Plugin Name: Classyfeds (MVP)
 * Description: Custom post type "listing" with JSON-LD output and auto-expiration for a federated classifieds network.
 * Version: 0.1.4
 * Author: thomi@etik.com + amis
 */

namespace ClassyFeds;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register the "listing" post type and taxonomy.
 */
function register_listing_post_type() {
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
        'register_meta_box_cb' => __NAMESPACE__ . '\\register_listing_metaboxes',
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
            'capabilities' => [
                'manage_terms' => 'manage_listing_categories',
                'edit_terms'   => 'manage_listing_categories',
                'delete_terms' => 'manage_listing_categories',
                'assign_terms' => 'publish_listings',
            ],
        ]
    );
}

/**
 * Register custom meta boxes for listings.
 */
function register_listing_metaboxes() {
    remove_meta_box( 'listing_categorydiv', 'listing', 'side' );

    add_meta_box(
        'classyfeds_listing_categorydiv',
        __( 'Listing Categories', 'classyfeds' ),
        __NAMESPACE__ . '\\listing_category_meta_box',
        'listing',
        'side'
    );
}

/**
 * Output category checklist meta box.
 */
function listing_category_meta_box( $post ) {
    wp_terms_checklist( $post->ID, [ 'taxonomy' => 'listing_category' ] );
}

/**
 * Add "Typ" dropdown to editor.
 */
function add_listing_type_meta_box() {
    add_meta_box(
        'listing_type',
        __( 'Typ', 'classyfeds' ),
        __NAMESPACE__ . '\\render_listing_type_meta_box',
        'listing',
        'side'
    );
}

/**
 * Render type dropdown.
 */
function render_listing_type_meta_box( $post ) {
    $value = get_post_meta( $post->ID, '_listing_type', true );
    wp_nonce_field( 'save_listing_type', 'listing_type_nonce' );
    echo '<select name="listing_type" id="listing_type" style="width:100%">';
    echo '<option value="Angebot"' . selected( $value, 'Angebot', false ) . '>' . esc_html__( 'Angebot', 'classyfeds' ) . '</option>';
    echo '<option value="Gesuch"' . selected( $value, 'Gesuch', false ) . '>' . esc_html__( 'Gesuch', 'classyfeds' ) . '</option>';
    echo '</select>';
}

/**
 * Register post type for incoming ActivityPub objects.
 */
function register_ap_object_post_type() {
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
}

/**
 * Register custom post status "expired".
 */
function register_expired_status() {
    register_post_status( 'expired', [
        'label'                     => _x( 'Expired', 'post', 'classyfeds' ),
        'public'                    => false,
        'internal'                  => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'classyfeds' ),
    ] );
}

/**
 * Save listing metadata.
 */
function save_listing_meta( $post_id, $post, $update ) {
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
}

/**
 * Migrate legacy option names.
 */
function migrate_options() {
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

/**
 * Register settings page under Options.
 */
function register_settings_menu() {
    add_options_page(
        __( 'Classifieds', 'classyfeds' ),
        __( 'Classifieds', 'classyfeds' ),
        'manage_options',
        'classyfeds',
        __NAMESPACE__ . '\\settings_page'
    );
}

/**
 * Render settings page for selecting publish roles.
 */
function settings_page() {
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
                $role->remove_cap( 'manage_listing_categories' );
            } else {
                $role->remove_cap( 'publish_listings' );
                $role->remove_cap( 'manage_listing_categories' );
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

/**
 * Cron: expire listings.
 */
function expire_listings() {
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
}

/**
 * Output JSON-LD structured data for listings.
 */
function output_json_ld() {
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
}

/**
 * Load template for Classifieds page.
 */
function template_include( $template ) {
    $page_id = (int) get_option( 'classyfeds_page_id' );
    if ( $page_id && is_page( $page_id ) ) {
        $new_template = plugin_dir_path( __FILE__ ) . 'templates/listings-page.php';
        if ( file_exists( $new_template ) ) {
            return $new_template;
        }
    }
    return $template;
}

/**
 * Enqueue frontend assets.
 */
function enqueue_assets() {
    $page_id = (int) get_option( 'classyfeds_page_id' );
    if ( $page_id && is_page( $page_id ) ) {
        wp_enqueue_style( 'classyfeds', plugin_dir_url( __FILE__ ) . 'assets/css/classyfeds.css', [], '0.1.0' );
        wp_enqueue_script( 'classyfeds', plugin_dir_url( __FILE__ ) . 'assets/js/classyfeds.js', [ 'jquery' ], '0.1.0', true );
    }
}

/**
 * Register REST API endpoints.
 */
function register_rest_routes() {
    register_rest_route(
        'classyfeds/v1',
        '/inbox',
        [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => __NAMESPACE__ . '\\inbox_handler',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'classyfeds/v1',
        '/listings',
        [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => __NAMESPACE__ . '\\listings_handler',
            'permission_callback' => '__return_true',
        ]
    );
}

/**
 * Handle incoming ActivityPub objects.
 */
function inbox_handler( \WP_REST_Request $request ) {
    $activity = $request->get_json_params();

    if ( empty( $activity ) || ! is_array( $activity ) ) {
        return new \WP_REST_Response( [ 'error' => 'Invalid object' ], 400 );
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
        return new \WP_REST_Response( [ 'error' => 'Could not store object' ], 500 );
    }

    return new \WP_REST_Response( [ 'stored' => $post_id ], 202 );
}

/**
 * Retrieve listings and ActivityPub objects as ActivityStreams collection.
 */
function listings_handler( \WP_REST_Request $request ) {
    $query = new \WP_Query(
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
                $data['@context'] = [
                    'https://www.w3.org/ns/activitystreams',
                    [
                        'price'    => 'https://schema.org/price',
                        'shipping' => 'https://schema.org/shippingDetails',
                        'category' => 'https://schema.org/category',
                    ],
                ];
                $data['to'] = $data['to'] ?? 'https://www.w3.org/ns/activitystreams#Public';
                $data['cc'] = $data['cc'] ?? 'https://www.w3.org/ns/activitystreams#Public';

                if ( empty( $data['price'] ) || empty( $data['shipping'] ) || empty( $data['category'] ) ) {
                    continue;
                }

                $items[] = $data;
                continue;
            }
        }

        $cats    = wp_get_post_terms( $post->ID, 'listing_category', [ 'fields' => 'names' ] );
        $price   = get_post_meta( $post->ID, '_price', true );
        $shipping = get_post_meta( $post->ID, '_shipping', true );

        if ( empty( $price ) || empty( $shipping ) || empty( $cats ) ) {
            continue;
        }

        $items[] = [
            '@context'     => [
                'https://www.w3.org/ns/activitystreams',
                [
                    'price'    => 'https://schema.org/price',
                    'shipping' => 'https://schema.org/shippingDetails',
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
            'shipping'     => $shipping,
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

    return new \WP_REST_Response( $collection );
}

/**
 * Upload image and attach to listing.
 */
function upload_listing_image( $image_path, $post_id ) {
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

/**
 * Send listing to remote ActivityPub inbox.
 */
function notify_remote( $post_id, $title, $content, $cat_names, $price, $shipping, $image_id = 0 ) {
    $remote = get_option( 'classyfeds_remote_inbox' );
    if ( ! $remote ) {
        return;
    }

    $payload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type'     => 'Create',
        'actor'    => home_url(),
        'object'   => [
            'type'     => 'Note',
            'name'     => $title,
            'content'  => $content,
            'url'      => get_permalink( $post_id ),
            'category' => $cat_names,
            'price'    => $price,
            'shipping' => $shipping,
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

/**
 * Process form submission.
 */
function process_listing_submission( array $data, array $files ) {
    $title      = isset( $data['listing_title'] ) ? sanitize_text_field( $data['listing_title'] ) : '';
    $content    = isset( $data['listing_description'] ) ? wp_kses_post( $data['listing_description'] ) : '';
    $price      = isset( $data['listing_price'] ) ? sanitize_text_field( $data['listing_price'] ) : '';
    $shipping   = isset( $data['listing_shipping'] ) ? sanitize_text_field( $data['listing_shipping'] ) : '';
    $cat_names  = isset( $data['listing_category'] ) ? (array) $data['listing_category'] : [];
    $image_path = isset( $files['listing_image'][0] ) ? $files['listing_image'][0] : '';

    if ( '' === $title || '' === $content || '' === $price || '' === $shipping || empty( $cat_names ) ) {
        return new \WP_Error( 'classyfeds_incomplete', __( 'Required fields are missing.', 'classyfeds' ) );
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

    $image_id = upload_listing_image( $image_path, $post_id );

    update_post_meta( $post_id, '_price', $price );
    update_post_meta( $post_id, '_shipping', $shipping );

    notify_remote( $post_id, $title, $content, $cat_names, $price, $shipping, $image_id );

    return $post_id;
}

/**
 * Handle Contact Form 7 submissions.
 */
function cf7_handle_submission( $contact_form ) {
    $expected_form_id = (int) get_option( 'classyfeds_cf7_form_id' );
    if ( $contact_form->id() !== $expected_form_id ) {
        return;
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'publish_listings' ) ) {
        return;
    }

    $submission = \WPCF7_Submission::get_instance();
    if ( ! $submission ) {
        return;
    }

    process_listing_submission( $submission->get_posted_data(), $submission->uploaded_files() );
}

/**
 * Main plugin class.
 */
final class Plugin {
    public function register() {
        add_action( 'init', __NAMESPACE__ . '\\register_listing_post_type' );
        add_action( 'add_meta_boxes', __NAMESPACE__ . '\\add_listing_type_meta_box' );
        add_action( 'init', __NAMESPACE__ . '\\register_ap_object_post_type' );
        add_action( 'init', __NAMESPACE__ . '\\register_expired_status' );
        add_action( 'save_post_listing', __NAMESPACE__ . '\\save_listing_meta', 10, 3 );
        add_action( 'plugins_loaded', __NAMESPACE__ . '\\migrate_options' );
        add_action( 'admin_menu', __NAMESPACE__ . '\\register_settings_menu' );
        add_action( 'classyfeds_expire_event', __NAMESPACE__ . '\\expire_listings' );
        add_action( 'wp_head', __NAMESPACE__ . '\\output_json_ld' );
        add_filter( 'template_include', __NAMESPACE__ . '\\template_include' );
        add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );
        add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_routes' );
        if ( class_exists( '\\WPCF7' ) ) {
            add_action( 'wpcf7_mail_sent', __NAMESPACE__ . '\\cf7_handle_submission' );
        }
    }

    public static function activate() {
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

        // Insert default categories.
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

        // Create or update Contact Form 7 form.
        $cf7_form_id = (int) get_option( 'classyfeds_cf7_form_id' );
        if ( class_exists( '\\WPCF7_ContactForm' ) && ( ! $cf7_form_id || ! get_post( $cf7_form_id ) ) ) {
            $cats = get_terms([
                'taxonomy'   => 'listing_category',
                'hide_empty' => false,
                'fields'     => 'names',
            ]);
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
            $form = \WPCF7_ContactForm::get_template( [ 'title' => __( 'Submit Listing', 'classyfeds' ) ] );
            $form->set_properties( [ 'form' => $form_markup ] );
            $cf7_form_id = $form->save();
            if ( $cf7_form_id ) {
                update_option( 'classyfeds_cf7_form_id', $cf7_form_id );
            }
        }

        // Ensure submission page.
        $form_page_id = (int) get_option( 'classyfeds_form_page_id' );
        $shortcode    = $cf7_form_id ? '[contact-form-7 id="' . $cf7_form_id . '"]' : '';
        if ( ! ( $form_page_id && get_post( $form_page_id ) ) ) {
            $page         = get_page_by_path( 'submit-listing' );
            $form_page_id = $page ? $page->ID : 0;

            if ( ! $form_page_id ) {
                $form_page_id = wp_insert_post([
                    'post_title'   => __( 'Submit Listing', 'classyfeds' ),
                    'post_name'    => 'submit-listing',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_content' => $shortcode,
                ]);
            }

            if ( $form_page_id ) {
                update_option( 'classyfeds_form_page_id', $form_page_id );
            }
        } elseif ( $shortcode ) {
            wp_update_post( [ 'ID' => $form_page_id, 'post_content' => $shortcode ] );
        }

        // Capabilities and roles.
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

        flush_rewrite_rules();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'classyfeds_expire_event' );
        flush_rewrite_rules();
    }
}

$plugin = new Plugin();
$plugin->register();
register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Plugin::class, 'deactivate' ] );

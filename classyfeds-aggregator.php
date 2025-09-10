<?php
/**
 * Plugin Name: Classyfeds Aggregator (MVP)
 * Description: Standalone aggregator page for federated classifieds.
 * Version: 0.1.0
 * Author: thomi@etik.com + amis
 */

namespace ClassyFeds;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register custom post type for ActivityPub objects and listing taxonomy.
 */
function register_post_type_and_taxonomy() {
    register_post_type( 'ap_object', [
        'labels' => [
            'name'          => __( 'ActivityPub Objects', 'classyfeds-aggregator' ),
            'singular_name' => __( 'ActivityPub Object', 'classyfeds-aggregator' ),
        ],
        'public'       => false,
        'show_ui'      => false,
        'show_in_rest' => false,
        'supports'     => [ 'title', 'editor' ],
    ] );

    register_taxonomy(
        'listing_category',
        [ 'listing', 'ap_object' ],
        [
            'labels'       => [
                'name'          => __( 'Listing Categories', 'classyfeds-aggregator' ),
                'singular_name' => __( 'Listing Category', 'classyfeds-aggregator' ),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ]
    );
}

/**
 * Plugin activation callback + bootstrap hooks.
 */
class Aggregator {
    public function register() {
        add_action( 'init', __NAMESPACE__ . '\\register_post_type_and_taxonomy' );
        add_action( 'admin_menu', __NAMESPACE__ . '\\register_settings_menu' );
        add_filter( 'template_include', __NAMESPACE__ . '\\template_include' );
        add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );
        add_shortcode( 'classyfeds_listings', __NAMESPACE__ . '\\get_listings_html' );
        add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_routes' );
    }

    public static function activate() {
        $page_id = (int) get_option( 'classyfeds_page_id' );

        if ( $page_id && get_post( $page_id ) ) {
            // Page already exists, rien à faire.
        } else {
            $page    = get_page_by_path( 'classifieds' );
            $page_id = $page ? $page->ID : 0;

            if ( ! $page_id ) {
                $page_id = wp_insert_post( [
                    'post_title'  => __( 'Classifieds', 'classyfeds-aggregator' ),
                    'post_name'   => 'classifieds',
                    'post_status' => 'publish',
                    'post_type'   => 'page',
                ] );
            }

            if ( $page_id ) {
                update_option( 'classyfeds_page_id', $page_id );
            }
        }

        flush_rewrite_rules();
    }
}

/**
 * Register settings menu.
 */
function register_settings_menu() {
    add_menu_page(
        __( 'Classifieds Aggregator', 'classyfeds-aggregator' ),
        __( 'Classifieds', 'classyfeds-aggregator' ),
        'manage_options',
        'classyfeds-aggregator',
        __NAMESPACE__ . '\\settings_page',
        'dashicons-megaphone',
        25
    );
}

/**
 * Render settings page.
 */
function settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $message           = '';
    $current_page      = (int) get_option( 'classyfeds_page_id' );
    $remote_inbox      = get_option( 'classyfeds_remote_inbox', '' );
    $filter_posts      = (int) get_option( 'classyfeds_filter_posts', 0 );

    if ( isset( $_POST['classyfeds_save'] ) && check_admin_referer( 'classyfeds_save_settings', 'classyfeds_nonce' ) ) {
        $page_id = isset( $_POST['classyfeds_page_id'] ) ? absint( wp_unslash( $_POST['classyfeds_page_id'] ) ) : 0;
        $slug    = isset( $_POST['classyfeds_slug'] ) ? sanitize_title( wp_unslash( $_POST['classyfeds_slug'] ) ) : '';

        if ( $slug ) {
            $page = get_page_by_path( $slug );
            if ( $page ) {
                $page_id = $page->ID;
            } else {
                $page_id = wp_insert_post(
                    [
                        'post_title'  => ucwords( str_replace( '-', ' ', $slug ) ),
                        'post_name'   => $slug,
                        'post_status' => 'publish',
                        'post_type'   => 'page',
                    ]
                );
            }
        }

        $remote_inbox = isset( $_POST['classyfeds_remote_inbox'] ) ? esc_url_raw( wp_unslash( $_POST['classyfeds_remote_inbox'] ) ) : '';
        $filter_posts = isset( $_POST['classyfeds_filter_posts'] ) ? absint( wp_unslash( $_POST['classyfeds_filter_posts'] ) ) : 0;

        // Add new category if provided.
        if ( ! empty( $_POST['classyfeds_new_cat_name'] ) ) {
            $new_cat_name   = sanitize_text_field( wp_unslash( $_POST['classyfeds_new_cat_name'] ) );
            $new_cat_parent = isset( $_POST['classyfeds_new_cat_parent'] ) ? absint( $_POST['classyfeds_new_cat_parent'] ) : 0;
            wp_insert_term( $new_cat_name, 'listing_category', [ 'parent' => $new_cat_parent ] );
        }

        // Rename existing category if requested.
        if ( ! empty( $_POST['classyfeds_edit_cat'] ) && ! empty( $_POST['classyfeds_edit_cat_name'] ) ) {
            $edit_cat_id     = absint( $_POST['classyfeds_edit_cat'] );
            $edit_cat_name   = sanitize_text_field( wp_unslash( $_POST['classyfeds_edit_cat_name'] ) );
            $edit_cat_parent = isset( $_POST['classyfeds_edit_cat_parent'] ) ? absint( $_POST['classyfeds_edit_cat_parent'] ) : 0;
            wp_update_term( $edit_cat_id, 'listing_category', [ 'name' => $edit_cat_name, 'parent' => $edit_cat_parent ] );
        }

        // Refresh stored category slugs.
        $terms             = get_terms( 'listing_category', [ 'hide_empty' => false ] );
        $filter_categories = join( ',', wp_list_pluck( $terms, 'slug' ) );

        update_option( 'classyfeds_page_id', $page_id );
        update_option( 'classyfeds_remote_inbox', $remote_inbox );
        update_option( 'classyfeds_filter_categories', $filter_categories );
        update_option( 'classyfeds_filter_posts', $filter_posts );

        $current_page = $page_id;
        $message      = __( 'Settings saved.', 'classyfeds-aggregator' );
    }

    $page_link = $current_page ? get_permalink( $current_page ) : '';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Classifieds Aggregator', 'classyfeds-aggregator' ) . '</h1>';

    if ( $message ) {
        echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field( 'classyfeds_save_settings', 'classyfeds_nonce' );

    echo '<table class="form-table">';
    echo '<tr><th scope="row">' . esc_html__( 'Page', 'classyfeds-aggregator' ) . '</th><td>';
    wp_dropdown_pages(
        [
            'name'             => 'classyfeds_page_id',
            'selected'         => $current_page,
            'show_option_none' => __( '— Select —', 'classyfeds-aggregator' ),
        ]
    );
    echo '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'or Slug', 'classyfeds-aggregator' ) . '</th><td><input type="text" name="classyfeds_slug" value="" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'Remote Inbox URL', 'classyfeds-aggregator' ) . '</th><td><input type="url" name="classyfeds_remote_inbox" value="' . esc_attr( $remote_inbox ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'Listing Categories', 'classyfeds-aggregator' ) . '</th><td>';

    $terms = get_terms( 'listing_category', [ 'hide_empty' => false ] );
    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        echo '<ul>';
        foreach ( $terms as $term ) {
            echo '<li>' . esc_html( $term->name ) . ' (' . esc_html( $term->slug ) . ')</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>' . esc_html__( 'No categories found.', 'classyfeds-aggregator' ) . '</p>';
    }

    echo '<h4>' . esc_html__( 'Add Category', 'classyfeds-aggregator' ) . '</h4><p><input type="text" name="classyfeds_new_cat_name" value="" class="regular-text" /> ';
    wp_dropdown_categories(
        [
            'taxonomy'         => 'listing_category',
            'name'             => 'classyfeds_new_cat_parent',
            'show_option_none' => __( '— Parent —', 'classyfeds-aggregator' ),
            'hide_empty'       => false,
            'selected'         => 0,
        ]
    );
    echo '</p>';

    echo '<h4>' . esc_html__( 'Rename Category', 'classyfeds-aggregator' ) . '</h4>';
    wp_dropdown_categories(
        [
            'taxonomy'         => 'listing_category',
            'name'             => 'classyfeds_edit_cat',
            'show_option_none' => __( '— Select —', 'classyfeds-aggregator' ),
            'hide_empty'       => false,
        ]
    );
    echo '<p><input type="text" name="classyfeds_edit_cat_name" value="" class="regular-text" /> ';
    wp_dropdown_categories(
        [
            'taxonomy'         => 'listing_category',
            'name'             => 'classyfeds_edit_cat_parent',
            'show_option_none' => __( '— Parent —', 'classyfeds-aggregator' ),
            'hide_empty'       => false,
            'selected'         => 0,
        ]
    );
    echo '</p>';

    echo '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'Posts Per Page', 'classyfeds-aggregator' ) . '</th><td><input type="number" name="classyfeds_filter_posts" value="' . esc_attr( $filter_posts ) . '" class="small-text" min="0" /></td></tr>';
    echo '</table>';

    submit_button( __( 'Save Changes', 'classyfeds-aggregator' ), 'primary', 'classyfeds_save' );

    if ( $page_link ) {
        echo '<p><a class="button" href="' . esc_url( $page_link ) . '" target="_blank">' . esc_html__( 'Open Aggregator Page', 'classyfeds-aggregator' ) . '</a></p>';
    }

    echo '</form>';
    echo '</div>';
}

/**
 * Template loader for aggregator page.
 */
function template_include( $template ) {
    $page_id = (int) get_option( 'classyfeds_page_id' );
    if ( $page_id && (int) get_queried_object_id() === $page_id ) {
        $new_template = plugin_dir_path( __FILE__ ) . 'templates/aggregator-page.php';
        if ( file_exists( $new_template ) ) {
            return $new_template;
        }
    }
    return $template;
}

/**
 * Enqueue frontend assets for the Classifieds page.
 */
function enqueue_assets() {
    $page_id = (int) get_option( 'classyfeds_page_id' );
    if ( $page_id && (int) get_queried_object_id() === $page_id ) {
        wp_enqueue_style( 'classyfeds', plugin_dir_url( __FILE__ ) . 'assets/css/classyfeds.css', [], '0.1.0' );
        wp_enqueue_script( 'classyfeds', plugin_dir_url( __FILE__ ) . 'assets/js/classyfeds.js', [ 'jquery' ], '0.1.0', true );

        wp_localize_script(
            'classyfeds',
            'classyfedsOptions',
            [
                'remoteInbox' => get_option( 'classyfeds_remote_inbox', '' ),
                'categories'  => get_option( 'classyfeds_filter_categories', '' ),
                'posts'       => (int) get_option( 'classyfeds_filter_posts', 0 ),
            ]
        );
    }
}

/**
 * Render aggregated listings HTML.
 */
function get_listings_html() {
    $post_types = [ 'ap_object' ];
    if ( post_type_exists( 'listing' ) ) {
        $post_types[] = 'listing';
    }

    $query = new \WP_Query(
        [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]
    );

    ob_start();
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $data = ( 'ap_object' === get_post_type() ) ? json_decode( get_the_content(), true ) : [];
            $link = '';
            if ( $data ) {
                if ( isset( $data['url'] ) ) {
                    $link = $data['url'];
                } elseif ( isset( $data['id'] ) ) {
                    $link = $data['id'];
                }
            }
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class( 'classyfeds-listing' ); ?>>
                <header class="entry-header">
                    <?php if ( 'listing' === get_post_type() ) : ?>
                        <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php elseif ( $link ) : ?>
                        <h2 class="entry-title"><a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="nofollow noopener"><?php the_title(); ?></a></h2>
                    <?php else : ?>
                        <h2 class="entry-title"><?php the_title(); ?></h2>
                    <?php endif; ?>
                </header>
                <div class="entry-content">
                    <?php
                    if ( 'listing' === get_post_type() ) {
                        the_excerpt();
                    } else {
                        if ( isset( $data['content'] ) ) {
                            echo wp_kses_post( wpautop( $data['content'] ) );
                        } elseif ( isset( $data['summary'] ) ) {
                            echo esc_html( $data['summary'] );
                        }
                    }
                    ?>
                </div>
            </article>
            <?php
        }
        wp_reset_postdata();
    } else {
        echo '<p>' . esc_html__( 'No listings found.', 'classyfeds-aggregator' ) . '</p>';
    }

    return ob_get_clean();
}

/**
 * Register REST API routes.
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
        $title = __( 'Remote ActivityPub Object', 'classyfeds-aggregator' );
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
 * Output aggregated listings as ActivityStreams collection.
 */
function listings_handler() {
    $remote_inbox      = get_option( 'classyfeds_remote_inbox', '' );
    $filter_categories = get_option( 'classyfeds_filter_categories', '' );
    $filter_posts      = (int) get_option( 'classyfeds_filter_posts', 0 );

    $args = [
        'post_type'      => [ 'listing', 'ap_object' ],
        'post_status'    => 'publish',
        'posts_per_page' => $filter_posts > 0 ? $filter_posts : -1,
    ];

    $cats = [];
    if ( $filter_categories ) {
        $cats = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', $filter_categories ) ) ) );
        if ( $cats ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'listing_category',
                    'field'    => 'slug',
                    'terms'    => $cats,
                ],
            ];
        }
    }

    $query = new \WP_Query( $args );

    $items = [];

    foreach ( $query->posts as $post ) {
        if ( 'ap_object' === $post->post_type ) {
            $data = json_decode( $post->post_content, true );
            if ( is_array( $data ) ) {
                if ( empty( $data['@context'] ) ) {
                    $data['@context'] = 'https://www.w3.org/ns/activitystreams';
                }
                $items[] = $data;
                continue;
            }
        }

        $post_cats = wp_get_post_terms( $post->ID, 'listing_category', [ 'fields' => 'names' ] );
        $items[] = [
            '@context'     => 'https://www.w3.org/ns/activitystreams',
            'id'           => get_permalink( $post ),
            'type'         => 'Note',
            'name'         => get_the_title( $post ),
            'content'      => apply_filters( 'the_content', $post->post_content ),
            'url'          => get_permalink( $post ),
            'published'    => mysql2date( 'c', $post->post_date_gmt, false ),
            'attributedTo' => home_url(),
            'category'     => $post_cats,
            'listingType'  => get_post_meta( $post->ID, '_listing_type', true

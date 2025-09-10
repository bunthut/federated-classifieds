<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'template_include', function( $template ) {
    $page_id = (int) get_option( 'classyfeds_page_id' );
    if ( $page_id && (int) get_queried_object_id() === $page_id ) {
        $new_template = plugin_dir_path( __FILE__ ) . '../templates/aggregator-page.php';
        if ( file_exists( $new_template ) ) {
            return $new_template;
        }
    }
    return $template;
} );

/**
 * Render aggregated listings HTML for template and shortcode.
 *
 * @return string HTML output.
 */
function classyfeds_aggregator_get_listings_html() {
    $post_types = [ 'ap_object' ];
    if ( post_type_exists( 'listing' ) ) {
        $post_types[] = 'listing';
    }

    $query = new WP_Query(
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

add_shortcode( 'classyfeds_listings', 'classyfeds_aggregator_get_listings_html' );

<?php
/**
 * Template for the standalone Classifieds aggregator page.
 *
 * @package Classyfeds_Aggregator
 */

get_header(); ?>
<div id="primary" class="content-area">
    <main id="main" class="site-main classyfeds-listings">
        <?php
        $post_types = [ 'ap_object' ];
        if ( post_type_exists( 'listing' ) ) {
            $post_types[] = 'listing';
        }

        $query = new WP_Query([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        if ( $query->have_posts() ) :
            while ( $query->have_posts() ) :
                $query->the_post();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('classyfeds-listing'); ?>>
                    <header class="entry-header">
                        <?php if ( 'listing' === get_post_type() ) : ?>
                            <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <?php else : ?>
                            <h2 class="entry-title"><?php the_title(); ?></h2>
                        <?php endif; ?>
                    </header>
                    <div class="entry-content">
                        <?php
                        if ( 'listing' === get_post_type() ) {
                            the_excerpt();
                        } else {
                            $data = json_decode( get_the_content(), true );
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
            endwhile;
            wp_reset_postdata();
        else :
            echo '<p>' . esc_html__( 'No listings found.', 'classyfeds-aggregator' ) . '</p>';
        endif;
        ?>
    </main>
</div>

<?php get_footer();

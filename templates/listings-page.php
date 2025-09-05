<?php
/**
 * Template for the Classifieds listings page.
 *
 * @package Fed_Classifieds
 */

get_header(); ?>
<div id="primary" class="content-area">
    <main id="main" class="site-main fed-classifieds-listings">
        <?php
        $query = new WP_Query([
            'post_type'      => [ 'listing', 'external_listing' ],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        if ( $query->have_posts() ) :
            while ( $query->have_posts() ) :
                $query->the_post();
                $link = 'external_listing' === get_post_type() ? get_post_meta( get_the_ID(), '_external_url', true ) : get_permalink();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('fed-classifieds-listing'); ?>>
                    <header class="entry-header">
                        <?php if ( 'listing' === get_post_type() ) : ?>
                            <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <?php else : ?>
                            <h2 class="entry-title"><a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="nofollow noopener"><?php the_title(); ?></a></h2>
                        <?php endif; ?>
                    </header>
                    <div class="entry-content">
                        <?php
                        if ( 'listing' === get_post_type() ) {
                            $type = get_post_meta( get_the_ID(), '_listing_type', true );
                            if ( $type ) {
                                echo '<p class="listing-type">' . esc_html( $type ) . '</p>';
                            }
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
                    <footer class="entry-footer">
                        <?php
                        $categories = get_the_term_list( get_the_ID(), 'category', '<span class="cat-links">', ', ', '</span>' );
                        if ( $categories ) {
                            echo $categories; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        $tags = get_the_term_list( get_the_ID(), 'post_tag', '<span class="tags-links">', ', ', '</span>' );
                        if ( $tags ) {
                            echo $tags; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                    </footer>
                </article>
                <?php
            endwhile;
            wp_reset_postdata();
        else :
            echo '<p>' . esc_html__( 'No listings found.', 'fed-classifieds' ) . '</p>';
        endif;
        ?>
    </main>
</div>

<?php get_footer(); ?>

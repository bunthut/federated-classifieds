<?php
/**
 * Template for the standalone Classifieds aggregator page.
 *
 * @package Classyfeds_Aggregator
 */

get_header();

$filter_categories = get_option( 'classyfeds_filter_categories', '' );
$cats             = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', $filter_categories ) ) ) );
$current_cat      = isset( $_GET['classyfeds_cat'] ) ? sanitize_title( wp_unslash( $_GET['classyfeds_cat'] ) ) : '';
?>
<div id="primary" class="content-area classyfeds-aggregator">
    <?php if ( $cats ) : ?>
        <nav class="classyfeds-nav">
            <ul>
                <li><a href="<?php echo esc_url( remove_query_arg( 'classyfeds_cat' ) ); ?>" class="<?php echo $current_cat ? '' : 'current-cat'; ?>"><?php esc_html_e( 'All', 'classyfeds-aggregator' ); ?></a></li>
                <?php foreach ( $cats as $slug ) :
                    $term  = get_term_by( 'slug', $slug, 'listing_category' );
                    $name  = $term ? $term->name : ucwords( str_replace( '-', ' ', $slug ) );
                    $link  = add_query_arg( 'classyfeds_cat', $slug );
                    $class = ( $current_cat === $slug ) ? ' class="current-cat"' : '';
                    ?>
                    <li><a href="<?php echo esc_url( $link ); ?>"<?php echo $class; ?>><?php echo esc_html( $name ); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
    <main id="main" class="site-main classyfeds-listings">
        <div class="classyfeds-logo">
            <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/classyfeds.png' ); ?>" alt="ClassyFeds logo" />
        </div>
        <?php
        $post_types = array( 'ap_object' );
        if ( post_type_exists( 'listing' ) ) {
            $post_types[] = 'listing';
        }

        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );

        if ( $current_cat ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'listing_category',
                    'field'    => 'slug',
                    'terms'    => $current_cat,
                ),
            );
        }

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $data = ( 'ap_object' === get_post_type() ) ? json_decode( get_the_content(), true ) : array();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class( 'classyfeds-listing' ); ?>>
                    <?php
                    if ( 'listing' === get_post_type() && has_post_thumbnail() ) {
                        ?>
                        <div class="classyfeds-listing-image">
                            <?php the_post_thumbnail( 'medium' ); ?>
                        </div>
                        <?php
                    } elseif ( isset( $data['image'] ) ) {
                        $img = is_array( $data['image'] ) ? ( $data['image']['url'] ?? '' ) : $data['image'];
                        if ( $img ) {
                            ?>
                            <div class="classyfeds-listing-image">
                                <img src="<?php echo esc_url( $img ); ?>" alt="" />
                            </div>
                            <?php
                        }
                    }
                    ?>
                    <header class="entry-header">
                        <?php if ( 'listing' === get_post_type() ) { ?>
                            <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <?php } else { ?>
                            <h2 class="entry-title"><?php the_title(); ?></h2>
                        <?php } ?>
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
        ?>
    </main>
</div>

<?php get_footer(); ?>


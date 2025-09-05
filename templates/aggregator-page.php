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

        $remote_inbox      = get_option( 'classyfeds_remote_inbox', '' );
        $filter_categories = get_option( 'classyfeds_filter_categories', '' );
        $filter_posts      = (int) get_option( 'classyfeds_filter_posts', 0 );

        $args = [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
$args = [
    'post_type'      => $post_types,
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

$query = new WP_Query( $args );

if ( $query->have_posts() ) :
    while ( $query->have_posts() ) :
        $query->the_post();
        $data = 'ap_object' === get_post_type() ? json_decode( get_the_content(), true ) : [];
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('classyfeds-listing'); ?>>
            <?php if ( 'listing' === get_post_type() && has_post_thumbnail() ) : ?>
                <div class="classyfeds-listing-image">
                    <?php the_post_thumbnail( 'medium' ); ?>
                </div>
            <?php elseif ( isset( $data['image'] ) ) :
                $img = is_array( $data['image'] ) ? ( $data['image']['url'] ?? '' ) : $data['image'];
                if ( $img ) : ?>
                    <div class="classyfeds-listing-image">
                        <img src="<?php echo esc_url( $img ); ?>" alt="" />
                    </div>
                <?php endif;
            endif; ?>
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

$remaining = $filter_posts > 0 ? $filter_posts - $query->post_count : -1;

$remote_items = [];
if ( $remote_inbox ) {
    $response = wp_remote_get( $remote_inbox );
    if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( isset( $data['orderedItems'] ) && is_array( $data['orderedItems'] ) ) {
            foreach ( $data['orderedItems'] as $item ) {
                if ( $cats ) {
                    $item_cats = [];
                    if ( isset( $item['category'] ) ) {
                        $item_cats = (array) $item['category'];
                    }
                    if ( ! array_intersect( $cats, $item_cats ) ) {
                        continue;
                    }
                }
                // Process and display remote items here
            }
        }
    }
}

                            }
                        }
                        $remote_items[] = $item;
                        if ( $remaining > 0 ) {
                            $remaining--;
                            if ( 0 === $remaining ) {
                                break;
                            }
                        }
                    }
                }
            }
        }

        if ( $query->have_posts() || $remote_items ) :
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
            endif;

            if ( $remote_items ) :
                foreach ( $remote_items as $item ) :
                    ?>
                    <article class="classyfeds-listing remote">
                        <header class="entry-header">
                            <h2 class="entry-title"><?php echo esc_html( $item['name'] ?? ( $item['summary'] ?? __( 'Remote listing', 'classyfeds-aggregator' ) ) ); ?></h2>
                        </header>
                        <?php if ( ! empty( $item['content'] ) ) : ?>
                            <div class="entry-content">
                                <?php echo wp_kses_post( wpautop( $item['content'] ) ); ?>
                            </div>
                        <?php endif; ?>
                    </article>
                    <?php
                endforeach;
            endif;
        else :
            echo '<p>' . esc_html__( 'No listings found.', 'classyfeds-aggregator' ) . '</p>';
        endif;
        ?>
    </main>
</div>

<?php get_footer(); ?>

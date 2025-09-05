<?php
/**
 * Template for the standalone Classifieds aggregator page.
 *
 * @package Classyfeds_Aggregator
 */

get_header(); ?>
<div id="primary" class="content-area">
    <main id="main" class="site-main classyfeds-listings">
        <?php echo classyfeds_aggregator_get_listings_html(); ?>
    </main>
</div>

<?php get_footer();

<?php
namespace ClassyFeds;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Attach the listing category taxonomy to ActivityPub objects.
 */
add_action(
    'init',
    function() {
        if ( taxonomy_exists( 'listing_category' ) && post_type_exists( 'ap_object' ) ) {
            register_taxonomy_for_object_type( 'listing_category', 'ap_object' );
        }
    },
    11
);

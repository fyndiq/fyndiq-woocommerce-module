<?php
/**
 *
 * Handles enqueuing scripts
 *
 */

//Hack to provide some variables that we need
add_action('admin_head', function () {
    echo "    <script type='text/javascript'>
                var wordpressurl ='" . get_site_url() . "';
                var trans_error = '" . __('Error!', 'fyndiq') . "';
                var trans_loading ='" . __('Loading', 'fyndiq') . "..." . "';
                var trans_done = '" . __('Done', 'fyndiq') . "';
              </script>";
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('fyndiq-product-update', plugins_url('/js/product-update.js', __FILE__), array(), false, false);

    //This used to only be called if orders are enabled. TODO: Check that this doesn't let users to do things that they shouldn't do
    wp_enqueue_script('fyndiq-order-import', plugins_url('/js/order-import.js', __FILE__), array(), false, false);
});

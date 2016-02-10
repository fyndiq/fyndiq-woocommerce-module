<?php
/**
 *
 * Handles the rendering of metaboxes in orders
 *
 */


/**
 * orderMetaBoxes
 *
 * Adds delivery note metabox
 *
 */
add_action('add_meta_boxes', function () {
    global $post;
    $meta = get_post_custom(getPostId());
    if (isset($meta['fyndiq_delivery_note']) && isset($meta['fyndiq_delivery_note'][0]) && $meta['fyndiq_delivery_note'][0] != '') {
        add_meta_box(
            'woocommerce-order-fyndiq-delivery-note',
            __('Fyndiq', 'fyndiq'),
            function () {
                $meta = get_post_custom(getPostId());
                $GLOBALS['fmOutput']->output('<a href="' . $meta['fyndiq_delivery_note'][0] . '" class="button button-primary">Get Fyndiq Delivery Note</a>');
            },
            'shop_order',
            'side',
            'default'
        );
    }
});

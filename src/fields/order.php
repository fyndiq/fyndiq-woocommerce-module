<?php

/**
 * orderSave
 *
 * Handles saving order fields - currently just whether the order is handled
 *
 */
add_action('woocommerce_process_shop_order_meta', function ($post_id) {
    $orderObject = new FmOrder($post_id);
    $orderObject->setIsHandled($orderObject->getIsHandled());
});


add_action('woocommerce_admin_order_data_after_order_details', function () {
        fyndiq_generate_field('_fyndiq_handled_order', array(
            'type' => 'checkbox',
            'class' => array('input-checkbox'),
            'label' => __('Order handled', 'fyndiq'),
            'description' => __('Report this order as handled to Fyndiq', 'fyndiq'),
        ), (bool)get_post_meta(getPostId(), '_fyndiq_handled_order', true));
    });

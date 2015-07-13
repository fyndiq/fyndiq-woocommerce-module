<?php

class FmOrder
{
    public function orderExists($fyndiq_id)
    {
        $args = array(
            'meta_key' => '',
            'meta_value' => $fyndiq_id,
            'post_type' => 'shop_order',
            'posts_per_page' => -1,
            'post_status' => array_keys(wc_get_order_statuses())
        );
        $posts = get_posts($args);
        return count($posts) > 0;
    }

    public function createOrder($order)
    {
        // build order data
        $order_data = array(
            'post_name' => 'order-' . date_format(new DateTime($order->created), 'M-d-Y-hi-a'), //'order-jun-19-2014-0648-pm'
            'post_type' => 'shop_order',
            'post_title' => 'Order &ndash; ' . date_format(new DateTime($order->created), 'F d, Y @ h:i A'), //'June 19, 2014 @ 07:19 PM'
            'post_status' => 'wc-completed',
            'ping_status' => 'closed',
            'post_excerpt' => 'Generated from Fyndiq',
            'post_author' => 0,
            'post_password' => uniqid('order_'), // Protects the post just in case
            'post_date' => date_format(new DateTime($order->created), 'Y-m-d H:i:s e'), //'order-jun-19-2014-0648-pm'
            'comment_status' => 'open'
        );

        // create order
        $order_id = wp_insert_post($order_data, true);

        if (is_wp_error($order_id)) {
            $order->errors = $order_id;

        } else {
            $order->imported = true;

            $order_total = 0;
            foreach ($order->order_rows as $order_rows) {
                $order_total += ($order_rows->unit_price_amount*$order_rows->quantity);
            }
            // add a bunch of meta data
            add_post_meta($order_id, 'fyndiq_id', $order->id, true);
            add_post_meta($order_id, 'fyndiq_delivery_note', 'https://fyndiq.se' . $order->delivery_note, true);
            add_post_meta($order_id, '_payment_method_title', 'Import', true);
            add_post_meta($order_id, '_order_total', $order_total, true);
            add_post_meta($order_id, '_customer_user', 0, true);
            add_post_meta($order_id, '_completed_date', date_format(new DateTime($order->created), 'Y-m-d H:i:s e'), true);
            add_post_meta($order_id, '_order_currency', $order->order_rows[0]->unit_price_currency, true);
            add_post_meta($order_id, '_paid_date', date_format(new DateTime($order->created), 'Y-m-d H:i:s e'), true);

            // Shipping info
            add_post_meta($order_id, '_shipping_address_1', $order->delivery_address, true);
            add_post_meta($order_id, '_shipping_city', $order->delivery_city, true);
            add_post_meta($order_id, '_shipping_postcode', $order->delivery_postalcode, true);
            add_post_meta($order_id, '_shipping_country', $order->delivery_country, true);
            add_post_meta($order_id, '_shipping_first_name', $order->delivery_firstname, true);
            add_post_meta($order_id, '_shipping_last_name', $order->delivery_lastname, true);
            add_post_meta($order_id, '_shipping_phone', $order->delivery_phone, true);


            foreach ($order->order_rows as $order_row) {
                // get product by item_id
                $product = $this->get_product_by_sku($order_row->sku);

                $product_total = ($order_row->unit_price_amount*$order_row->quantity);

                if ($product) {
                    // add item
                    $item_id = wc_add_order_item(
                        $order_id,
                        array(
                            'order_item_name' => $product->get_title(),
                            'order_item_type' => 'line_item'
                        )
                    );

                    if ($item_id) {
                        // add item meta data
                        wc_add_order_item_meta($item_id, '_qty', $order_row->quantity);
                        wc_add_order_item_meta($item_id, '_tax_class', $product->get_tax_class());
                        wc_add_order_item_meta($item_id, '_product_id', $product->ID);
                        wc_add_order_item_meta($item_id, '_variation_id', '');
                        wc_add_order_item_meta($item_id, '_line_subtotal', wc_format_decimal(intval($product_total)));
                        wc_add_order_item_meta($item_id, '_line_total', wc_format_decimal(intval($product_total)));

                    }

                    // if downloadable
                    if ($product->is_downloadable()) {
                        wp_die("ERROR - product is downloadable.");
                    }

                } else {
                    echo 'Product SKU (' . $order_row->sku . ') not found.';
                }
            }

            $wc_order = new WC_Order($order_id);

            // set order status as completed
            $order_status = get_option('wcfyndiq_create_order_status');
            $wc_order->update_status($order_status);
        }

    }

    public function get_product_by_sku($sku)
    {

        global $wpdb;

        $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));

        if ($product_id) {
            return new WC_Product($product_id);
        }

        return null;

    }
}

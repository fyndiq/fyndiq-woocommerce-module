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
        $wc_order = wc_create_order();

        if (is_wp_error($wc_order)) {
            wp_die("ERROR - Couldn't create order");
        }

        $address = array(
            'first_name' => $order->delivery_firstname,
            'last_name'  => $order->delivery_lastname,
            'company'    => '',
            'email'      => '',
            'phone'      => $order->delivery_phone,
            'address_1'  => $order->delivery_address,
            'address_2'  => '',
            'city'       => $order->delivery_city,
            'state'      => '',
            'postcode'   => $order->delivery_postalcode,
            'country'    => $order->delivery_country_code
        );
        // add a bunch of meta data
        add_post_meta($wc_order->id, 'fyndiq_id', $order->id, true);
        add_post_meta($wc_order->id, 'fyndiq_delivery_note', 'https://fyndiq.se' . $order->delivery_note, true);
        add_post_meta($wc_order->id, '_payment_method_title', 'Import', true);

        add_post_meta($wc_order->id, '_customer_user', 0, true);
        add_post_meta($wc_order->id, '_completed_date', date_format(new DateTime($order->created), 'Y-m-d H:i:s e'), true);
        add_post_meta($wc_order->id, '_order_currency', $order->order_rows[0]->unit_price_currency, true);
        add_post_meta($wc_order->id, '_paid_date', date_format(new DateTime($order->created), 'Y-m-d H:i:s e'), true);

        $wc_order->set_address($address, 'shipping');

        foreach ($order->order_rows as $order_row) {
            // get product by item_id
            $product = $this->get_product_by_sku($order_row->sku);

            $product_total = ($order_row->unit_price_amount*$order_row->quantity);
            if ($product) {
                // if downloadable
                if ($product->is_downloadable()) {
                    wp_die("ERROR - product is downloadable.");
                }
                // add item
                $args = array(
                  'totals' => array(
                    'taxdata' => array()
                  )
                );
                $args['totals']['subtotal'] = intval($order_row->unit_price_amount);
                $args['totals']['total'] = intval($order_row->unit_price_amount);
                $args['totals']['taxdata']['total']  = (intval($product_total)*((100+intval($row->vat_percent)) / 100));
                $args['totals']['taxdata']['subtotal'] = (intval($order_row->unit_price_amount)*((100+intval($row->vat_percent)) / 100));

                $wc_order->add_product($product, $order_row->quantity, $args);
            } else {
                echo 'Product SKU (' . $order_row->sku . ') not found.';
            }
        }
        $wc_order->calculate_totals();

        // set order status as completed
        $order_status = get_option('wcfyndiq_create_order_status');
        $wc_order->update_status($order_status);
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

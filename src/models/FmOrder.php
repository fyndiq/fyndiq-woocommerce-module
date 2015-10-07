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
        $status = get_option('wcfyndiq_create_order_status');

        $settings = array(
            'status'        => $status,
            'created_via'   => 'fyndiq'
        );

        foreach ($order->order_rows as $order_row) {
            // get product by item_id
            $product = $this->getProductBySku($order_row->sku);
            if (!isset($product)) {
                wp_die(sprintf(__('Product SKU ( %s ) not found.', 'fyndiq'), $order_row->sku));
            }
        }

        $order_type = wc_get_order_type('shop_order');
        if (!$order_type) {
            wc_register_order_type(
                'shop_order',
                apply_filters(
                    'woocommerce_register_post_type_shop_order',
                    array(
                        'labels'              => array(
                                'name'               => __('Orders', 'woocommerce'),
                                'singular_name'      => __('Order', 'woocommerce'),
                                'add_new'            => __('Add Order', 'woocommerce'),
                                'add_new_item'       => __('Add New Order', 'woocommerce'),
                                'edit'               => __('Edit', 'woocommerce'),
                                'edit_item'          => __('Edit Order', 'woocommerce'),
                                'new_item'           => __('New Order', 'woocommerce'),
                                'view'               => __('View Order', 'woocommerce'),
                                'view_item'          => __('View Order', 'woocommerce'),
                                'search_items'       => __('Search Orders', 'woocommerce'),
                                'not_found'          => __('No Orders found', 'woocommerce'),
                                'not_found_in_trash' => __('No Orders found in trash', 'woocommerce'),
                                'parent'             => __('Parent Orders', 'woocommerce'),
                                'menu_name'          => _x('Orders', 'Admin menu name', 'woocommerce')
                            ),
                        'description'         => __('This is where store orders are stored.', 'woocommerce'),
                        'public'              => false,
                        'show_ui'             => true,
                        'capability_type'     => 'shop_order',
                        'map_meta_cap'        => true,
                        'publicly_queryable'  => false,
                        'exclude_from_search' => true,
                        'show_in_menu'        => current_user_can('manage_woocommerce') ? 'woocommerce' : true,
                        'hierarchical'        => false,
                        'show_in_nav_menus'   => false,
                        'rewrite'             => false,
                        'query_var'           => false,
                        'supports'            => array( 'title', 'comments', 'custom-fields' ),
                        'has_archive'         => false,
                    )
                )
            );
        }
        $wc_order = wc_create_order($settings);
        if (is_wp_error($wc_order)) {
            wp_die(__('ERROR - Could not create order', 'fyndiq'));
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
        add_post_meta($wc_order->id, 'fyndiq_delivery_note', $order->delivery_note, true);
        add_post_meta($wc_order->id, '_payment_method_title', 'Import', true);

        add_post_meta($wc_order->id, '_customer_user', 0, true);
        add_post_meta($wc_order->id, '_completed_date', date_format(new DateTime($order->created), 'Y-m-d H:i:s e'), true);
        add_post_meta($wc_order->id, '_order_currency', $order->order_rows[0]->unit_price_currency, true);
        add_post_meta($wc_order->id, '_paid_date', date_format(new DateTime($order->created), 'Y-m-d H:i:s e'), true);

        $wc_order->set_address($address, 'shipping');

        foreach ($order->order_rows as $order_row) {
            // get product by item_id
            $product = $this->getProductBySku($order_row->sku);

            if (isset($product)) {
                // if downloadable
                if ($product->is_downloadable()) {
                    wp_die(__('ERROR - product is downloadable.', 'fyndiq'));
                }
                // add item
                $args = array(
                  'totals' => array(
                    'taxdata' => array()
                  )
                );


                $product_total = ($order_row->unit_price_amount*$order_row->quantity);

                if(wc_tax_enabled() && wc_prices_include_tax()) {
                    $product_total = ($order_row->unit_price_amount * $order_row->quantity)  / ((100+intval($order_row->vat_percent)) / 100);
                }
                elseif(!wc_tax_enabled()) {
                    $product_total = ($order_row->unit_price_amount * $order_row->quantity)  / ((100+intval($order_row->vat_percent)) / 100);
                }

                $args['totals']['total'] = $product_total;

                $wc_order->add_product($product, $order_row->quantity, $args);
                $product->set_stock($order_row->quantity, 'subtract');
            } else {
                wp_die(sprintf(__('Product SKU ( %s ) not found.', 'fyndiq'), $order_row->sku));
            }
        }
        $wc_order->calculate_totals();
    }

    public function getProductBySku($sku)
    {
        global $wpdb;

        $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));

        if ($product_id) {
            return new WC_Product($product_id);
        }

        return null;
    }
}

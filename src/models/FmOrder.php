<?php
/**
 * Class FmOrder
 *
 * Object model for orders
 */

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class FmOrder extends FmPost
{
    const FYNDIQ_ID_META_FIELD = 'fyndiq_id';
    const FYNDIQ_HANDLED_ORDER_META_FIELD = '_fyndiq_handled_order';

    //Getter for whether the order is handled. Takes into account $_POST when called.
    public function getIsHandled()
    {
        //If we're saving the post, look in the HTTP POST data.
        if ((isset($_POST['action']) && isset($_POST['post_type'])) &&
            ($_POST['action'] == 'editpost' && $_POST['post_type'] == 'shop_order')) {
            //Is only set if box is ticked.
            return isset($_POST['_fyndiq_handled_order']);
            //Otherwise, look in the metadata.
        } elseif (!get_post_meta($this->getPostId(), self::FYNDIQ_HANDLED_ORDER_META_FIELD, true)) {
            return 0;
        }
        return 1;
    }

    public function setIsHandled($value)
    {
        /**
         * This might seem inadequate in terms of input sanity,
         * but actually would be no different than an if statement.
         */
        update_post_meta($this->getPostId(), self::FYNDIQ_HANDLED_ORDER_META_FIELD, (bool)$value);

        $markPair = new stdClass();
        $markPair->id = $this->getFyndiqOrderId();
        $markPair->marked = (bool)$value;

        $data = new stdClass();
        $data->orders = array($markPair);
        try {
            FmHelpers::callApi('POST', 'orders/marked/', $data);
        } catch (Exception $e) {
            FmError::handleError($e->getMessage());
        }
    }


    public function getFyndiqOrderId()
    {
        return get_post_meta($this->getPostId(), self::FYNDIQ_ID_META_FIELD, true);
    }

    public function setFyndiqOrderId($fyndiqId)
    {
        $this->setMetaData(self::FYNDIQ_ID_META_FIELD, $fyndiqId);
    }



    /**
     * Here be dragons. By dragons, I mean static methods.
     */

    public static function orderExists($fyndiqId)
    {
        $args = array(
            'meta_key' => '',
            'meta_value' => $fyndiqId,
            'post_type' => 'shop_order',
            'posts_per_page' => -1,
            'post_status' => array_keys(wc_get_order_statuses())
        );
        $posts = get_posts($args);
        return count($posts) > 0;
    }

    public static function createOrder($order)
    {
        $status = get_option('wcfyndiq_create_order_status');

        $settings = array(
            'status'        => $status,
            'created_via'   => 'fyndiq'
        );

        foreach ($order->order_rows as $order_row) {
            // get product by item_id
            $product = FmOrder::getProductByReference($order_row->sku);
            if (!isset($product)) {
                throw new Exception(sprintf(__('Product SKU ( %s ) not found.', 'fyndiq'), $order_row->sku));
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
            throw new Exception(__('ERROR - Could not create order', 'fyndiq'));
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
            $product = FmOrder::getProductByReference($order_row->sku);

            if (isset($product)) {
                // if downloadable
                if ($product->is_downloadable()) {
                    throw new Exception(__('ERROR - product is downloadable.', 'fyndiq'));
                }
                // add item
                $args = array(
                    'totals' => array(
                        'taxdata' => array()
                    )
                );

                $product_total = ($order_row->unit_price_amount * $order_row->quantity)  / ((100+intval($order_row->vat_percent)) / 100);

                if (FmHelpers::fyndiq_wc_tax_enabled() && !FmHelpers::fyndiq_wc_prices_include_tax()) {
                    $product_total = ($order_row->unit_price_amount*$order_row->quantity);
                }

                $args['totals']['total'] = $product_total;

                $wc_order->add_product($product, $order_row->quantity, $args);
                $product->set_stock($order_row->quantity, 'subtract');
            } else {
                throw new Exception(sprintf(__('Product SKU ( %s ) not found.', 'fyndiq'), $order_row->sku));
            }
        }
        $wc_order->calculate_totals();
    }

    public static function getProductBySku($sku)
    {
        global $wpdb;

        $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));

        if ($product_id) {
            $product = new WC_Product($product_id);
            if (!is_null($product->post)) {
                return $product;
            }
            return null;
        }

        return null;
    }

    public static function getProductById($product_id)
    {
        $product = new WC_Product($product_id);
        if (!is_null($product->post)) {
            return $product;
        }
        return null;
    }

    public static function getProductByReference($reference)
    {
        $option = get_option('wcfyndiq_reference_picker');
        if (empty($reference)) {
            return null;
        }
        switch ($option) {
            case FmExport::REF_ID:
                $id = explode(FmExport::REF_DELIMITER, $reference);
                return (count($id) == 2) ? FmOrder::getProductById(end($id)) : FmOrder::getProductById(reset($id));
            default:
                return FmOrder::getProductBySku($reference);
        }
    }

    public static function getWordpressCurrentOrderID()
    {
        return get_the_ID();
    }

    /**
     *
     * Sets whether the given orders are marked as processed to Fyndiq or not
     *
     * @param $orders - an array of orders in the structure:
     *
     * array(
     *        array(
     *              id => postIDvalue,
     *              marked => boolean
     *              ),
     *                  ...
     * )
     * @throws Exception
     *
     */
    public static function setIsHandledBulk($orders)
    {
        $data = new stdClass();


        $data->orders = $orders;

        //Try to send the data to the API
        try {
            FmHelpers::callApi('POST', 'orders/marked/', $data);
        } catch (Exception $e) {
            FmError::handleError(urlencode($e->getMessage()));
        }

        //If the API call worked, update the orders on WC
        foreach ($orders as $order) {
            $orderObject = new FmOrder($order['id']);
            $orderObject->setIsHandled((bool) $order['marked']);
        }
    }


    //This probably can be removed with some refactoring.
    public static function setOrderError()
    {
        if (get_option('wcfyndiq_order_error') !== false) {
            update_option('wcfyndiq_order_error', true);
        } else {
            add_option('wcfyndiq_order_error', true, null, false);
        }
    }

    public static function generateOrders()
    {
        $fmOutput = new FyndiqOutput();

        define('DOING_AJAX', true);
        try {
            $orderFetch = new FmOrderFetch(false, true);
            $result = $orderFetch->getAll();
            update_option('wcfyndiq_order_time', time());
        } catch (Exception $e) {
            $result = $e->getMessage();
            FmOrder::setOrderError();
        }
        $fmOutput->outputJSON($result);
        wp_die();
    }
}

<?php
//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class WC_Fyndiq
{
    private $filepath = null;
    private $fmOutput = null;
    private $productImages = null;
    private $categoryCache = array();

    const NOTICES = 'fyndiq_notices';

    const ORDERS_DISABLE = 1;
    const ORDERS_ENABLE = 2;

    const SETTING_TAB_PRIORITY = 50;


    public function __construct()
    {
        $this->currencies = array_combine(FyndiqUtils::$allowedCurrencies, FyndiqUtils::$allowedCurrencies);

        //Register class hooks as early as possible
        add_action('wp_loaded', array(&$this, 'initiateClassHooks'));

        //Load locale in init
        add_action('init', array(&$this, 'locale_load'));

        // called only after woocommerce has finished loading
        add_action('init', array(&$this, 'woocommerce_loaded'), 250);

        $this->filepath = wp_upload_dir()['basedir'] . '/fyndiq-feed.csv';

        $this->fmOutput = new FyndiqOutput();
        $this->fmUpdate = new FmUpdate();
        $this->fmExport = new FmExport($this->filepath, $this->fmOutput);
    }

    public function locale_load()
    {
        // Localization
        load_plugin_textdomain('fyndiq', false, dirname(plugin_basename(__FILE__)) . '/translations/');
    }

    public function initiateClassHooks()
    {
        FmError::setHooks();
        FmProduct::setHooks();
        new FmField();
    }

    /**
     * Take care of anything that needs woocommerce to be loaded.
     * For instance, if you need access to the $woocommerce global
     */
    public function woocommerce_loaded()
    {
        //javascript
        //@todo Fix JS loading
        add_action('admin_head', array(&$this, 'get_url'));


        //Settings
        add_filter('woocommerce_settings_tabs_array', array(&$this, 'fyndiq_add_settings_tab'), self::SETTING_TAB_PRIORITY);
        add_action('woocommerce_settings_tabs_wcfyndiq', array(&$this, 'settings_tab'));
        add_action('woocommerce_update_options_wcfyndiq', array(&$this, 'update_settings'));

        //products


        add_action('woocommerce_process_shop_order_meta', array(&$this, 'fyndiq_order_handled_save'));

        add_action('woocommerce_admin_order_data_after_order_details', array(&$this, 'fyndiq_add_order_field'));
        add_action('woocommerce_product_write_panel_tabs', array(&$this, 'fyndiq_product_tab'));


        //product list
        add_filter('manage_edit-product_columns', array(&$this, 'fyndiq_product_add_column'));
        add_action('manage_product_posts_custom_column', array(&$this, 'fyndiq_product_column_export'), 5, 2);
        add_filter('manage_edit-product_sortable_columns', array(&$this, 'fyndiq_product_column_sort'));
        add_action('pre_get_posts', array(&$this, 'fyndiq_product_column_sort_by'));
        add_action('admin_notices', array(&$this, 'fyndiq_bulk_notices'));
        add_action('admin_notices', array(&$this, 'do_bulk_action_messages'));


        //order list
        if ($this->ordersEnabled()) {
            add_filter('manage_edit-shop_order_columns', array(&$this, 'fyndiq_order_add_column'));
            add_action('manage_shop_order_posts_custom_column', array(&$this, 'fyndiq_order_column'), 5, 2);
            add_filter('manage_edit-shop_order_sortable_columns', array(&$this, 'fyndiq_order_column_sort'));
            add_action('load-edit.php', array(&$this, 'fyndiq_order_delivery_note_bulk_action'));
        }

        //bulk action
        //Inserts the JS for the appropriate dropdown items
        add_action('admin_footer-edit.php', array(&$this, 'fyndiq_add_bulk_action'));

        //The actual actions behind the bulk actions. Ought to be coalesced into a dispatcher
        add_action('load-edit.php', array(&$this, 'fyndiq_product_export_bulk_action'));
        add_action('load-edit.php', array(&$this, 'fyndiq_bulk_action_dispatcher'));

        //add_action('post_submitbox_misc_actions', array( &$this, 'fyndiq_order_edit_action'));
        add_action('add_meta_boxes', array(&$this, 'fyndiq_order_meta_boxes'));

        //notice for currency check
        add_action('admin_notices', array(&$this, 'my_admin_notice'));

        //Checker Page
        add_action('admin_menu', array(&$this, 'fyndiq_add_menu'));
        add_filter('plugin_action_links_' . plugin_basename(dirname(__FILE__).'/woocommerce-fyndiq.php'), array(&$this, 'fyndiq_action_links'));

        //index
        add_action('load-index.php', array($this->fmUpdate, 'updateNotification'));

        //orders
        add_action('load-edit.php', array(&$this, 'fyndiq_show_order_error'));

        //functions
        if (isset($_GET['fyndiq_feed'])) {
            $this->fmExport->generate_feed();
        }
        if (isset($_GET['fyndiq_orders'])) {
            $this->generate_orders();
        }
        if (isset($_GET['fyndiq_products'])) {
            define('DOING_AJAX', true);
            $this->update_product_info();
            $this->fmOutput->outputJSON(array('status' => 'ok'));
            wp_die();
        }
        if (isset($_GET['fyndiq_notification'])) {
            $this->notification_handle();
        }
    }

    function fyndiq_add_menu()
    {
        add_submenu_page(null, 'Fyndiq Checker Page', 'Fyndiq', 'manage_options', 'fyndiq-check', array(&$this, 'check_page'));
    }

    function fyndiq_action_links($links)
    {
        $checkUrl = esc_url(get_admin_url(null, 'admin.php?page=fyndiq-check'));
        $settingUrl = esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=products&section=wcfyndiq'));
        $links[] = '<a href="'.$settingUrl.'">'.__('Settings', 'fyndiq').'</a>';
        $links[] = '<a href="'.$checkUrl.'">'.__('Fyndiq Check', 'fyndiq').'</a>';
        return $links;
    }

    public function fyndiq_order_meta_boxes()
    {
        $meta = get_post_custom(FmOrder::getWordpressCurrentPostID());
        if (isset($meta['fyndiq_delivery_note']) && isset($meta['fyndiq_delivery_note'][0]) && $meta['fyndiq_delivery_note'][0] != '') {
            add_meta_box(
                'woocommerce-order-fyndiq-delivery-note',
                __('Fyndiq', 'fyndiq'),
                array(&$this, 'order_meta_box_delivery_note'),
                'shop_order',
                'side',
                'default'
            );
        }
    }

    public function order_meta_box_delivery_note()
    {
        $meta = get_post_custom(FmOrder::getWordpressCurrentPostID());
        $this->fmOutput->output('<a href="' . $meta['fyndiq_delivery_note'][0] . '" class="button button-primary">Get Fyndiq Delivery Note</a>');
    }

    public function get_url()
    {
        if ($this->ordersEnabled()) {
            $script = <<<EOS
            <script type="text/javascript">
                var wordpressurl = '%s';
                var trans_error = '%s';
                var trans_loading = '%s';
                var trans_done = '%s';
            </script>
            <script src="%s" type="text/javascript"></script>
            <script src="%s" type="text/javascript"></script>
EOS;
            printf(
                $script,
                get_site_url(),
                __('Error!', 'fyndiq'),
                __('Loading', 'fyndiq') . '...',
                __('Done', 'fyndiq'),
                plugins_url('/js/order-import.js', __FILE__),
                plugins_url('/js/product-update.js', __FILE__)
            );
        } else {
            $script = <<<EOS
            <script type="text/javascript">
                var wordpressurl = '%s';
                var trans_error = '%s';
                var trans_loading = '%s';
                var trans_done = '%s';
            </script>
            <script src="%s" type="text/javascript"></script>
EOS;
            printf(
                $script,
                get_site_url(),
                __('Error!', 'fyndiq'),
                __('Loading', 'fyndiq') . '...',
                __('Done', 'fyndiq'),
                plugins_url('/js/product-update.js', __FILE__)
            );
        }


    }

    function settings_tab()
    {
        woocommerce_admin_fields($this->fyndiq_all_settings());
    }

    public function fyndiq_all_settings()
    {

        //Get options for attributes
        $attributes = $this->getAllTerms();

        /**
         * Check the current section is what we want
         **/
        $settings_slider = array();

        $settings_slider[] = array(
            'name'     => __('Fyndiq', 'fyndiq'),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_settings_wcfyndiq_section_title'
        );

        // Add Title to the Settings
        $settings_slider[] = array(
            'name' => __('General Settings', 'fyndiq'),
            'type' => 'title',
            'desc' => __('The following options are used to configure Fyndiq', 'fyndiq'),
            'id' => 'wcfyndiq'
        );

        // Add second text field option
        $settings_slider[] = array(

            'name' => __('Username', 'fyndiq'),
            'desc_tip' => __('This is the username you use for login on Fyndiq Merchant', 'fyndiq'),
            'id' => 'wcfyndiq_username',
            'type' => 'text',
            'desc' => __('Must be your username', 'fyndiq'),

        );

        // Add second text field option
        $settings_slider[] = array(

            'name' => __('API-token', 'fyndiq'),
            'desc_tip' => __('This is the API V2 Token on Fyndiq', 'fyndiq'),
            'id' => 'wcfyndiq_apitoken',
            'type' => 'text',
            'desc' => __('Must be API v2 token', 'fyndiq'),
        );


        //Price Percentage
        $settings_slider[] = array(

            'name' => __('Global Price Percentage', 'fyndiq'),
            'desc_tip' => __(
                'The percentage that will be removed from the price when sending to fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_price_percentage',
            'type' => 'text',
            'default' => '10',
            'desc' => __('Can be 0 if the price should be the same as in your shop.', 'fyndiq'),

        );

        //Price Discount
        $settings_slider[] = array(

            'name' => __('Global Price Discount', 'fyndiq'),
            'desc_tip' => __(
                'The amount that will be removed from the price when sending to fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_price_discount',
            'type' => 'text',
            'default' => '0',
            'desc' => __('Can be 0 if the price should not change', 'fyndiq'),

        );

        if (isset($_GET['set_sku'])) {
            // Add SKU picker
            $settings_slider[] = array(

                'name' => __('Reference to be in use', 'fyndiq'),
                'desc_tip' => __(
                    'If you have multi SKU as in variations changing this will make it work better',
                    'fyndiq'
                ),
                'id' => 'wcfyndiq_reference_picker',
                'type' => 'select',
                'options' => array(
                    FmExport::REF_SKU => __('SKU', 'fyndiq'),
                    FmExport::REF_ID => __('Product and Article ID', 'fyndiq'),
                ),
                'desc' => __('If this value is changed, products already existing on Fyndiq will be removed and uploaded again and orders might not be able to be imported with old SKU.', 'fyndiq'),
            );
        }

        // Add currency setting
        $settings_slider[] = array(

            'name' => __('Used Currency', 'fyndiq'),
            'desc_tip' => __(
                'Choose currency to be used for Fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_currency',
            'type' => 'select',
            'options' => $this->currencies,
            'desc' => __('This must be picked accurate', 'fyndiq'),

        );

        //Minimum Quantity limit
        $settings_slider[] = array(

            'name' => __('Minimum Quantity Limit', 'fyndiq'),
            'desc_tip' => __(
                'this quantity will be reserved by you and will be removed from the quantity that is sent to Fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_quantity_minimum',
            'type' => 'text',
            'default' => '0',
            'desc' => __('Stay on 0 if you want to send all stock to Fyndiq.', 'fyndiq'),

        );

        // Add Description picker
        $settings_slider[] = array(

            'name' => __('Enable Orders', 'fyndiq'),
            'desc_tip' => __(
                'This will disable all order logic for Fyndiq',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_order_enable',
            'type' => 'select',
            'options' => array(
                self::ORDERS_ENABLE => __('Enable', 'fyndiq'),
                self::ORDERS_DISABLE => __('Disable', 'fyndiq'),
            ),
            'desc' => __('Default is to have orders enabled', 'fyndiq'),


            // Add order status setting
            $settings_slider[] = array(

                'name' => __('Order Status', 'fyndiq'),
                'desc_tip' => __(
                    'When a order is imported from fyndiq, this status will be applied.',
                    'fyndiq'
                ),
                'id' => 'wcfyndiq_create_order_status',
                'type' => 'select',
                'options' => array(
                    'completed' => 'completed',
                    'processing' => 'processing',
                    'pending' => 'pending',
                    'on-hold' => 'on-hold'
                ),
                'desc' => __('This must be picked accurate', 'fyndiq')
            ));

            $settings_slider[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end'
            );

            $settings_slider[] = array(
            'name'     => __('Field Mappings', 'fyndiq'),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_settings_wcfyndiq_section_title'
            );


        // Add Description picker
            $settings_slider[] = array(
            'name' => __('Description to use', 'fyndiq'),
            'desc_tip' => __(
                'Set how you want your description to be exported to Fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_description_picker',
            'type' => 'select',
            'options' => array(
                FmExport::DESCRIPTION_LONG => __('Long Description', 'fyndiq'),
                FmExport::DESCRIPTION_SHORT => __('Short Description', 'fyndiq'),
                FmExport::DESCRIPTION_SHORT_LONG => __('Short and Long Description', 'fyndiq'),
            ),
            'desc' => __('Default is Long Description', 'fyndiq'),
            );

        // Map Field for EAN
            $settings_slider[] = array(
            'name' => __('EAN', 'fyndiq'),
            'desc_tip' => __(
                'EAN',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_ean',
            'type' => 'select',
            'options' => $attributes,
            'desc' => __('This must be picked accurate', 'fyndiq'),
            );

        // Map Field for ISBN
            $settings_slider[] = array(
            'name' => __('ISBN', 'fyndiq'),
            'desc_tip' => __(
                'ISBN',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_isbn',
            'type' => 'select',
            'options' => $attributes,
            'desc' => __('This must be picked accurate', 'fyndiq'),
            );

        // Map Field for MPN
            $settings_slider[] = array(
            'name' => __('MPN', 'fyndiq'),
            'desc_tip' => __(
                'MPN',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_mpn',
            'type' => 'select',
            'options' => $attributes,
            'desc' => __('This must be picked accurate', 'fyndiq'),
            );

        // Map Field for MPN
            $settings_slider[] = array(
            'name' => __('Brand', 'fyndiq'),
            'desc_tip' => __(
                'Brand',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_brand',
            'type' => 'select',
            'options' => $attributes,
            'desc' => __('This must be picked accurate', 'fyndiq'),
            );

            $settings_slider[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end'
            );

            return apply_filters('wc_settings_tab_wcfyndiq', $settings_slider);
    }

    public function fyndiq_add_settings_tab($settings_tabs)
    {
        $settings_tabs['wcfyndiq'] = __('Fyndiq', 'fyndiq');
        return $settings_tabs;
    }

    public function update_settings()
    {
        woocommerce_update_options($this->fyndiq_all_settings());
        try {
            $this->updateUrls();
        } catch (Exception $e) {
            if ($e->getMessage() == 'Unauthorized') {
                $this->fyndiq_show_setting_error_notice();
            }
        }
    }

    public function updateUrls()
    {
        //Generate pingtoken
        $pingToken = md5(uniqid());
        update_option('wcfyndiq_ping_token', $pingToken);

        $data = array(
            FyndiqUtils::NAME_PRODUCT_FEED_URL => get_site_url() . '/?fyndiq_feed&pingToken=' . $pingToken,
            FyndiqUtils::NAME_PING_URL => get_site_url() .
                '/?fyndiq_notification=1&event=ping&pingToken=' . $pingToken
        );
        if ($this->ordersEnabled()) {
            $data[FyndiqUtils::NAME_NOTIFICATION_URL] = get_site_url() . '/?fyndiq_notification=1&event=order_created';
        }
        return FmHelpers::callApi('PATCH', 'settings/', $data);
    }

    public function fyndiq_product_tab()
    {
        echo sprintf("<li class='fyndiq_tab'><a href='#fyndiq_tab'>%s</a></li>", __('Fyndiq', 'fyndiq'));
    }




    /**
     *
     * This is the hooked function for fields on the order pages
     *
     */
    public function fyndiq_add_order_field()
    {
        $order = new FmOrder(FmOrder::getWordpressCurrentOrderID());

        FmField::fyndiq_generate_field(FmOrder::FYNDIQ_HANDLED_ORDER_META_FIELD, array(
            'type' => 'checkbox',
            'class' => array('input-checkbox'),
            'label' => __('Order handled', 'fyndiq'),
            'description' => __('Report this order as handled to Fyndiq', 'fyndiq'),
        ), (bool)$order->getIsHandled());
    }


    public function fyndiq_show_order_error()
    {
        if (isset($_GET['post_type']) && $_GET['post_type'] == 'shop_order') {
            $error = get_option('wcfyndiq_order_error');
            if ($error) {
                add_action('admin_notices', array(&$this, 'fyndiq_show_order_error_notice'));
                update_option('wcfyndiq_order_error', false);
            }
        }
    }

    public function fyndiq_show_order_error_notice()
    {
        $this->fmOutput->output(sprintf(
            '<div class="error"><p>%s</p></div>',
            __('Some Fyndiq Orders failed to be imported, most likely due to
            stock or couldn\'t find product on Reference.', 'fyndiq')
        ));
    }

    public function fyndiq_show_setting_error_notice()
    {
        $this->fmOutput->output(sprintf(
            '<div class="error"><p>%s</p></div>',
            __('Fyndiq credentials was wrong, try again.', 'fyndiq')
        ));
    }

    /**
     *
     * Hooked action for saving orders handled status (woocommerce_process_shop_order_meta)
     *
     * @param int $orderId
     */
    public function fyndiq_order_handled_save($orderId)
    {
        $orderObject = new FmOrder($orderId);
        $orderObject->setIsHandled($orderObject->getIsHandled());
    }

    //Hooked function for adding columns to the products page (manage_edit-shop_order_columns)
    public function fyndiq_order_add_column($defaults)
    {
        $defaults['fyndiq_order'] = __('Fyndiq Order', 'fyndiq');
        return $defaults;
    }

    public function fyndiq_order_column($column, $orderId)
    {
        if ($column === 'fyndiq_order') {
            $fyndiq_order = get_post_meta($orderId, 'fyndiq_id', true);
            if ($fyndiq_order != '') {
                $this->fmOutput->output($fyndiq_order);
            } else {
                update_post_meta($orderId, 'fyndiq_id', '-');
                $this->fmOutput->output('-');
            }
        }
    }

    public function fyndiq_order_column_sort()
    {
        return array(
            'fyndiq_order' => 'fyndiq_order'
        );
    }

    public function fyndiq_order_column_sort_by($query)
    {
        if (!is_admin()) {
            return;
        }
        $orderby = $query->get('orderby');
        if ('fyndiq_order' === $orderby) {
            $query->set('meta_key', 'fyndiq_id');
            $query->set('orderby', 'meta_value_integer');
        }
    }



    //Hooked function for adding columns to the products page (manage_edit-product_columns)
    public function fyndiq_product_add_column($defaults)
    {
        $defaults['fyndiq_export'] = __('Fyndiq', 'fyndiq');
        return $defaults;
    }

    public function fyndiq_product_column_sort()
    {
        return array(
            'fyndiq_export' => 'fyndiq_export',
        );
    }

    public function fyndiq_product_column_sort_by($query)
    {
        if (!is_admin()) {
            return;
        }
        $orderby = $query->get('orderby');
        if ('fyndiq_export' == $orderby) {
            $query->set('meta_key', '_fyndiq_export');
            $query->set('orderby', 'meta_value');
        }
    }

    public function fyndiq_product_column_export($column, $postId)
    {
        $product = new FmProduct($postId);

        if ($column == 'fyndiq_export') {
            if ($product->isProductExportable()) {
                    if ($product->getIsExported()) {
                        _e('Exported', 'fyndiq');
                    } else {
                        _e('Not exported', 'fyndiq');
                    }
            } else {
                _e('Can\'t be exported', 'fyndiq');
            }
        }
    }



    public function my_admin_notice()
    {
        if ($this->checkCurrency()) {
            printf(
                '<div class="error"><p><strong>%s</strong>: %s %s</p></div>',
                __('Wrong Currency', 'fyndiq'),
                __('Fyndiq only works in EUR and SEK. change to correct currency. Current Currency:', 'fyndiq'),
                get_woocommerce_currency()
            );
        }
        if ($this->checkCountry()) {
            printf(
                '<div class="error"><p><strong>%s</strong>: %s %s</p></div>',
                __('Wrong Country', 'fyndiq'),
                __('Fyndiq only works in Sweden and Germany. change to correct country. Current Country:', 'fyndiq'),
                WC()->countries->get_base_country()
            );
        }
        if ($this->checkCredentials()) {
            $url = admin_url('admin.php?page=wc-settings&tab=wcfyndiq');
            printf(
                '<div class="error"><p><strong>%s</strong>: %s <a href="%s">%s</a></p></div>',
                __('Fyndiq Credentials', 'fyndiq'),
                __('You need to set Fyndiq Credentials to make it work. Do it in ', 'fyndiq'),
                $url,
                __('Woocommerce Settings > Fyndiq', 'fyndiq')
            );
        }
        if (isset($_SESSION[self::NOTICES])) {
            $notices = $_SESSION[self::NOTICES];
            foreach ($notices as $type => $noticegroup) {
                $class = 'update' === $type ? 'updated' : $type;
                echo '<div class="fn_message '.$class.'">';
                echo '<strong>'.__('Fyndiq Validations', 'fyndiq').'</strong>';
                echo '<ul>';
                foreach ($noticegroup as $notice) :
                    echo '<li>'.wp_kses($notice, wp_kses_allowed_html('post')).'</li>';
                endforeach;
                echo '</ul>';
                echo '<p>'.__('The product will not be exported to Fyndiq until these validations are fixed.', 'fyndiq') . '</p>';
                echo '</div>';
            }
            unset($_SESSION[self::NOTICES]);
        }
    }



    /**
     *
     * Adds bulk actions to the dropdown by reading array and generating relevant JS
     *
     */
    public function fyndiq_add_bulk_action()
    {
        global $post_type;

        //Define bulk actions for the various page types
        $bulkActionArray = array(
            'product' => array(
                'fyndiq_export' => __('Export to Fyndiq', 'fyndiq'),
                'fyndiq_no_export' => __('Remove from Fyndiq', 'fyndiq'),
            ),
            'shop_order' => array(
                'fyndiq_delivery' => __('Get Fyndiq Delivery Note', 'fyndiq'),
                'fyndiq-order-import' => __('Import From Fyndiq', 'fyndiq'),
                'fyndiq_handle_order' => __('Mark order(s) as handled', 'fyndiq'),
                'fyndiq_unhandle_order' => __('Mark order(s) as not handled', 'fyndiq')
            )
        );


        //We need this JS header in any case. Initialises output var too. TODO: why is the IDE marking this as wrong?
        $scriptOutput = '<script type="text/javascript">jQuery(document).ready(function () {';


        //Goes through the corresponding array for the page type and writes JS needed for dropdown
        if (isset($bulkActionArray[$post_type])) {
            foreach ($bulkActionArray[$post_type] as $key => $value) {
                $scriptOutput .= "jQuery('<option>').val('$key').text('$value').appendTo('select[name=\"action\"]');
                              jQuery('<option>').val('$key').text('$value').appendTo('select[name=\"action2\"]');";
            }
        }


        //This adds a button for importing stuff from fyndiq TODO: ask about this - it probably shouldn't be there
        //TODO: This should not rely on a translatable string
        switch ($post_type) {
            case 'shop_order': {
                if ($this->ordersEnabled()) {
                    $scriptOutput .= "if( jQuery('.wrap h2').length && jQuery(jQuery('.wrap h2')[0]).text() != 'Filter posts list' ) {
                                        jQuery(jQuery('.wrap h2')[0]).append(\"<a href='#' id='fyndiq-order-import' class='add-new-h2'>" .
                        $bulkActionArray[$post_type]['fyndiq-order-import'] . "</a>\");
                                    } else if (jQuery('.wrap h1').length ){
                                        jQuery(jQuery('.wrap h1')[0]).append(\"<a href='#' id='fyndiq-order-import' class='page-title-action'>" .
                        $bulkActionArray[$post_type]['fyndiq-order-import'] . "</a>\");
                                    }";
                }
                }
                break;
        }

        //We also need this footer in all cases too
        $scriptOutput .= "});</script>";

        $this->fmOutput->output($scriptOutput);
    }


    /**
     *
     * This function acts as a dispatcher, taking various actions and routing them to the appropriate function
     * @todo get all bulk actions to use the dispatcher
     *
     */
    public function fyndiq_bulk_action_dispatcher()
    {
        switch ($this->getAction('WP_Posts_List_Table')) {
            case 'fyndiq_handle_order':
                $this->fyndiq_order_handle_bulk_action(1);
                break;
            case 'fyndiq_unhandle_order':
                $this->fyndiq_order_handle_bulk_action(0);
                break;
            default:
                break;
        }
    }


    /**
     * Function that handles bulk actions related to setting order handling status
     *
     * @param bool $markStatus - whether the orders are handled or not
     * @throws Exception
     */
    private function fyndiq_order_handle_bulk_action($markStatus)
    {
        if (!empty($this->getRequestPost())) {
            $posts = array();
            foreach ($this->getRequestPost() as $post) {
                $dataRow = array(
                    'id' => $post,
                    'marked' => $markStatus
                );

                $posts[$post][] = $dataRow;
            }
            FmOrder::setIsHandledBulk($posts);
        }
    }


    public function do_bulk_action_messages()
    {
        if (isset($_SESSION['bulkMessage']) && $GLOBALS['pagenow'] === 'edit.php') {
            $this->fmOutput->output('<div class="updated"><p>' . $_SESSION['bulkMessage'] . '</p></div>');
            unset($_SESSION['bulkMessage']);
        }
    }

    public function fyndiq_product_export_bulk_action()
    {

        //If there is no action, we're done.
        if (!$this->getAction('WP_Posts_List_Table')) {
            return false;
        }

        switch ($this->getAction('WP_Posts_List_Table')) {
            case 'fyndiq_export':
                $report_action = 'fyndiq_exported';
                $exporting = true;
                break;
            case 'fyndiq_no_export':
                $report_action = 'fyndiq_removed';
                $exporting = false;
                break;
            default:
                throw new Exception('Unexpected bulk action value: ' . $this->getAction('WP_Posts_List_Table'));
        }

        $changed = 0;
        $post_ids = array();
        $posts = $this->getRequestPost();
        if (!is_null($posts)) {
            if ($exporting) {
                foreach ($posts as $post_id) {
                    $product = new FmProduct($post_id);
                    if ($product->isProductExportable()) {
                        $product->setIsExported(true);
                        $post_ids[] = $post_id;
                        $changed++;
                    }
                }
            } else {
                foreach ($posts as $post_id) {
                    $product = new FmProduct($post_id);
                    if ($product->isProductExportable()) {
                        $product->setIsExported(false);
                        $post_ids[] = $post_id;
                        $changed++;
                    }
                }
            }
        }

        return $this->bulkRedirect($report_action, $changed, $post_ids);
    }

    public function fyndiq_bulk_notices()
    {
        global $post_type, $pagenow;

        if ($pagenow == 'edit.php' && isset($_REQUEST['fyndiq_removed']) && (int)$_REQUEST['fyndiq_removed']) {
            $message = sprintf(
                _n(
                    'Products removed from Fyndiq.',
                    '%s products removed from Fyndiq.',
                    $_REQUEST['fyndiq_removed']
                ),
                number_format_i18n($_REQUEST['fyndiq_removed'])
            );
            $this->fmOutput->output('<div class="updated"><p>' . $message . '</p></div>');
        }
        if ($pagenow == 'edit.php' && isset($_REQUEST['fyndiq_exported']) && (int)$_REQUEST['fyndiq_exported']) {
            $message = sprintf(
                _n(
                    'Products exported to Fyndiq.',
                    '%s products exported to Fyndiq.',
                    $_REQUEST['fyndiq_exported']
                ),
                number_format_i18n($_REQUEST['fyndiq_exported'])
            );
            $this->fmOutput->output('<div class="updated"><p>' . $message . '</p></div>');
        }
    }

    public function fyndiq_order_delivery_note_bulk_action()
    {
        try {
            $wp_list_table = _get_list_table('WP_Posts_List_Table');
            $action = $wp_list_table->current_action();

            switch ($action) {
                case 'fyndiq_delivery':
                    break;
                default:
                    return;
            }

            $orders = array(
                'orders' => array()
            );
            if (!isset($_REQUEST['post'])) {
                throw new Exception(__('Pick at least one Order', 'fyndiq'));
            }
            foreach ($_REQUEST['post'] as $order) {
                $meta = get_post_custom($order);
                if (isset($meta['fyndiq_id']) && isset($meta['fyndiq_id'][0]) && $meta['fyndiq_id'][0] != '') {
                    $orders['orders'][] = array('order' => intval($meta['fyndiq_id'][0]));
                }
            }

            $ret = FmHelpers::callApi('POST', 'delivery_notes/', $orders, true);

            if ($ret['status'] == 200) {
                $fileName = 'delivery_notes-' . implode('-', $_REQUEST['post']) . '.pdf';
                $file = fopen('php://temp', 'wb+');
                fputs($file, $ret['data']);
                $this->fmOutput->streamFile($file, $fileName, 'application/pdf', strlen($ret['data']));
                fclose($file);
            } else {
                $sendback = add_query_arg(
                    array('post_type' => 'shop_order', $report_action => $changed, 'ids' => join(',', $post_ids)),
                    ''
                );
                wp_redirect($sendback);
            }
        } catch (Exception $e) {
            $sendback = add_query_arg(
                array('post_type' => 'shop_order', $report_action => $changed, 'ids' => join(',', $post_ids), 'error' => $e->getMessage()),
                ''
            );
            wp_redirect($sendback);
        }
        exit();
    }

    public function notification_handle()
    {
        define('DOING_AJAX', true);
        if (isset($_GET['event'])) {
            $event = $_GET['event'];
            $eventName = $event ? 'notice_' . $event : false;
            if ($eventName) {
                if ($eventName[0] != '_' && method_exists($this, $eventName)) {
                    $this->checkToken();
                    return $this->$eventName();
                }
            }
        }
        $this->fmOutput->showError(400, 'Bad Request', '400 Bad Request');
        wp_die();
    }

    private function notice_order_created()
    {
        if (!$this->ordersEnabled()) {
            wp_die('Orders is disabled');
        }
        $order_id = $_GET['order_id'];
        $orderId = is_numeric($order_id) ? intval($order_id) : 0;
        if ($orderId > 0) {
            try {
                $ret = FmHelpers::callApi('GET', 'orders/' . $orderId . '/');

                $fyndiqOrder = $ret['data'];

                if (!FmOrder::orderExists($fyndiqOrder->id)) {
                    FmOrder::createOrder($fyndiqOrder);
                }
            } catch (Exception $e) {
                $this->setOrderError();
                $this->fmOutput->showError(500, 'Internal Server Error', $e);
            }

            wp_die();
        }
    }

    private function notice_debug()
    {
        FyndiqUtils::debugStart();
        FyndiqUtils::debug('USER AGENT', FmHelpers::get_user_agent());
        $languageId = WC()->countries->get_base_country();
        FyndiqUtils::debug('language', $languageId);
        FyndiqUtils::debug('taxonomy', $this->getAllTerms());
        $return = $this->fmExport->feedFileHandling();
        $result = file_get_contents($this->filepath);
        FyndiqUtils::debug('$result', $result, true);
        FyndiqUtils::debugStop();
        wp_die();
    }

    private function notice_ping()
    {
        $this->fmOutput->flushHeader('OK');

        $locked = false;
        $lastPing = get_option('wcfyndiq_ping_time');
        $lastPing = $lastPing ? $lastPing : false;
        $locked = $lastPing && $lastPing > strtotime('15 minutes ago');
        if (!$locked) {
            update_option('wcfyndiq_ping_time', time());
            try {
                $this->fmExport->feedFileHandling();
                $this->update_product_info();
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }
        wp_die();
    }

    private function notice_info()
    {

        $info = FyndiqUtils::getInfo(
            FmHelpers::PLATFORM,
            WC_VERSION,
            FmHelpers::get_plugin_version(),
            FmHelpers::COMMIT
        );
        $this->fmOutput->outputJSON($info);
        wp_die();
    }



    private function update_product_info()
    {
        $productFetch = new FmProductFetch();
        $productFetch->getAll();
    }

    public function getAction($table)
    {
        $wp_list_table = _get_list_table($table);

        return $wp_list_table->current_action();
    }

    public function getRequestPost()
    {
        return isset($_REQUEST['post']) ? $_REQUEST['post'] : null;
    }

    public function returnAndDie($return)
    {
        die($return);
    }

    public function bulkRedirect($report_action, $changed, $post_ids)
    {
        $sendback = add_query_arg(
            array('post_type' => 'product', $report_action => $changed, 'ids' => join(',', $post_ids)),
            ''
        );
        wp_redirect($sendback);
        return exit();
    }

    public function checkCurrency()
    {
        $currency = get_woocommerce_currency();
        return !in_array($currency, FyndiqUtils::$allowedCurrencies);
    }

    public function checkCountry()
    {
        $country = WC()->countries->get_base_country();
        return !in_array($country, FyndiqUtils::$allowedMarkets);
    }

    public function checkCredentials()
    {
        $username = get_option('wcfyndiq_username');
        $token = get_option('wcfyndiq_apitoken');

        return (empty($username) || empty($token));
    }

    function check_page()
    {
        echo "<h1>".__('Fyndiq Checker Page', 'fyndiq')."</h1>";
        echo "<p>".__('This is a page to check all the important requirements to make the Fyndiq work.', 'fyndiq')."</p>";

        echo "<h2>".__('File Permission', 'fyndiq')."</h2>";
        echo $this->probe_file_permissions();

        echo "<h2>".__('Classes', 'fyndiq')."</h2>";
        echo $this->probe_module_integrity();

        echo "<h2>".__('API Connection', 'fyndiq')."</h2>";
        echo $this->probe_connection();

        echo "<h2>".__('Installed Plugins', 'fyndiq')."</h2>";
        echo $this->probe_plugins();
    }


    private function checkToken()
    {
        $pingToken = get_option('wcfyndiq_ping_token');

        $token = isset($_GET['pingToken']) ? $_GET['pingToken'] : null;

        if (is_null($token) || $token != $pingToken) {
            $this->fmOutput->showError(400, 'Bad Request', '400 Bad Request');
            wp_die();
        }
    }

    protected function probe_file_permissions()
    {
        $messages = array();
        $testMessage = time();
        try {
            $fileName = $this->filepath;
            $exists =  file_exists($fileName) ?
                __('exists', 'fyndiq') :
                __('does not exist', 'fyndiq');
            $messages[] = sprintf(__('Feed file name: `%s` (%s)', 'fyndiq'), $fileName, $exists);
            $tempFileName = FyndiqUtils::getTempFilename(dirname($fileName));
            if (dirname($tempFileName) !== dirname($fileName)) {
                throw new Exception(sprintf(
                    __('Cannot create file. Please make sure that the server can create new files in `%s`', 'fyndiq'),
                    dirname($fileName)
                ));
            }
            $messages[] = sprintf(__('Trying to create temporary file: `%s`', 'fyndiq'), $tempFileName);
            $file = fopen($tempFileName, 'w+');
            if (!$file) {
                throw new Exception(sprintf(__('Cannot create file: `%s`', 'fyndiq'), $tempFileName));
            }
            fwrite($file, $testMessage);
            fclose($file);
            $content = file_get_contents($tempFileName);
            if ($testMessage == file_get_contents($tempFileName)) {
                $messages[] = sprintf(__('File `%s` successfully read.', 'fyndiq'), $tempFileName);
            }
            FyndiqUtils::deleteFile($tempFileName);
            $messages[] = sprintf(__('Successfully deleted temp file `%s`', 'fyndiq'), $tempFileName);
            return implode('<br />', $messages);
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            return implode('<br />', $messages);
        }
    }

    protected function probe_module_integrity()
    {
        $messages = array();
        $missing = array();
        $checkClasses = array(
            'FyndiqAPI',
            'FyndiqAPICall',
            'FyndiqCSVFeedWriter',
            'FyndiqFeedWriter',
            'FyndiqOutput',
            'FyndiqPaginatedFetch',
            'FyndiqUtils',
            'FmHelpers'
        );
        try {
            foreach ($checkClasses as $className) {
                if (class_exists($className)) {
                    $messages[] = sprintf(__('Class `%s` is found.', 'fyndiq'), $className);
                    continue;
                }
                $messages[] = sprintf(__('Class `%s` is NOT found.', 'fyndiq'), $className);
            }
            if ($missing) {
                throw new Exception(sprintf(
                    __('Required classes `%s` are missing.', 'fyndiq'),
                    implode(',', $missing)
                ));
            }
            return implode('<br />', $messages);
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            return implode('<br />', $messages);
        }
    }
    protected function probe_connection()
    {
        $messages = array();
        try {
            try {
                FmHelpers::callApi('GET', 'settings/');
            } catch (Exception $e) {
                if ($e instanceof FyndiqAPIAuthorizationFailed) {
                    throw new Exception(__('Module is not authorized.', 'fyndiq'));
                }
            }
            $messages[] = __('Connection to Fyndiq successfully tested', 'fyndiq');
            return implode('<br />', $messages);
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            return implode('<br />', $messages);
        }
    }

    protected function probe_plugins()
    {
        $all_plugins = get_plugins();
        $installed_plugin = array();
        foreach ($all_plugins as $plugin) {
            $installed_plugin[] = $plugin['Name'] . ' v. ' . $plugin['Version'];
        }
        return implode('<br />', $installed_plugin);
    }

    private function ordersEnabled()
    {
        $setting = get_option('wcfyndiq_order_enable');
        if (!isset($setting) || $setting == false) {
            return true;
        }
        return ($setting == self::ORDERS_ENABLE);
    }



    private function getAllTerms()
    {
        $attributes = array('' => '');
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if ($attribute_taxonomies) {
            foreach ($attribute_taxonomies as $tax) {
                $attributes[$tax->attribute_name] = $tax->attribute_label;
            }
        }

        // Get products attributes
        // This can be set per product and some product can have no attributes at all
        global $wpdb;
        $results = $wpdb->get_results('SELECT * FROM wp_postmeta WHERE meta_key = "_product_attributes" AND meta_value != "a:0:{}"', OBJECT);
        foreach ($results as $meta) {
            $data = unserialize($meta->meta_value);
            foreach ($data as $key => $attribute) {
                $attributes[$key] = $attribute['name'];
            }
        }
        return $attributes;
    }
}

<?php
//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class WC_Fyndiq
{
    private $filePath = null;
    private $fmWoo = null;
    private $fmOutput = null;
    private $fmExport = null;

    const NOTICES = 'fyndiq_notices';

    /** @var string key value for Fyndiq order column */
    const ORDERS = 'fyndiq_order';

    /** @var string key value for Fyndiq product column */
    const EXPORT = 'fyndiq_export_column';

    /** @var string the key for the bulk action in export */
    const EXPORT_HANDLE = 'fyndiq_handle_export';

    /** @var string the key for the bulk action in not export */
    const EXPORT_UNHANDLE = 'fyndiq_handle_no_export';

    /** @var string the key for mark imported orders as handled */
    const ORDER_HANDLE = 'fyndiq_handle_order';

    /** @var string the key for mark imported orders as not handled */
    const ORDER_UNHANDLE = 'fyndiq_unhandle_order';

    /** @var string the key for delivery note action */
    const DELIVERY_NOTE = 'fyndiq_delivery';

    /** @var string the key for order import action */
    const ORDER_IMPORT = 'order_import';

    const ORDERS_DISABLE = 1;
    const ORDERS_ENABLE = 2;

    const SETTING_TAB_PRIORITY = 50;

    const TEXT_DOMAIN = 'fyndiq';

    public function __construct($fmWoo, $fmOutput)
    {
        $this->fmWoo = $fmWoo;
        $this->fmOutput = $fmOutput;

        $this->currencies = array_combine(
            FyndiqUtils::$allowedCurrencies,
            FyndiqUtils::$allowedCurrencies
        );

        //Register class hooks as early as possible
        $this->fmWoo->addAction('wp_loaded', array(&$this, 'initiateClassHooks'));

        //Load locale in init
        $this->fmWoo->addAction('init', array(&$this, 'localeLoad'));

        // called only after woocommerce has finished loading
        $this->fmWoo->addAction('init', array(&$this, 'woocommerceLoaded'), 250);

        $uploadDir = $this->fmWoo->wpUploadDir();
        $this->filePath = $uploadDir['basedir'] . '/fyndiq-feed.csv';

        $this->fmUpdate = new FmUpdate();
        $this->fmExport = new FmExport($this->filePath, $this->fmOutput);
    }

    public function localeLoad()
    {
        // Localization
        return $this->fmWoo->loadPluginTextdomain(
            self::TEXT_DOMAIN,
            false,
            dirname($this->fmWoo->pluginBasename(__FILE__)) . '/translations/'
        );
    }

    public function initiateClassHooks()
    {
        FmError::setHooks();
        FmProduct::setHooks();
        FmField::setHooks();
    }

    /**
     * Take care of anything that needs WooCommerce to be loaded.
     * For instance, if you need access to the $woocommerce global
     */
    public function woocommerceLoaded()
    {
        //Settings
        $this->fmWoo->addFilter(
            'woocommerce_settings_tabs_array',
            array(&$this, 'fyndiq_add_settings_tab'),
            self::SETTING_TAB_PRIORITY
        );
        $this->fmWoo->addAction(
            'woocommerce_settings_tabs_wcfyndiq',
            array(&$this, 'settings_tab')
        );
        $this->fmWoo->addAction(
            'woocommerce_update_options_wcfyndiq',
            array(&$this, 'update_settings')
        );

        //products
        $this->fmWoo->addAction(
            'woocommerce_process_shop_order_meta',
            array(&$this, 'fyndiq_order_handled_save')
        );

        $this->fmWoo->addAction(
            'woocommerce_admin_order_data_after_order_details',
            array(&$this, 'fyndiq_add_order_field')
        );
        $this->fmWoo->addAction(
            'woocommerce_product_write_panel_tabs',
            array(&$this, 'fyndiq_product_tab')
        );


        //product list
        $this->fmWoo->addFilter(
            'manage_edit-product_columns',
            array(&$this, 'fyndiq_product_add_column')
        );
        $this->fmWoo->addAction(
            'manage_product_posts_custom_column',
            array(&$this, 'fyndiq_product_column_export'),
            5,
            2
        );
        $this->fmWoo->addFilter(
            'manage_edit-product_sortable_columns',
            array(&$this, 'fyndiq_product_column_sort')
        );
        $this->fmWoo->addAction('pre_get_posts', array(&$this, 'fyndiqProductColumnSortBy'));
        $this->fmWoo->addAction('admin_notices', array(&$this, 'fyndiqBulkNotices'));
        $this->fmWoo->addAction('admin_notices', array(&$this, 'doBulkActionMessages'));


        //order list
        if ($this->ordersEnabled()) {
            $this->fmWoo->addFilter('manage_edit-shop_order_columns', array(&$this, 'fyndiq_order_add_column'));
            $this->fmWoo->addAction(
                'manage_shop_order_posts_custom_column',
                array(&$this, 'fyndiq_order_column'),
                5,
                2
            );
            $this->fmWoo->addFilter(
                'manage_edit-shop_order_sortable_columns',
                array(&$this, 'fyndiq_order_column_sort')
            );
        }

        //bulk action
        //Inserts the JS for the appropriate dropdown items
        $this->fmWoo->addAction('admin_footer-edit.php', array(&$this, 'fyndiq_add_bulk_action'));

        //Dispatcher for different bulk actions
        $this->fmWoo->addAction('load-edit.php', array(&$this, 'fyndiq_bulk_action_dispatcher'));

        //add_action('post_submitbox_misc_actions', array( &$this, 'fyndiq_order_edit_action'));
        $this->fmWoo->addAction('add_meta_boxes', array(&$this, 'fyndiqOrderMetaBoxes'));

        //notice for currency check
        $this->fmWoo->addAction('admin_notices', array(&$this, 'my_admin_notice'));

        //Checker Page
        $this->fmWoo->addAction('admin_menu', array(&$this, 'fyndiqAddMenu'));
        $this->fmWoo->addFilter(
            'plugin_action_links_' . $this->fmWoo->pluginBasename(dirname(__FILE__).'/woocommerce-fyndiq.php'),
            array(&$this, 'fyndiqActionLinks')
        );

        //index
        $this->fmWoo->addAction('load-index.php', array($this->fmUpdate, 'updateNotification'));

        //orders
        $this->fmWoo->addAction('load-edit.php', array(&$this, 'fyndiq_show_order_error'));

        // admin javascripts
        add_action('admin_enqueue_scripts', array(&$this, 'fyndiqLoadJavascript'));

        //functions
        if (isset($_GET['fyndiq_feed'])) {
            $this->fmExport->generate_feed();
        }
        if (isset($_GET['fyndiq_orders'])) {
            FmOrder::generateOrders();
        }
        if (isset($_GET['fyndiq_notification'])) {
            $this->fmWoo->setDoingAJAX($value);
            $this->handleNotification($_GET);
            $this->fmWoo->wpDie();
        }
    }

    public function fyndiqAddMenu()
    {
        $this->fmWoo->addSubmenuPage(
            null,
            'Fyndiq Checker Page',
            'Fyndiq',
            'manage_options',
            'fyndiq-check',
            array(&$this, 'check_page')
        );
    }

    public function fyndiqActionLinks($links)
    {
        $checkUrl = $this->fmWoo->escURL(
            $this->fmWoo->getAdminURL(null, 'admin.php?page=fyndiq-check')
        );
        $settingUrl = $this->fmWoo->escURL(
            $this->fmWoo->getAdminURL(
                null,
                'admin.php?page=wc-settings&tab=products&section=wcfyndiq'
            )
        );
        return array(
            '<a href="'. $settingUrl . '">' . $this->fmWoo->__('Settings') . '</a>',
            '<a href="'. $checkUrl . '">' . $this->fmWoo->__('Fyndiq Check') . '</a>'
        );
    }

    function fyndiqLoadJavascript()
    {

        $script = <<<EOS
        <script type="text/javascript">
            var wordpressurl = '%s';
            var trans_error = '%s';
            var trans_loading = '%s';
            var trans_done = '%s';
        </script>
EOS;
        printf(
            $script,
            get_site_url(),
            __('Error!'),
            __('Loading') . '...',
            __('Done')
        );

        if ($this->ordersEnabled()) {
            wp_enqueue_script('fyndiq_order', plugins_url('/js/order-import.js', __FILE__), array('jquery'), null);
        }
    }

    public function fyndiqOrderMetaBoxes()
    {
        $meta = $this->fmWoo->getPostCustom(FmOrder::getWordpressCurrentPostID());
        if (array_key_exists('fyndiq_delivery_note', $meta) &&
            isset($meta['fyndiq_delivery_note'][0]) &&
            $meta['fyndiq_delivery_note'][0] != ''
        ) {
            return $this->fmWoo->addMetaBox(
                'woocommerce-order-fyndiq-delivery-note',
                $this->fmWoo->__('Fyndiq'),
                array(&$this, 'order_meta_box_delivery_note'),
                'shop_order',
                'side',
                'default'
            );
        }
        return false;
    }

    public function order_meta_box_delivery_note()
    {
        $meta = $this->fmWoo->getPostCustom(FmOrder::getWordpressCurrentPostID());
        $this->fmOutput->output(
            sprintf(
                '<a href="%s" class="button button-primary">%s</a>',
                $meta['fyndiq_delivery_note'][0],
                $this->fmWoo->__('Get Fyndiq Delivery Note')
            )
        );
    }

    public function settings_tab()
    {
        $this->fmWoo->woocommerceAdminFields($this->fyndiq_all_settings());
    }

    public function fyndiq_all_settings()
    {

        //Get options for attributes
        $attributes = $this->getAllTerms();

        /**
         * Check the current section is what we want
         **/
        $settings = array();

        $settings[] = array(
            'name'     => $this->fmWoo->__('Fyndiq'),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_settings_wcfyndiq_section_title'
        );

        // Add Title to the Settings
        $settings[] = array(
            'name' => $this->fmWoo->__('General Settings'),
            'type' => 'title',
            'desc' => $this->fmWoo->__('The following options are used to configure Fyndiq'),
            'id' => 'wcfyndiq'
        );

        // Add second text field option
        $settings[] = array(
            'name' => $this->fmWoo->__('Username'),
            'desc_tip' => $this->fmWoo->__('This is the username you use for login on Fyndiq Merchant'),
            'id' => 'wcfyndiq_username',
            'type' => 'text',
            'desc' => $this->fmWoo->__('Must be your username'),
        );

        // Add second text field option
        $settings[] = array(
            'name' => $this->fmWoo->__('API-token'),
            'desc_tip' => $this->fmWoo->__('This is the API v2 Token on Fyndiq'),
            'id' => 'wcfyndiq_apitoken',
            'type' => 'text',
            'desc' => $this->fmWoo->__('Must be API v2 token'),
        );


        //Price Percentage
        $settings[] = array(
            'name' => $this->fmWoo->__('Global Price Percentage'),
            'desc_tip' => $this->fmWoo->__(
                'The percentage that will be removed from the price when sending to Fyndiq.'
            ),
            'id' => 'wcfyndiq_price_percentage',
            'type' => 'text',
            'default' => '10',
            'desc' => $this->fmWoo->__('Can be 0 if the price should be the same as in your shop.'),
        );

        //Price Discount
        $settings[] = array(
            'name' => $this->fmWoo->__('Global Price Discount'),
            'desc_tip' => $this->fmWoo->__(
                'The amount that will be removed from the price when sending to Fyndiq.'
            ),
            'id' => 'wcfyndiq_price_discount',
            'type' => 'text',
            'default' => '0',
            'desc' => $this->fmWoo->__('Can be 0 if the price should not change'),
        );

        if (isset($_GET['set_sku'])) {
            // Add SKU picker
            $settings[] = array(
                'name' => $this->fmWoo->__('Reference to be in use'),
                'desc_tip' => $this->fmWoo->__(
                    'If you have multi SKU as in variations changing this will make it work better'
                ),
                'id' => 'wcfyndiq_reference_picker',
                'type' => 'select',
                'options' => array(
                    FmExport::REF_SKU => $this->fmWoo->__('SKU'),
                    FmExport::REF_ID => $this->fmWoo->__('Product and Article ID'),
                ),
                'desc' => $this->fmWoo->__('If this value is changed, products already existing on Fyndiq will be removed and uploaded again and orders might not be able to be imported with old SKU.'),
            );
        }

        // Add currency setting
        $settings[] = array(
            'name' => $this->fmWoo->__('Used Currency'),
            'desc_tip' => $this->fmWoo->__('Choose currency to be used for Fyndiq.'),
            'id' => 'wcfyndiq_currency',
            'type' => 'select',
            'options' => $this->currencies,
            'desc' => $this->fmWoo->__('This must be picked accurate'),
        );

        //Minimum Quantity limit
        $settings[] = array(
            'name' => $this->fmWoo->__('Minimum Quantity Limit'),
            'desc_tip' => $this->fmWoo->__(
                'This quantity will be reserved by you and will be removed from the quantity that is sent to Fyndiq.'
            ),
            'id' => 'wcfyndiq_quantity_minimum',
            'type' => 'text',
            'default' => '0',
            'desc' => $this->fmWoo->__('Stay on 0 if you want to send all stock to Fyndiq.'),
        );

        // Add Description picker
        $settings[] = array(
            'name' => $this->fmWoo->__('Enable Orders'),
            'desc_tip' => $this->fmWoo->__('This will disable all order logic for Fyndiq'),
            'id' => 'wcfyndiq_order_enable',
            'type' => 'select',
            'options' => array(
                self::ORDERS_ENABLE => $this->fmWoo->__('Enable'),
                self::ORDERS_DISABLE => $this->fmWoo->__('Disable'),
            ),
            'desc' => $this->fmWoo->__('Default is to have orders enabled')
        );


        // Add order status setting
        $settings[] = array(
            'name' => $this->fmWoo->__('Order Status'),
            'desc_tip' => $this->fmWoo->__(
                'When a order is imported from fyndiq, this status will be applied.'
            ),
            'id' => 'wcfyndiq_create_order_status',
            'type' => 'select',
            'options' => array(
                'completed' => 'completed',
                'processing' => 'processing',
                'pending' => 'pending',
                'on-hold' => 'on-hold'
            ),
            'desc' => $this->fmWoo->__('This must be picked accurate')
        );

        $settings[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end',
        );

        $settings[] = array(
            'name' => $this->fmWoo->__('Field Mappings'),
            'type' => 'title',
            'desc' => '',
            'id' => 'wc_settings_wcfyndiq_section_title'
        );


        // Add Description picker
        $settings[] = array(
            'name' => $this->fmWoo->__('Description to use'),
            'desc_tip' => $this->fmWoo->__(
                'Set how you want your description to be exported to Fyndiq.'
            ),
            'id' => 'wcfyndiq_description_picker',
            'type' => 'select',
            'options' => array(
                FmExport::DESCRIPTION_LONG => $this->fmWoo->__('Long Description'),
                FmExport::DESCRIPTION_SHORT => $this->fmWoo->__('Short Description'),
                FmExport::DESCRIPTION_SHORT_LONG =>
                    $this->fmWoo->__('Short and Long Description'),
            ),
            'desc' => $this->fmWoo->__('Default is Long Description'),
        );

        // Map Field for EAN
        $settings[] = array(
            'name' => $this->fmWoo->__('EAN'),
            'desc_tip' => $this->fmWoo->__('EAN'),
            'id' => 'wcfyndiq_field_map_ean',
            'type' => 'select',
            'options' => $attributes,
            'desc' => $this->fmWoo->__('This must be picked accurate'),
        );

        // Map Field for ISBN
        $settings[] = array(
            'name' => $this->fmWoo->__('ISBN'),
            'desc_tip' => $this->fmWoo->__('ISBN'),
            'id' => 'wcfyndiq_field_map_isbn',
            'type' => 'select',
            'options' => $attributes,
            'desc' => $this->fmWoo->__('This must be picked accurate'),
        );

        // Map Field for MPN
        $settings[] = array(
            'name' => $this->fmWoo->__('MPN'),
            'desc_tip' => $this->fmWoo->__('MPN'),
            'id' => 'wcfyndiq_field_map_mpn',
            'type' => 'select',
            'options' => $attributes,
            'desc' => $this->fmWoo->__('This must be picked accurate'),
        );

        // Map Field for Brand
        $settings[] = array(
            'name' => $this->fmWoo->__('Brand'),
            'desc_tip' => $this->fmWoo->__('Brand'),
            'id' => 'wcfyndiq_field_map_brand',
            'type' => 'select',
            'options' => $attributes,
            'desc' => $this->fmWoo->__('This must be picked accurate'),
        );

        $settings[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end'
        );

        return apply_filters('wc_settings_tab_wcfyndiq', $settings);
    }

    public function fyndiq_add_settings_tab($settings_tabs)
    {
        $settings_tabs['wcfyndiq'] = $this->fmWoo->__('Fyndiq');
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
        //Generate ping token
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

    //Hooked to woocommerce_product_write_panel_tabs
    public function fyndiq_product_tab()
    {
        $this->fmOutput->output(
            sprintf(
                '<li class="fyndiq_tab"><a href="#fyndiq_tab">%s</a></li>',
                $this->fmWoo->__('Fyndiq')
            )
        );
    }

    /**
     *
     * This is the hooked function for fields on the order pages
     *
     */
    public function fyndiq_add_order_field()
    {
        $order = new FmOrder(FmOrder::getWordpressCurrentPostID());

        FmField::fyndiq_generate_field(FmOrder::FYNDIQ_HANDLED_ORDER_META_FIELD, array(
            'type' => 'checkbox',
            'class' => array('input-checkbox'),
            'label' => $this->fmWoo->__('Order handled'),
            'description' => $this->fmWoo->__('Report this order as handled to Fyndiq'),
        ), (bool)$order->getIsHandled());
    }

    public function fyndiq_show_order_error()
    {
        if (isset($_GET['post_type']) && $_GET['post_type'] == 'shop_order') {
            $error = $this->fmWoo->getOption('wcfyndiq_order_error');
            if ($error) {
                $this->fmWoo->addAction('admin_notices', array(&$this, 'fyndiq_show_order_error_notice'));
                update_option('wcfyndiq_order_error', false);
            }
        }
    }

    public function fyndiq_show_order_error_notice()
    {
        $this->fmOutput->output(sprintf(
            '<div class="error"><p>%s</p></div>',
            $this->fmWoo->__('Some Fyndiq Orders failed to be imported, most likely due to stock or couldn\'t find product on Reference.')
        ));
    }

    public function fyndiq_show_setting_error_notice()
    {
        $this->fmOutput->output(sprintf(
            '<div class="error"><p>%s</p></div>',
            $this->fmWoo->__('Fyndiq credentials was wrong, try again.')
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
        $defaults[self::ORDERS] = $this->fmWoo->__('Fyndiq Order');
        return $defaults;
    }

    public function fyndiq_order_column($column, $orderId)
    {
        if ($column === self::ORDERS) {
            $fyndiq_order = $this->fmWoo->getPostMeta($orderId, 'fyndiq_id', true);
            if ($fyndiq_order != '') {
                $this->fmOutput->output($fyndiq_order);
            } else {
                $this->fmWoo->updatePostMeta($orderId, 'fyndiq_id', '-');
                $this->fmOutput->output('-');
            }
        }
    }

    //Hooked to manage_edit-shop_order_sortable_columns
    public function fyndiq_order_column_sort()
    {
        return array(
            self::ORDERS => self::ORDERS
        );
    }

    //TODO: find out how this function is called
    public function fyndiq_order_column_sort_by($query)
    {
        if (!$this->fmWoo->isAdmin()) {
            return;
        }
        $orderby = $query->get('orderby');
        if ($orderby === self::ORDERS) {
            $query->set('meta_key', 'fyndiq_id');
            $query->set('orderby', 'meta_value_integer');
        }
    }

    //Hooked function for adding columns to the products page (manage_edit-product_columns)
    public function fyndiq_product_add_column($defaults)
    {
        $defaults[self::EXPORT] = $this->fmWoo->__('Fyndiq');
        return $defaults;
    }

    public function fyndiq_product_column_sort()
    {
        return array(
            self::EXPORT => self::EXPORT,
        );
    }

    public function fyndiqProductColumnSortBy($query)
    {
        if (!$this->fmWoo->isAdmin()) {
            return;
        }
        $orderby = $query->get('orderby');
        if ($orderby === self::EXPORT) {
            $query->set('meta_key', '_fyndiq_export');
            $query->set('orderby', 'meta_value');
        }
    }

    public function fyndiq_product_column_export($column, $postId)
    {
        $product = new FmProduct($postId);

        if ($column == self::EXPORT) {
            if ($product->isProductExportable()) {
                if ($product->getIsExported()) {
                    _e('Exported');
                } else {
                    _e('Not exported');
                }
            } else {
                _e('Can\'t be exported');
            }
        }
    }


    public function my_admin_notice()
    {
        if ($this->checkCurrency()) {
            $this->fmOutput->output(sprintf(
                '<div class="error"><p><strong>%s</strong>: %s %s</p></div>',
                $this->fmWoo->__('Wrong Currency'),
                $this->fmWoo->__('Fyndiq only works in EUR and SEK. change to correct currency. Current Currency:'),
                $this->fmWoo->getWoocommerceCurrency()
            ));
        }
        if ($this->checkCountry()) {
            $this->fmOutput->output(sprintf(
                '<div class="error"><p><strong>%s</strong>: %s %s</p></div>',
                $this->fmWoo->__('Wrong Country'),
                $this->fmWoo->__('Fyndiq only works in Sweden and Germany. change to correct country. Current Country:'),
                $this->fmWoo->WC()->countries->get_base_country()
            ));
        }
        if ($this->checkCredentials()) {
            $url = admin_url('admin.php?page=wc-settings&tab=wcfyndiq');
            $this->fmOutput->output(sprintf(
                '<div class="error"><p><strong>%s</strong>: %s <a href="%s">%s</a></p></div>',
                $this->fmWoo->__('Fyndiq Credentials'),
                $this->fmWoo->__('You need to set Fyndiq Credentials to make it work. Do it in '),
                $url,
                $this->fmWoo->__('Woocommerce Settings > Fyndiq')
            ));
        }
        if (isset($_SESSION[self::NOTICES])) {
            $notices = $_SESSION[self::NOTICES];
            foreach ($notices as $type => $noticegroup) {
                $class = 'update' === $type ? 'updated' : $type;
                echo '<div class="fn_message '.$class.'">';
                echo '<strong>'.$this->fmWoo->__('Fyndiq Validations').'</strong>';
                echo '<ul>';
                foreach ($noticegroup as $notice) :
                    echo '<li>'.wp_kses($notice, wp_kses_allowed_html('post')).'</li>';
                endforeach;
                echo '</ul>';
                echo '<p>' . $this->fmWoo->__('The product will not be exported to Fyndiq until these validations are fixed.') . '</p>';
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
                self::EXPORT_HANDLE => $this->fmWoo->__('Export to Fyndiq'),
                self::EXPORT_UNHANDLE => $this->fmWoo->__('Remove from Fyndiq'),
            ),
            'shop_order' => array(
                self::DELIVERY_NOTE => $this->fmWoo->__('Get Fyndiq Delivery Note'),
                self::ORDER_IMPORT => $this->fmWoo->__('Import From Fyndiq'),
                self::ORDER_HANDLE => $this->fmWoo->__('Mark order(s) as handled'),
                self::ORDER_UNHANDLE => $this->fmWoo->__('Mark order(s) as not handled')
            )
        );

        $scriptOutput = '';

        //Goes through the corresponding array for the page type and writes JS needed for dropdown
        if (isset($bulkActionArray[$post_type])) {
            foreach ($bulkActionArray[$post_type] as $key => $value) {
                $scriptOutput .= "jQuery('<option>').val('$key').text('$value').appendTo('select[name=\"action\"]');
                    jQuery('<option>').val('$key').text('$value').appendTo('select[name=\"action2\"]');";
            }
        }

        //This adds a button for importing stuff from fyndiq TODO: ask about this - it probably shouldn't be there
        //TODO: This should not rely on a translatable string
        if ($post_type === 'shop_order' && $this->ordersEnabled()) {
            $scriptOutput .= sprintf(
                "if( jQuery('.wrap h2').length && jQuery(jQuery('.wrap h2')[0]).text() != 'Filter posts list' ) {
                    jQuery(jQuery('.wrap h2')[0]).append(\"<a href='#' id='%s' class='add-new-h2'>%s</a>\");
                } else if (jQuery('.wrap h1').length ){
                    jQuery(jQuery('.wrap h1')[0]).append(\"<a href='#' id='%s' class='page-title-action'>%s</a>\");
                }",
                self::ORDER_IMPORT,
                $bulkActionArray[$post_type][self::ORDER_IMPORT],
                self::ORDER_IMPORT,
                $bulkActionArray[$post_type][self::ORDER_IMPORT]
            );
        }

        if ($scriptOutput) {
            $script = sprintf(
                '<script type="text/javascript">jQuery(document).ready(function (){%s});</script>',
                $scriptOutput
            );

            $this->fmOutput->output($script);
        }
    }


    /**
     *
     * This function acts as a dispatcher, taking various actions and routing them to the appropriate function
     * @todo get all bulk actions to use the dispatcher
     *
     */
    public function fyndiq_bulk_action_dispatcher()
    {
        $action = $this->getAction('WP_Posts_List_Table');
        switch ($this->getAction('WP_Posts_List_Table')) {
            case self::ORDER_HANDLE:
                FmOrder::orderHandleBulkAction(true);
                break;
            case self::ORDER_UNHANDLE:
                FmOrder::orderHandleBulkAction(false);
                break;
            case self::DELIVERY_NOTE:
                FmOrder::deliveryNoteBulkaction();
                break;
            case self::EXPORT_HANDLE:
                FmProduct::productExportBulkAction(FmProduct::EXPORTED, $action);
                break;
            case self::EXPORT_UNHANDLE:
                FmProduct::productExportBulkAction(FmProduct::NOT_EXPORTED, $action);
                break;
            default:
                break;
        }
    }

    public function doBulkActionMessages()
    {
        if (isset($_SESSION['bulkMessage']) && $GLOBALS['pagenow'] === 'edit.php') {
            $this->fmOutput->output('<div class="updated"><p>' . $_SESSION['bulkMessage'] . '</p></div>');
            unset($_SESSION['bulkMessage']);
        }
    }

    public function fyndiqBulkNotices()
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

    /**
     * handleNotification handles notification calls
     * @param array $get $_GET array
     * @return bool
     */
    public function handleNotification($get)
    {
        if (isset($get['event'])) {
            switch ($get['event']) {
                case 'order_created':
                    return $this->orderCreated($get);
                case 'ping':
                    $this->checkToken($get);
                    return $this->ping();
                case 'debug':
                    $this->checkToken($get);
                    return $this->debug();
                case 'info':
                    $this->checkToken($get);
                    return $this->info();
            }
        }
        return $this->fmOutput->showError(400, 'Bad Request', '400 Bad Request');
    }

    /**
     * orderCreated handles new order notification
     * @param array $get $_GET array
     * @return bool
     */
    protected function orderCreated($get)
    {
        if (!$this->ordersEnabled()) {
            $this->fmWoo->wpDie('Orders is disabled');
        }
        $order_id = $get['order_id'];
        $orderId = is_numeric($order_id) ? intval($order_id) : 0;
        if ($orderId > 0) {
            try {
                $ret = FmHelpers::callApi('GET', 'orders/' . $orderId . '/');

                $fyndiqOrder = $ret['data'];

                if (!FmOrder::orderExists($fyndiqOrder->id)) {
                    return FmOrder::createOrder($fyndiqOrder);
                }
                return true;
            } catch (Exception $e) {
                FmOrder::setOrderError();
                $this->fmOutput->showError(500, 'Internal Server Error', $e);
            }
        }
        return false;
    }

    /**
     * debug handles the debug page
     * @return bool
     */
    protected function debug()
    {
        FyndiqUtils::debugStart();
        FyndiqUtils::debug('USER AGENT', FmHelpers::get_user_agent());
        $languageId = $this->fmWoo->WC()->countries->get_base_country();
        FyndiqUtils::debug('language', $languageId);
        FyndiqUtils::debug('taxonomy', $this->getAllTerms());
        $return = $this->fmExport->feedFileHandling();
        $result = file_get_contents($this->filePath);
        FyndiqUtils::debug('$result', $result, true);
        FyndiqUtils::debugStop();
        return true;
    }

    /**
     * ping handles ping notification
     * @return bool
     */
    protected function ping()
    {
        $this->fmOutput->flushHeader('OK');

        $locked = false;
        $lastPing = $this->fmWoo->getOption('wcfyndiq_ping_time');
        $lastPing = $lastPing ? $lastPing : false;
        $locked = $lastPing && $lastPing > strtotime('15 minutes ago');
        if (!$locked) {
            update_option('wcfyndiq_ping_time', time());
            try {
                $this->fmExport->feedFileHandling();
            } catch (Exception $e) {
                error_log($e->getMessage());
                return false;
            }
        }
        return true;
    }

    /**
     * info handles information report
     * @return bool
     */
    protected function info()
    {
        $info = FyndiqUtils::getInfo(
            FmHelpers::PLATFORM,
            FmHelpers::get_woocommerce_version(),
            FmHelpers::get_plugin_version(),
            FmHelpers::COMMIT
        );
        return $this->fmOutput->outputJSON($info);
    }

    public function getAction($table)
    {
        $wp_list_table = _get_list_table($table);
        return $wp_list_table->current_action();
    }

    public function checkCurrency()
    {
        $currency = $this->fmWoo->getWoocommerceCurrency();
        return !in_array($currency, FyndiqUtils::$allowedCurrencies);
    }

    public function checkCountry()
    {
        $country = $this->fmWoo->WC()->countries->get_base_country();
        return !in_array($country, FyndiqUtils::$allowedMarkets);
    }

    public function checkCredentials()
    {
        $username = $this->fmWoo->getOption('wcfyndiq_username');
        $token = $this->fmWoo->getOption('wcfyndiq_apitoken');

        return (empty($username) || empty($token));
    }

    function check_page()
    {
        echo "<h1>".__('Fyndiq Checker Page')."</h1>";
        echo "<p>".__('This is a page to check all the important requirements to make the Fyndiq work.')."</p>";

        echo "<h2>".__('File Permission')."</h2>";
        echo $this->probe_file_permissions();

        echo "<h2>".__('Classes')."</h2>";
        echo $this->probe_module_integrity();

        echo "<h2>".__('API Connection')."</h2>";
        echo $this->probe_connection();

        echo "<h2>".__('Installed Plugins')."</h2>";
        echo $this->probe_plugins();
    }


    protected function checkToken($get)
    {
        $pingToken = $this->fmWoo->getOption('wcfyndiq_ping_token');

        $token = isset($get['pingToken']) ? $get['pingToken'] : null;

        if (is_null($token) || $token != $pingToken) {
            $this->fmOutput->showError(400, 'Bad Request', '400 Bad Request');
            $this->fmWoo->wpDie();
        }
    }

    protected function probe_file_permissions()
    {
        $messages = array();
        $testMessage = time();
        try {
            $fileName = $this->filePath;
            $exists =  file_exists($fileName) ?
                $this->fmWoo->__('exists') :
                $this->fmWoo->__('does not exist');
            $messages[] = sprintf($this->fmWoo->__('Feed file name: `%s` (%s)'), $fileName, $exists);
            $tempFileName = FyndiqUtils::getTempFilename(dirname($fileName));
            if (dirname($tempFileName) !== dirname($fileName)) {
                throw new Exception(sprintf(
                    $this->fmWoo->__('Cannot create file. Please make sure that the server can create new files in `%s`'),
                    dirname($fileName)
                ));
            }
            $messages[] = sprintf(
                $this->fmWoo->__('Trying to create temporary file: `%s`'),
                $tempFileName
            );
            $file = fopen($tempFileName, 'w+');
            if (!$file) {
                throw new Exception(sprintf(
                    $this->fmWoo->__('Cannot create file: `%s`'),
                    $tempFileName
                ));
            }
            fwrite($file, $testMessage);
            fclose($file);
            if ($testMessage == file_get_contents($tempFileName)) {
                $messages[] = sprintf($this->fmWoo->__('File `%s` successfully read.'), $tempFileName);
            }
            FyndiqUtils::deleteFile($tempFileName);
            $messages[] = sprintf($this->fmWoo->__('Successfully deleted temp file `%s`'), $tempFileName);
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
                    $messages[] = sprintf($this->fmWoo->__('Class `%s` is found.'), $className);
                    continue;
                }
                $messages[] = sprintf($this->fmWoo->__('Class `%s` is NOT found.'), $className);
            }
            if ($missing) {
                throw new Exception(sprintf(
                    $this->fmWoo->__('Required classes `%s` are missing.'),
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
                    throw new Exception($this->fmWoo->__('Module is not authorized.'));
                }
            }
            $messages[] = $this->fmWoo->__('Connection to Fyndiq successfully tested');
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
        $setting = $this->fmWoo->getOption('wcfyndiq_order_enable');
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

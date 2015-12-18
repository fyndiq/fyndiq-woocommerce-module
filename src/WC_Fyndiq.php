<?php
class WC_Fyndiq
{
    private $filepath = null;
    private $fmOutput = null;
    private $productImages = null;
    private $categoryCache = array();

    const EXPORTED = 'exported';
    const NOT_EXPORTED = 'not exported';
    const NOTICES = 'fyndiq_notices';

    const ORDERS_DISABLE = 1;
    const ORDERS_ENABLE = 2;

    public function __construct($fmOutput, $mainfile)
    {
        //Load locale in init
        add_action('init', array(&$this, 'locale_load'));
        // called only after woocommerce has finished loading
        add_action('init', array(&$this, 'woocommerce_loaded'), 250);

        $upload_dir = wp_upload_dir();
        $this->filepath = $upload_dir['basedir'] . '/fyndiq-feed.csv';

        $this->fmOutput = $fmOutput;
        $this->fmUpdate = new FmUpdate();
        $this->fmExport = new FmExport($this->filepath, $this->fmOutput);
        $this->mainfile = $mainfile;
    }

    public function locale_load()
    {
        // Localization
        load_plugin_textdomain('fyndiq', false, dirname(plugin_basename(__FILE__)) . '/translations/');
    }

    /**
     * Take care of anything that needs woocommerce to be loaded.
     * For instance, if you need access to the $woocommerce global
     */
    public function woocommerce_loaded()
    {
        //javascript
        add_action('admin_head', array(&$this, 'get_url'));


        //Settings
        add_filter('woocommerce_settings_tabs_array', array(&$this, 'fyndiq_add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_wcfyndiq', array(&$this, 'settings_tab'));
        add_action('woocommerce_update_options_wcfyndiq', array(&$this, 'update_settings'));

        //products
        add_action(
            'woocommerce_product_options_general_product_data',
            array(&$this, 'fyndiq_add_product_field')
        );
        add_action('woocommerce_process_product_meta', array(&$this, 'fyndiq_product_save'));

        //product list
        add_filter('manage_edit-product_columns', array(&$this, 'fyndiq_product_add_column'));
        add_action('manage_product_posts_custom_column', array(&$this, 'fyndiq_product_column_export'), 5, 2);
        add_filter('manage_edit-product_sortable_columns', array(&$this, 'fyndiq_product_column_sort'));
        add_action('pre_get_posts', array(&$this, 'fyndiq_product_column_sort_by'));
        add_action('admin_notices', array(&$this, 'fyndiq_bulk_notices'));

        //Deactivation
        register_deactivation_hook($this->mainfile, array(&$this, 'deactivate'));

        //order list
        if ($this->ordersEnabled()) {
            add_filter('manage_edit-shop_order_columns', array(&$this, 'fyndiq_order_add_column'));
            add_action('manage_shop_order_posts_custom_column', array(&$this, 'fyndiq_order_column'), 5, 2);
            add_filter('manage_edit-shop_order_sortable_columns', array(&$this, 'fyndiq_order_column_sort'));
            add_action('load-edit.php', array(&$this, 'fyndiq_order_delivery_note_bulk_action'));
        }

        //bulk action
        add_action('admin_footer-edit.php', array(&$this, 'fyndiq_product_add_bulk_action'));
        add_action('load-edit.php', array(&$this, 'fyndiq_product_export_bulk_action'));

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
        global $post;
        $post_id = $post->ID;
        $meta = get_post_custom($post_id);
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
        global $post;
        $post_id = $post->ID;
        $meta = get_post_custom($post_id);

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


            //Price Percentage
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

            );

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
            FyndiqUtils::NAME_PRODUCT_FEED_URL => get_site_url() . '/?fyndiq_feed',
            FyndiqUtils::NAME_PING_URL => get_site_url() .
                '/?fyndiq_notification=1&event=ping&pingToken=' . $pingToken
        );
        if ($this->ordersEnabled()) {
            $data[FyndiqUtils::NAME_NOTIFICATION_URL] = get_site_url() . '/?fyndiq_notification=1&event=order_created';
        }
        return FmHelpers::callApi('PATCH', 'settings/', $data);
    }


    public function fyndiq_add_product_field()
    {
        $product = get_product($this->getProductId());
        $version = FmHelpers::get_woocommerce_version();
        $price = $this->fmExport->getPrice($product->id, $product->price);
        $percentage = get_post_meta($product->id, '_fyndiq_price_percentage', true);

        if (!$this->isProductExportable($product)) {
            $this->fmOutput->output(sprintf(
                '<div class="options_group"><p>%s</p></div>',
                __('Can\'t export this product to Fyndiq', 'fyndiq')
            ));
            return;
        }
        $this->fmOutput->output('<div class="options_group"><p>' . __('Fyndiq Product Settings', 'fyndiq') . '</p>');
        if (version_compare($version, '2.3.8') >= 0) {
            // Checkbox for exporting to fyndiq
            $value = (get_post_meta($product->id, '_fyndiq_export', true) == self::EXPORTED) ? 1 : 0;

            woocommerce_form_field(
                '_fyndiq_export',
                array(
                    'type' => 'checkbox',
                    'class' => array('form-field', 'input-checkbox'),
                    'label' => __('Export to Fyndiq', 'fyndiq'),
                    'description' => __('mark this as true if you want to export to Fyndiq', 'fyndiq'),
                ),
                $value
            );

            //The price percentage for fyndiq for this specific product.
            woocommerce_form_field(
                '_fyndiq_price_percentage',
                array(
                    'type' => 'text',
                    'class' => array('form-field', 'short'),
                    'label' => __('Fyndiq Discount Percentage', 'fyndiq'),
                    'description' => __(
                        'The percentage specific for this product, it will override the globel percentage for this product.',
                        'fyndiq'
                    ),
                    'required' => false,
                ),
                $percentage
            );
        } else {
            // If the woocommerce is older or the same as 2.2.11 it needs to
            // use raw html becuase woocommerce_form_field doesn't exist

            $exported = (get_post_meta($product->id, '_fyndiq_export', true) == self::EXPORTED) ? ' checked' : '';

            // Checkbox for exporting to fyndiq
            $this->fmOutput->output(sprintf(
                '<p class="form-field" id="_fyndiq_export_field">
                <label for="_fyndiq_export"> %s</label>
				<input type="checkbox" class="input-checkbox " name="_fyndiq_export" id="_fyndiq_export" value="1"%s>
                <span class="description">%s</span></p>',
                __('Export to Fyndiq', 'fyndiq'),
                $exported,
                __('mark this as true if you want to export to Fyndiq', 'fyndiq')
            ));

            //The price percentage for fyndiq for this specific product.
            $this->fmOutput->output(sprintf(
                '<p class="form-row form-row form-field short" id="_fyndiq_price_percentage_field">
                <label for="_fyndiq_price_percentage" class="">%s</label>
                <input type="text" class="short wc_input_price" name="_fyndiq_price_percentage" id="_fyndiq_price_percentage" placeholder="" value="%s">
                <span class="description">%s</span></p>',
                __('Fyndiq Discount Percentage', 'fyndiq'),
                $percentage,
                __(
                    'The percentage specific for this product, it will override the globel percentage for this product.',
                    'fyndiq'
                )
            ));
        }

        $this->fmOutput->output(sprintf(
            '<p>%s %s %s</p></div>',
            __('Fyndiq Price with set Discount percentage: ', 'fyndiq'),
            $price,
            get_woocommerce_currency()
        ));
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
        ?>
        <div class="error">
        <p><?php _e('Some Fyndiq Orders failed to be imported, most likely due to stock or couldn\'t find product on Reference.', 'fyndiq'); ?></p>
        </div>
        <?php
    }

    public function fyndiq_show_setting_error_notice()
    {
        $this->fmOutput->output(sprintf(
            '<div class="error"><p>%s</p></div>',
            __('Fyndiq credentials was wrong, try again.', 'fyndiq')
        ));
    }

    /**
    * This is validating product data and show error if
    * it is not following the fyndiq validations
    */
    public function fyndiq_product_validate($post_id)
    {
        if ($this->getExportState() == self::EXPORTED) {
            $error = false;
            $postTitleLength = mb_strlen($_POST['post_title']);
            if ($postTitleLength < FyndiqFeedWriter::$minLength[FyndiqFeedWriter::PRODUCT_TITLE] ||
            $postTitleLength > FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::PRODUCT_TITLE]) {
                $this->add_fyndiq_notice(
                    sprintf(
                        __('Title needs to be between %s and %s in length, now it is: %s', 'fyndiq'),
                        FyndiqFeedWriter::$minLength[FyndiqFeedWriter::PRODUCT_TITLE],
                        FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::PRODUCT_TITLE],
                        $postTitleLength
                    ),
                    'error'
                );
                $error = true;
            }

            $postDescriptionLength = mb_strlen($this->fmExport->getDescriptionPOST());
            if ($postDescriptionLength < FyndiqFeedWriter::$minLength[FyndiqFeedWriter::PRODUCT_DESCRIPTION] ||
            $postDescriptionLength > FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::PRODUCT_DESCRIPTION]) {
                $this->add_fyndiq_notice(
                    sprintf(
                        __('Description needs to be between %s and %s in length, now it is: %s', 'fyndiq'),
                        FyndiqFeedWriter::$minLength[FyndiqFeedWriter::PRODUCT_DESCRIPTION],
                        FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::PRODUCT_DESCRIPTION],
                        $postDescriptionLength
                    ),
                    'error'
                );
                $error = true;
            }

            $postSKULength = mb_strlen($_POST['_sku']);
            if ($postSKULength < FyndiqFeedWriter::$minLength[FyndiqFeedWriter::ARTICLE_SKU] ||
            $postSKULength > FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::ARTICLE_SKU]) {
                $this->add_fyndiq_notice(
                    sprintf(
                        __('SKU needs to be between %s and %s in length, now it is: %s', 'fyndiq'),
                        FyndiqFeedWriter::$minLength[FyndiqFeedWriter::ARTICLE_SKU],
                        FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::ARTICLE_SKU],
                        $postSKULength
                    ),
                    'error'
                );
                $error = true;
            }

            $postRegularPrice = intval($_POST['_regular_price']);
            $type = $_POST['product-type'];
            if ($type != 'variable' && $postRegularPrice <= 0) {
                $this->add_fyndiq_notice(
                    sprintf(
                        __('Regular Price needs to be set above 0, now it is: %s', 'fyndiq'),
                        $postRegularPrice
                    ),
                    'error'
                );
                $error = true;
            }

            if ($error) {
                update_post_meta($post_id, '_fyndiq_export', self::NOT_EXPORTED);
            }
        }
    }

    public function fyndiq_product_save($post_id)
    {
        $woocommerce_checkbox = $this->getExportState();
        $woocommerce_pricepercentage = $this->getPricePercentage();
        update_post_meta($post_id, '_fyndiq_export', $woocommerce_checkbox);


        if ($woocommerce_checkbox == self::EXPORTED && !update_post_meta($post_id, '_fyndiq_status', FmProduct::STATUS_PENDING)) {
            add_post_meta($post_id, '_fyndiq_status', FmProduct::STATUS_PENDING, true);
            if (empty($woocommerce_pricepercentage)) {
                update_post_meta($post_id, '_fyndiq_price_percentage', get_option('wcfyndiq_price_percentage'));
            }
        } elseif ($woocommerce_checkbox == self::NOT_EXPORTED && !update_post_meta($post_id, '_fyndiq_status', '')) {
            add_post_meta($post_id, '_fyndiq_status', '', true);
        }
        if (!empty($woocommerce_pricepercentage)) {
            update_post_meta($post_id, '_fyndiq_price_percentage', $woocommerce_pricepercentage);
        }

        $this->fyndiq_product_validate($post_id);
    }

    public function fyndiq_product_add_column($defaults)
    {
        $defaults['fyndiq_export'] = __('Fyndiq Exported', 'fyndiq');
        $defaults['fyndiq_status'] = __('Fyndiq Status', 'fyndiq');

        return $defaults;
    }

    public function fyndiq_product_column_export($column, $postid)
    {
        $product = get_product($postid);

        if ($column == 'fyndiq_export') {
            if ($this->isProductExportable($product)) {
                $exported = get_post_meta($postid, '_fyndiq_export', true);
                if ($exported != '') {
                    if ($exported == self::EXPORTED) {
                        _e('Exported', 'fyndiq');
                    } else {
                        _e('Not exported', 'fyndiq');
                    }
                } else {
                    update_post_meta($postid, '_fyndiq_export', self::NOT_EXPORTED);
                    _e('Not exported', 'fyndiq');
                }
            } else {
                _e('Can\'t be exported', 'fyndiq');
            }
        }
        if ($column == 'fyndiq_status') {
            $status = get_post_meta($postid, '_fyndiq_status', true);
            $exported = get_post_meta($postid, '_fyndiq_export', true);

            if ($exported != '' && $status != '') {
                if ($status == FmProduct::STATUS_PENDING) {
                    _e('Pending', 'fyndiq');
                } elseif ($status == FmProduct::STATUS_FOR_SALE) {
                    _e('For Sale', 'fyndiq');
                }
            } else {
                _e('-', 'fyndiq');
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
        if ('fyndiq_order' == $orderby) {
            $query->set('meta_key', 'fyndiq_id');
            $query->set('orderby', 'meta_value_integer');
        }
    }


    public function fyndiq_order_add_column($defaults)
    {
        $defaults['fyndiq_order'] = __('Fyndiq Order', 'fyndiq');

        return $defaults;
    }

    public function fyndiq_order_column($column, $postid)
    {
        $product = new WC_Order($postid);
        if ($column == 'fyndiq_order') {
            $fyndiq_order = get_post_meta($postid, 'fyndiq_id', true);
            if ($fyndiq_order != '') {
                $this->fmOutput->output($fyndiq_order);
            } else {
                update_post_meta($postid, 'fyndiq_id', '-');
                $this->fmOutput->output('-');
            }
        }
    }


    public function fyndiq_product_column_sort()
    {
        return array(
            'fyndiq_export' => 'fyndiq_export',
            'fyndiq_status' => 'fyndiq_status'
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
        if ('fyndiq_status' == $orderby) {
            $query->set('meta_key', '_fyndiq_status');
            $query->set('orderby', 'meta_value');
        }
    }

    public function fyndiq_product_add_bulk_action()
    {
        global $post_type;

        if ($post_type == 'product') {
            $exportToFyndiq = __('Export to Fyndiq', 'fyndiq');
            $removeFromFyndiq = __('Remove from Fyndiq', 'fyndiq');
            $updateFyndiqStatus = __('Update Fyndiq Status', 'fyndiq');
            $script = <<<EOS
<script type="text/javascript">
    jQuery(document).ready(function () {
        jQuery('<option>').val('fyndiq_export').text('$exportToFyndiq').appendTo("select[name='action']");
        jQuery('<option>').val('fyndiq_export').text('$exportToFyndiq').appendTo("select[name='action2']");
        jQuery('<option>').val('fyndiq_no_export').text('$removeFromFyndiq').appendTo("select[name='action']");
        jQuery('<option>').val('fyndiq_no_export').text('$removeFromFyndiq').appendTo("select[name='action2']");
        if( jQuery('.wrap h2').length )  {
            jQuery(jQuery(".wrap h2")[0]).append("<a href='#' id='fyndiq-product-update' class='add-new-h2'>$updateFyndiqStatus</a>");
        }
        else if(jQuery('.wrap h1').length ){
            jQuery(jQuery(".wrap h1")[0]).append("<a href='#' id='fyndiq-product-update' class='page-title-action'>$updateFyndiqStatus</a>");
        }
    });
</script>
EOS;
            $this->fmOutput->output($script);
        }
        if ($post_type == 'shop_order' && $this->ordersEnabled()) {
            $getFyndiqDeliveryNote =  __('Get Fyndiq Delivery Note', 'fyndiq');
            $importFromFyndiq = __('Import From Fyndiq', 'fyndiq');
            $script =  <<<EOS
<script type="text/javascript">
    jQuery(document).ready(function () {
        jQuery('<option>').val('fyndiq_delivery').text('$getFyndiqDeliveryNote').appendTo("select[name='action']");
        jQuery('<option>').val('fyndiq_delivery').text('$getFyndiqDeliveryNote').appendTo("select[name='action2']");
        if( jQuery('.wrap h2').length ) {
            jQuery(jQuery(".wrap h2")[0]).append("<a href='#' id='fyndiq-order-import' class='add-new-h2'>$importFromFyndiq</a>");
        }
        else if(jQuery('.wrap h1').length ){
            jQuery(jQuery(".wrap h1")[0]).append("<a href='#' id='fyndiq-order-import' class='page-title-action'>$importFromFyndiq</a>");
        }
    });
</script>
EOS;
            $this->fmOutput->output($script);
        }
    }

    public function fyndiq_product_export_bulk_action()
    {
        $action = $this->getAction('WP_Posts_List_Table');

        switch ($action) {
            case 'fyndiq_export':
                $report_action = 'fyndiq_exported';
                $exporting = true;
                break;
            case 'fyndiq_no_export':
                $report_action = 'fyndiq_removed';
                $exporting = false;
                break;
            default:
                return;
        }

        $changed = 0;
        $post_ids = array();
        $posts = $this->getRequestPost();
        if (!is_null($posts)) {
            if ($exporting) {
                foreach ($posts as $post_id) {
                    $product = get_product($post_id);
                    if ($this->isProductExportable($product)) {
                        $this->perform_export($post_id);
                        $post_ids[] = $post_id;
                        $changed++;
                    }
                }
            } else {
                foreach ($posts as $post_id) {
                    $product = get_product($post_id);
                    if ($this->isProductExportable($product)) {
                        $this->perform_no_export($post_id);
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

    private function perform_export($post_id)
    {
        if (!update_post_meta($post_id, '_fyndiq_export', self::EXPORTED)) {
            add_post_meta($post_id, '_fyndiq_export', self::EXPORTED, true);
        };
        if (!update_post_meta($post_id, '_fyndiq_status', FmProduct::STATUS_PENDING)) {
            add_post_meta($post_id, '_fyndiq_status', FmProduct::STATUS_PENDING, true);
        };
        $percentage = get_post_meta($post_id, '_fyndiq_price_percentage', true);
        if (empty($percentage)) {
            update_post_meta($post_id, '_fyndiq_price_percentage', get_option('wcfyndiq_price_percentage'));
        }
    }

    private function perform_no_export($post_id)
    {
        if (!update_post_meta($post_id, '_fyndiq_export', self::NOT_EXPORTED)) {
            add_post_meta($post_id, '_fyndiq_export', self::NOT_EXPORTED, true);
        };
        if (!update_post_meta($post_id, '_fyndiq_status', '')) {
            add_post_meta($post_id, '_fyndiq_status', '', true);
        };
    }

    public function notification_handle()
    {
        define('DOING_AJAX', true);
        if (isset($_GET['event'])) {
            $event = $_GET['event'];
            $eventName = $event ? 'notice_' . $event : false;
            if ($eventName) {
                if ($eventName[0] != '_' && method_exists($this, $eventName)) {
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

                $orderModel = new FmOrder();

                if (!$orderModel->orderExists($fyndiqOrder->id)) {
                    $orderModel->createOrder($fyndiqOrder);
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
        $this->checkToken();

        FyndiqUtils::debugStart();
        FyndiqUtils::debug('USER AGENT', FmHelpers::get_user_agent());
        $languageId = WC()->countries->get_base_country();
        FyndiqUtils::debug('language', $languageId);
        $return = $this->fmExport->feedFileHandling();
        $result = file_get_contents($this->filepath);
        FyndiqUtils::debug('$result', $result, true);
        FyndiqUtils::debugStop();
        wp_die();
    }

    private function notice_ping()
    {
        $this->checkToken();

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

    public function generate_orders()
    {
        define('DOING_AJAX', true);
        try {
            $orderFetch = new FmOrderFetch(false, true);
            $result = $orderFetch->getAll();
            update_option('wcfyndiq_order_time', time());
        } catch (Exception $e) {
            $result = $e->getMessage();
            $this->setOrderError();
        }
        $this->fmOutput->outputJSON($result);
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
        exit();
    }

    public function getProductId()
    {
        return get_the_ID();
    }

    public function getExportState()
    {
        return isset($_POST['_fyndiq_export']) ? self::EXPORTED : self::NOT_EXPORTED;
    }

    public function getPricePercentage()
    {
        return isset($_POST['_fyndiq_price_percentage']) ? $_POST['_fyndiq_price_percentage'] : '';
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

    private function isProductExportable($product)
    {
        return (!$product->is_downloadable() && !$product->is_virtual() && !$product->is_type('external') && !$product->is_type('grouped'));
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

    function deactivate()
    {
        //First empty the settings on fyndiq
        if (!$this->checkCredentials()) {
            $data = array(
                FyndiqUtils::NAME_PRODUCT_FEED_URL => '',
                FyndiqUtils::NAME_PING_URL => '',
                FyndiqUtils::NAME_NOTIFICATION_URL => ''
            );
            try {
                FmHelpers::callApi('PATCH', 'settings/', $data);
            } catch (Exception $e) {
            }
        }
        //Empty all settings
        update_option('wcfyndiq_ping_token', '');
        update_option('wcfyndiq_username', '');
        update_option('wcfyndiq_apitoken', '');
    }

    private function add_fyndiq_notice($message, $type = 'update')
    {
        $notices = array();
        if (isset($_SESSION[self::NOTICES])) {
            $notices = $_SESSION[self::NOTICES];
        }

        if (!isset($notices[$type])) {
            $notices[$type] = array();
        }

        $notices[$type][] = $message;

        $_SESSION[self::NOTICES] = $notices;
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

    private function setOrderError()
    {
        if (get_option('wcfyndiq_order_error') !== false) {
            update_option('wcfyndiq_order_error', true);
        } else {
            add_option('wcfyndiq_order_error', true, null, false);
        }
    }
}

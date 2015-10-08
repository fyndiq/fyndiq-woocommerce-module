<?php
class WC_Fyndiq
{
    private $filepath = null;
    private $fmOutput = null;
    private $productImages = null;

    const EXPORTED = 'exported';
    const NOT_EXPORTED = 'not exported';

    public function __construct($fmOutput)
    {
        //Load locale in init
        add_action('init', array(&$this, 'locale_load'));
        // called only after woocommerce has finished loading
        add_action('woocommerce_init', array(&$this, 'woocommerce_loaded'));

        $upload_dir = wp_upload_dir();
        $this->filepath = $upload_dir['basedir'] . '/fyndiq-feed.csv';

        $this->fmOutput = $fmOutput;

        // indicates we are running the admin
        if (!is_admin()) {
        }
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
        add_filter('woocommerce_get_sections_products', array(&$this, 'fyndiq_settings_action'));
        add_filter('woocommerce_get_settings_products', array(&$this, 'fyndiq_all_settings'), 10, 2);
        add_action('woocommerce_update_options_products', array(&$this, 'update_settings'));

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

        //order list
        add_filter('manage_edit-shop_order_columns', array(&$this, 'fyndiq_order_add_column'));
        add_action('manage_shop_order_posts_custom_column', array(&$this, 'fyndiq_order_column'), 5, 2);
        add_filter('manage_edit-shop_order_sortable_columns', array(&$this, 'fyndiq_order_column_sort'));


        //bulk action
        add_action('admin_footer-edit.php', array(&$this, 'fyndiq_product_add_bulk_action'));
        add_action('load-edit.php', array(&$this, 'fyndiq_product_export_bulk_action'));
        add_action('load-edit.php', array(&$this, 'fyndiq_order_delivery_note_bulk_action'));

        //add_action('post_submitbox_misc_actions', array( &$this, 'fyndiq_order_edit_action'));
        add_action('add_meta_boxes', array(&$this, 'fyndiq_order_meta_boxes'));

        //notice for currency check
        add_action('admin_notices', array(&$this, 'my_admin_notice'));

        //functions
        if (isset($_GET['fyndiq_feed'])) {
            $this->generate_feed();
        }
        if (isset($_GET['fyndiq_orders'])) {
            $this->generate_orders();
        }
        if (isset($_GET['fyndiq_products'])) {
            $this->update_product_info();
        }
        if (isset($_GET['fyndiq_notification'])) {
            $this->notification_handle();
        }
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
    }

    public function fyndiq_settings_action($sections)
    {
        $sections['wcfyndiq'] = __('Fyndiq', 'fyndiq');
        return $sections;
    }

    public function fyndiq_all_settings($settings, $current_section)
    {
        /**
         * Check the current section is what we want
         **/

        if ($current_section == 'wcfyndiq') {
            $settings_slider = array();

            // Add Title to the Settings
            $settings_slider[] = array(
                'name' => __('Fyndiq Settings', 'fyndiq'),
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

            $settings_slider[] = array('type' => 'sectionend', 'id' => 'wcfyndiq');

            return $settings_slider;
        } else {
            /**
             * If not, return the standard settings
             **/
            return $settings;
        }
    }

    public function update_settings()
    {
        woocommerce_update_options($this->fyndiq_all_settings(array(), 'wcfyndiq'));
        try {
            $this->updateUrls();
        } catch (Exception $e) {
            if ($e->getMessage() == 'Unauthorized') {
                $this->fmOutput->output(sprintf(
                    '<div class="error"><p>%s</p></div>',
                    __('Fyndiq credentials was wrong, try again.', 'fyndiq')
                ));
            }
            //die();
        }
    }

    public function updateUrls()
    {
        //Generate pingtoken
        $pingToken = md5(uniqid());
        update_option('wcfyndiq_ping_token', $pingToken);

        $data = array(
            FyndiqUtils::NAME_PRODUCT_FEED_URL => get_site_url() . '/?fyndiq_feed',
            FyndiqUtils::NAME_NOTIFICATION_URL => get_site_url() . '/?fyndiq_notification=1&event=order_created',
            FyndiqUtils::NAME_PING_URL => get_site_url() .
                '/?fyndiq_notification=1&event=ping&pingToken=' . $pingToken
        );
        return FmHelpers::callApi('PATCH', 'settings/', $data);
    }


    public function fyndiq_add_product_field()
    {
        $product = get_product($this->getProductId());
        $version = FmHelpers::get_woocommerce_version();
        $price = $this->getPrice($product->id, $product->price);
        $percentage = get_post_meta($product->id, '_fyndiq_price_percentage', true);

        if (!$this->isProductExportable($product)) {
            $this->fmOutput->output(sprintf(
                '<div class="options_group"><p>%s</p></div>',
                __('Can\'t export this product to Fyndiq', 'fyndiq')
            ));
            return;
        }
        $this->fmOutput->output('<div class="options_group"><p>' . __('Fyndiq Product Settings', 'fyndiq') . '</p>');
        if (version_compare($version, '2.2.11') > 0) {
            // Checkbox for exporting to fyndiq
            $value = (get_post_meta($product->id, '_fyndiq_export', true) == self::EXPORTED) ? 1 : 0;

            woocommerce_form_field(
                '_fyndiq_export',
                array(
                    'type' => 'checkbox',
                    'class' => array('form-field', 'input-checkbox'),
                    'label' => __('Export to Fyndiq', 'fyndiq'),
                    'description' => __('mark this as true if you want to export to Fyndiq', 'fyndiq'),
                    'required' => false,
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

    public function fyndiq_product_save($post_id)
    {
        $woocommerce_checkbox = $this->getExportState();
        $woocommerce_pricepercentage = $this->getPricePercentage();
        update_post_meta($post_id, '_fyndiq_export', $woocommerce_checkbox);


        if ($woocommerce_checkbox == self::EXPORTED && !update_post_meta($post_id, '_fyndiq_status', FmProduct::STATUS_PENDING)) {
            add_post_meta($post_id, '_fyndiq_status', FmProduct::STATUS_PENDING, true);
        } elseif ($woocommerce_checkbox == self::NOT_EXPORTED && !update_post_meta($post_id, '_fyndiq_status', '')) {
            add_post_meta($post_id, '_fyndiq_status', '', true);
        }

        update_post_meta($post_id, '_fyndiq_price_percentage', $woocommerce_pricepercentage);
    }

    public function fyndiq_product_add_column($defaults)
    {
        $defaults['fyndiq_export'] = __('Fyndiq Exported', 'fyndiq');
        $defaults['fyndiq_status'] = __('Fyndiq Status', 'fyndiq');

        return $defaults;
    }

    public function fyndiq_product_column_export($column, $postid)
    {
        $product = get_product( $postid );

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
            $url = admin_url('admin.php?page=wc-settings&tab=products&section=wcfyndiq');
            printf(
                '<div class="error"><p><strong>%s</strong>: %s <a href="%s">%s</a></p></div>',
                __('Fyndiq Credentials', 'fyndiq'),
                __('You need to set Fyndiq Credentials to make it work. Do it in ', 'fyndiq'),
                $url,
                __('Woocommerce Settings > Products > Fyndiq', 'fyndiq')
            );
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
        if ($post_type == 'shop_order') {
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
        if ($exporting) {
            foreach ($this->getRequestPost() as $post_id) {
                $product = get_product($post_id);
                if ($this->isProductExportable($product)) {
                    $this->perform_export($post_id);
                    $post_ids[] = $post_id;
                    $changed++;
                }
            }
        } else {
            foreach ($this->getRequestPost() as $post_id) {
                $product = get_product($post_id);
                if ($this->isProductExportable($product)) {
                    $this->perform_no_export($post_id);
                    $post_ids[] = $post_id;
                    $changed++;
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
            throw new Exception('Pick at least one order');
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

    public function generate_feed()
    {
        $username = get_option('wcfyndiq_username');
        $token = get_option('wcfyndiq_apitoken');

        if (isset($username) && isset($token)) {
            if (FyndiqUtils::mustRegenerateFile($this->filepath)) {
                $this->feedFileHandling();
            }
            if (file_exists($this->filepath)) {
                $lastModified = filemtime($this->filepath);
                $file = fopen($this->filepath, 'r');
                $this->fmOutput->header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $lastModified));
                $this->fmOutput->streamFile($file, 'feed.csv', 'text/csv', filesize($this->filepath));
                fclose($file);
            }

            return $this->returnAndDie('');
        }
        return $this->fmOutput->showError(
            500,
            'Internal Server Error',
            sprintf('Error generating feed to %s', $this->filepath)
        );
    }

    public function feedFileHandling()
    {
        $tempFileName = FyndiqUtils::getTempFilename(dirname($this->filepath));
        FyndiqUtils::debug('$fileName', $this->filepath);
        FyndiqUtils::debug('$tempFileName', $tempFileName);

        $file = fopen($tempFileName, 'w+');
        if (!$file) {
            FyndiqUtils::debug('Cannot create file: ' . $fileName);
            return false;
        }

        $feedWriter = new FyndiqCSVFeedWriter($file);
        $exportResult = $this->feed_write($feedWriter);
        fclose($file);
        if ($exportResult) {
            // File successfully generated
            FyndiqUtils::moveFile($tempFileName, $this->filepath);
        } else {
            // Something wrong happened, clean the file
            FyndiqUtils::deleteFile($tempFileName);
        }
    }
    private function feed_write($feedWriter)
    {
        global $wpdb;
        $productmodel = new FmProduct();
        $posts_array = $productmodel->getExportedProducts();
        FyndiqUtils::debug('quantity minmum', get_option('wcfyndiq_quantity_minimum'));
        $this->tag_values_fixed = array();
        foreach ($posts_array as $product) {
            $this->productImages = array();
            $this->productImages['product'] = array();
            $this->productImages['articles'] = array();
            $exportedArticles = array();
            $product = new WC_Product_Variable($product);
            FyndiqUtils::debug('$product', $product);
            $tag_values = get_post_meta($product->id, '_product_attributes', true);
            FyndiqUtils::debug('$tag_values', $tag_values);
            foreach ($tag_values as $value) {
                FyndiqUtils::debug('$value[\'name\']', $value['name']);
                $name = str_replace('pa_', '', $value['name']);
                if (!isset($this->tag_values_fixed[$value['name']])) {
                    $label = $wpdb->get_var($wpdb->prepare("SELECT attribute_label FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s;", $name));
                    $this->tag_values_fixed[$value['name']] =  $label;
                }
            }
            FyndiqUtils::debug('$tag_values_fixed', $this->tag_values_fixed);
            $variations = $this->getAllVariations($product);
            if (count($variations) > 0) {
                $prices = array();

                $attachment_ids = $product->get_gallery_attachment_ids();
                $feat_image = wp_get_attachment_url(get_post_thumbnail_id($product->id));
                if (!empty($feat_image)) {
                    $this->productImages['product'][] = $feat_image;
                }
                foreach ($attachment_ids as $attachment_id) {
                    $image_link = wp_get_attachment_url($attachment_id);
                    $this->productImages['product'][] = $image_link;
                }

                foreach ($variations as $variation) {
                    $exportVariation = $this->getVariation($product, $variation);
                    $prices[] = $exportVariation['product-price'];
                    FyndiqUtils::debug('$exportVariation', $exportVariation);
                    $exportedArticles[] = $exportVariation;
                }

                $differentPrice = count(array_unique($prices)) > 1;
                FyndiqUtils::debug('$differentPrice', $differentPrice);

                FyndiqUtils::debug('productImages', $this->productImages);

                foreach ($exportedArticles as $articleId => $article) {
                    if (!$differentPrice) {
                        // All prices are NOT different, create articles
                        $images = $this->getImagesFromArray();
                        $article = array_merge($article, $images);
                        if (empty($article['article-sku'])) {
                            FyndiqUtils::debug('EMPTY ARTICLE SKU');
                        }
                        $feedWriter->addProduct($article);
                        FyndiqUtils::debug('Any sameprice Errors', $feedWriter->getLastProductErrors());
                        continue;
                    }

                    // Prices differ, create products
                    $id = count($article['article-sku']) > 0 ? $article['article-sku'] : null;
                    FyndiqUtils::debug('$id', $id);
                    $images = $this->getImagesFromArray($id);
                    $article = array_merge($article, $images);
                    $article['product-id'] = $article['product-id'] . '-' . $articleId;
                    if (empty($article['article-sku'])) {
                        FyndiqUtils::debug('EMPTY ARTICLE SKU');
                    }
                    $feedWriter->addProduct($article);
                    FyndiqUtils::debug('Any Validation Errors', $feedWriter->getLastProductErrors());
                }
            } else {
                $exportProduct = $this->getProduct($product);
                $images = $this->getImagesFromArray();
                $exportProduct = array_merge($exportProduct, $images);
                if (empty($exportProduct['article-sku'])) {
                    FyndiqUtils::debug('EMPTY PRODUCT SKU');
                }
                $feedWriter->addProduct($exportProduct);
                FyndiqUtils::debug('Product Validation Errors', $feedWriter->getLastProductErrors());
            }
        }
        $feedWriter->write();
        return true;
    }

    private function getProduct($product)
    {
        //Initialize models here so it saves memory.
        $feedProduct['product-id'] = $product->id;
        $feedProduct['product-title'] = $product->post->post_title;
        $feedProduct['product-description'] = $product->post->post_content;

        $productPrice = $product->get_price();
        if(wc_tax_enabled()) {
            $productPrice = $product->get_price_including_tax();
        }
        $price = $this->getPrice($product->id, $productPrice);

        $_tax = new WC_Tax(); //looking for appropriate vat for specific product
        FyndiqUtils::debug('tax class', $product->get_tax_class());

        $rates = $_tax->get_rates($product->get_tax_class());
        $rates = array_shift($rates);
        FyndiqUtils::debug('tax rate', $rates);


        $feedProduct['product-price'] = FyndiqUtils::formatPrice($price);
        $feedProduct['product-vat-percent'] = !empty($rates['rate']) ? $rates['rate'] : 0;
        $feedProduct['product-oldprice'] = FyndiqUtils::formatPrice($productPrice);
        $feedProduct['product-market'] = WC()->countries->get_base_country();
        $feedProduct['product-currency'] = get_woocommerce_currency();

        $terms = get_the_terms($product->id, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $feedProduct['product-category-id'] = $term->term_id;
                $feedProduct['product-category-name'] = $term->name;
                break;
            }
        }

        $attachment_ids = $product->get_gallery_attachment_ids();
        $feat_image = wp_get_attachment_url(get_post_thumbnail_id($product->id));
        FyndiqUtils::debug('$feat_image', $feat_image);
        if (!empty($feat_image)) {
            $this->productImages['product'][] = $feat_image;
        }
        foreach ($attachment_ids as $attachment_id) {
            $image_link = wp_get_attachment_url($attachment_id);
            $this->productImages['product'][] = $image_link;
        }

        $feedProduct['article-quantity'] = intval(0);
        if ($product->is_in_stock()) {
            $stock = $product->get_stock_quantity();
            $minimumQuantity = get_option('wcfyndiq_quantity_minimum');
            if ($minimumQuantity > 0) {
                $stock = $stock - $minimumQuantity;
            }
            FyndiqUtils::debug('$stock product', $stock);
            $feedProduct['article-quantity'] = intval($stock);
        }

        $sku = get_post_meta($product->id, '_sku');
        $sku = array_shift($sku);
        $feedProduct['article-sku'] = strval($sku);

        $feedProduct['article-name'] = $product->post->post_title;

        return $feedProduct;
    }


    private function getVariation($product, $variation)
    {
        FyndiqUtils::debug('$variation', $variation);
        if (!$variation['is_downloadable'] && !$variation['is_virtual']) {
            $variationModel = new WC_Product_Variation($variation['variation_id'], array('parent_id' => $product->id, 'parent' => $product));
            //Initialize models here so it saves memory.
            $feedProduct['product-id'] = $product->id;
            $feedProduct['product-title'] = $product->post->post_title;
            $feedProduct['product-description'] = $product->post->post_content;

            $productPrice = $variation['display_price'];
            $price = $this->getPrice($product->id, $productPrice);
            $_tax = new WC_Tax(); //looking for appropriate vat for specific product
            $rates = $_tax->get_rates($product->get_tax_class());
            $rates = array_shift($rates);

            $feedProduct['product-price'] = FyndiqUtils::formatPrice($price);
            $feedProduct['product-vat-percent'] = !empty($rates['rate']) ? $rates['rate'] : 0;
            $feedProduct['product-oldprice'] = FyndiqUtils::formatPrice($productPrice);
            $feedProduct['product-market'] = WC()->countries->get_base_country();
            $feedProduct['product-currency'] = get_woocommerce_currency();

            $terms = get_the_terms($product->id, 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $feedProduct['product-category-id'] = $term->term_id;
                    $feedProduct['product-category-name'] = $term->name;
                    break;
                }
            }
            $sku = $variation['sku'];

            $feedProduct['article-sku'] = strval($sku);

            $variationImages = array();
            if (!empty($variation['image_src'])) {
                $variationImages[] = $variation['image_src'];
            }
            $this->productImages['articles'][$sku] = $variationImages;

            $feedProduct['article-quantity'] = 0;

            if ($variation['is_purchasable'] && $variation['is_in_stock']) {
                $stock = intval($variationModel->get_stock_quantity());
                $minimumQuantity = get_option('wcfyndiq_quantity_minimum');
                if ($minimumQuantity > 0) {
                    $stock = $stock - $minimumQuantity;
                }
                $feedProduct['article-quantity'] = $stock;
            }


            $feedProduct['article-name'] = $product->post->post_title;
            $tag_values = $variationModel->get_variation_attributes();

            if (!empty($tag_values)) {
                FyndiqUtils::debug('$tag_values', $tag_values);
                $propertyId = 1;
                $tags = array();
                foreach ($tag_values as $key => $value) {
                    $key = str_replace('attribute_', '', $key);
                    $feedProduct['article-property-'.$propertyId.'-name'] = $this->tag_values_fixed[$key];
                    $feedProduct['article-property-'.$propertyId.'-value'] = $value;
                    $tags[] = $this->tag_values_fixed[$key] . ': ' . $value;
                    $propertyId++;
                }

                $feedProduct['article-name'] = implode(', ', $tags);
            }



            return $feedProduct;
        }
    }

    private function getImagesFromArray($articleId = null)
    {
        $product = array();
        $urls = array();
        //If we don't want to add a specific article, add all of them.
        if (!isset($articleId)) {
            foreach ($this->productImages['product'] as $url) {
                if (!in_array($url, $product)) {
                    $urls[] = $url;
                }
            }
            foreach ($this->productImages['articles'] as $article) {
                foreach ($article as $url) {
                    if (!in_array($url, $product)) {
                        $urls[] = $url;
                    }
                }
            }
        // If we want to add just the product images and the article's images - run this.
        } else {
            foreach ($this->productImages['articles'][$articleId] as $url) {
                $urls[] = $url;
            }

            foreach ($this->productImages['product'] as $url) {
                $urls[] = $url;
            }
        }
        $imageId = 1;
        foreach ($urls as $url) {
            if ($imageId > FyndiqUtils::NUMBER_OF_ALLOWED_IMAGES) {
                break;
            }
            $product['product-image-' . $imageId . '-url'] = $url;
            $product['product-image-' . $imageId . '-identifier'] = substr(md5($url), 0, 10);
            $imageId++;
        }
        return $product;
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
        $return = $this->feedFileHandling();
        $result = file_get_contents($this->filepath);
        FyndiqUtils::debug('$result', $result, true);
        FyndiqUtils::debugStop();
        wp_die();
    }

    private function notice_ping()
    {
        $pingToken = get_option('wcfyndiq_ping_token');

        $token = $_GET['token'];

        if (is_null($token) || $token != $pingToken) {
            $this->fmOutput->showError(400, 'Bad Request', '400 Bad Request');
            wp_die();
        }

        $this->fmOutput->flushHeader('OK');

        $locked = false;
        $lastPing = get_option('wcfyndiq_ping_time');
        $lastPing = $lastPing ? unserialize($lastPing) : false;
        $locked = $lastPing && $lastPing > strtotime('15 minutes ago');
        if (!$locked) {
            update_option('wcfyndiq_ping_time', time());
            try {
                $this->feed_write($this->filepath);
                $this->update_product_info();
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
            $this->feed_write($this->filepath);
        }
    }

    public function generate_orders()
    {
        define('DOING_AJAX', true);
        $orderFetch = new FmOrderFetch(false);
        $result = $orderFetch->getAll();
        $this->fmOutput->outputJSON($result);
        wp_die();
    }

    private function update_product_info()
    {
        define('DOING_AJAX', true);
        $productFetch = new FmProductFetch();
        $productFetch->getAll();
        $this->fmOutput->outputJSON(array('status' => 'ok'));
        wp_die();
    }

    public function getAction($table)
    {
        $wp_list_table = _get_list_table($table);

        return $wp_list_table->current_action();
    }

    public function getRequestPost()
    {
        return $_REQUEST['post'];
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
        return (get_woocommerce_currency() != 'SEK' && get_woocommerce_currency() != 'EUR');
    }

    public function checkCountry()
    {
        return (WC()->countries->get_base_country() != 'SE' && WC()->countries->get_base_country() != 'DE');
    }

    public function checkCredentials()
    {
        return empty(get_option('wcfyndiq_username')) || empty(get_option('wcfyndiq_apitoken'));
    }

    private function getPrice($product_id, $product_price)
    {
        $discount = $this->getDiscount(get_option('wcfyndiq_price_percentage'));
        $percentage = get_post_meta($product_id, '_fyndiq_price_percentage', true);

        if (isset($percentage) && $percentage > 0) {
            $discount = $this->getDiscount(intval($percentage));
        }

        return FyndiqUtils::getFyndiqPrice($product_price, $discount);
    }

    private function getDiscount($discount)
    {
        if ($discount > 100) {
            $discount = 100;
        } elseif ($discount < 0) {
            $discount = 0;
        }

        return $discount;
    }

    private function isProductExportable($product)
    {
        return (!$product->is_downloadable() && !$product->is_virtual() && !$product->is_type( 'external' ) && !$product->is_type( 'grouped' ));
    }

    private function getAllVariations($product)
    {

        $available_variations = array();

        foreach ($product->get_children() as $child_id) {
            $variation = $product->get_child($child_id);

            $variation_attributes = $variation->get_variation_attributes();
            $availability         = $variation->get_availability();
            $availability_html    = empty($availability['availability']) ? '' : '<p class="stock ' . esc_attr($availability['class']) . '">' . wp_kses_post($availability['availability']) . '</p>';
            $availability_html    = apply_filters('woocommerce_stock_html', $availability_html, $availability['availability'], $variation);

            if (has_post_thumbnail($variation->get_variation_id())) {
                $attachment_id = get_post_thumbnail_id($variation->get_variation_id());

                $attachment    = wp_get_attachment_image_src($attachment_id, apply_filters('single_product_large_thumbnail_size', 'shop_single'));
                $image         = $attachment ? current($attachment) : '';

                $attachment    = wp_get_attachment_image_src($attachment_id, 'full');
                $image_link    = $attachment ? current($attachment) : '';

                $image_title   = get_the_title($attachment_id);
                $image_alt     = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            } else {
                $image = $image_link = $image_title = $image_alt = '';
            }

            $filters = array(
                'variation_id'          => $child_id,
                'variation_is_visible'  => $variation->variation_is_visible(),
                'is_purchasable'        => $variation->is_purchasable(),
                'attributes'            => $variation_attributes,
                'image_src'             => $image,
                'image_link'            => $image_link,
                'image_title'           => $image_title,
                'image_alt'             => $image_alt,
                'price_html'            => $variation->get_price() === "" || $product->get_variation_price('min') !== $product->get_variation_price('max') ? '<span class="price">' . $variation->get_price_html() . '</span>' : '',
                'availability_html'     => $availability_html,
                'sku'                   => $variation->get_sku(),
                'weight'                => $variation->get_weight() . ' ' . esc_attr(get_option('woocommerce_weight_unit')),
                'dimensions'            => $variation->get_dimensions(),
                'min_qty'               => 1,
                'max_qty'               => $variation->backorders_allowed() ? '' : $variation->get_stock_quantity(),
                'backorders_allowed'    => $variation->backorders_allowed(),
                'is_in_stock'           => $variation->is_in_stock(),
                'is_downloadable'       => $variation->is_downloadable() ,
                'is_virtual'            => $variation->is_virtual(),
                'is_sold_individually'  => $variation->is_sold_individually() ? 'yes' : 'no',
            );

            $version = FmHelpers::get_woocommerce_version();
            if (version_compare($version, '2.2.11') > 0) {
                $filters['variation_is_active'] = $variation->variation_is_active();
                $filters['display_regular_price'] = $variation->get_display_price($variation->get_regular_price());

                $filters['display_price'] = $variation->get_display_price();
                if(wc_tax_enabled()) {
                    $filters['display_price'] = $variation->get_price_including_tax();
                }

            } else {
                $tax_display_mode      = get_option('woocommerce_tax_display_shop');
                $display_price         = $tax_display_mode == 'incl' ? $variation->get_price_including_tax() : $variation->get_price_excluding_tax();
                $display_regular_price = $tax_display_mode == 'incl' ? $variation->get_price_including_tax(1, $variation->get_regular_price()) : $variation->get_price_excluding_tax(1, $variation->get_regular_price());
                $display_sale_price    = $tax_display_mode == 'incl' ? $variation->get_price_including_tax(1, $variation->get_sale_price()) : $variation->get_price_excluding_tax(1, $variation->get_sale_price());

                $price = $display_price;
                if (isset($display_sale_price)) {
                    $price = $display_sale_price;
                }

                $filters['display_price'] = $price;
                $filters['display_regular_price'] = $display_regular_price;
            }

            $available_variations[] = apply_filters('woocommerce_available_variation', $filters, $product, $variation);
        }

        FyndiqUtils::debug('$available_variations', $available_variations);

        return $available_variations;
    }
}

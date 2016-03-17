<?php
//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class WC_Fyndiq
{
    private $filePath = null;
    private $fmOutput = null;

    const NOTICES = 'fyndiq_notices';




    public function __construct()
    {
        $this->currencies = array_combine(FyndiqUtils::$allowedCurrencies, FyndiqUtils::$allowedCurrencies);

        //Register class hooks as early as possible
        add_action('wp_loaded', array(&$this, 'initiateClassHooks'));

        //Load locale in init
        add_action('init', array(&$this, 'locale_load'));

        // called only after woocommerce has finished loading
        add_action('init', array(&$this, 'woocommerce_loaded'), 250);

        $this->filePath = wp_upload_dir()['basedir'] . '/fyndiq-feed.csv';

        $this->fmOutput = new FyndiqOutput();
        $this->fmUpdate = new FmUpdate();
        $this->fmExport = new FmExport($this->filePath, $this->fmOutput);
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
        FmField::setHooks();
        FmDiagnostics::setHooks();
        FmSettings::setHooks();
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
        if (FmOrder::getOrdersEnabled()) {
            add_filter('manage_edit-shop_order_columns', array(&$this, 'fyndiq_order_add_column'));
            add_action('manage_shop_order_posts_custom_column', array(&$this, 'fyndiq_order_column'), 5, 2);
            add_filter('manage_edit-shop_order_sortable_columns', array(&$this, 'fyndiq_order_column_sort'));
        }

        //bulk action
        //Inserts the JS for the appropriate dropdown items
        add_action('admin_footer-edit.php', array(&$this, 'fyndiq_add_bulk_action'));

        //Dispatcher for different bulk actions
        add_action('load-edit.php', array(&$this, 'fyndiq_bulk_action_dispatcher'));

        //add_action('post_submitbox_misc_actions', array( &$this, 'fyndiq_order_edit_action'));
        add_action('add_meta_boxes', array(&$this, 'fyndiq_order_meta_boxes'));

        //notice for currency check
        add_action('admin_notices', array(&$this, 'my_admin_notice'));

        //index
        add_action('load-index.php', array($this->fmUpdate, 'updateNotification'));

        //orders
        add_action('load-edit.php', array(&$this, 'fyndiq_show_order_error'));


        //functions
        if (isset($_GET['fyndiq_feed'])) {
            $this->fmExport->generate_feed();
        }
        if (isset($_GET['fyndiq_orders'])) {
            FmOrder::generateOrders();
        }
        if (isset($_GET['fyndiq_notification'])) {
            $this->notification_handle();
        }
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
        if (FmOrder::getOrdersEnabled()) {
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

    //Hooked to woocommerce_product_write_panel_tabs
    public function fyndiq_product_tab()
    {
        echo sprintf("<li class='fyndiq_tab'><a href='#fyndiq_tab'>%s</a></li>", __('Fyndiq', 'fyndiq'));
    }




    /**
     *
     * This is the hooked function for fields on the order pages
     *
     */



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

    //Hooked to manage_edit-shop_order_sortable_columns
    public function fyndiq_order_column_sort()
    {
        return array(
            'fyndiq_order' => 'fyndiq_order'
        );
    }

    //TODO: find out how this function is called
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
            $this->fmOutput->output(sprintf(
                '<div class="error"><p><strong>%s</strong>: %s %s</p></div>',
                __('Wrong Currency', 'fyndiq'),
                __('Fyndiq only works in EUR and SEK. change to correct currency. Current Currency:', 'fyndiq'),
                get_woocommerce_currency()
            ));
        }
        if ($this->checkCountry()) {
            $this->fmOutput->output(sprintf(
                '<div class="error"><p><strong>%s</strong>: %s %s</p></div>',
                __('Wrong Country', 'fyndiq'),
                __('Fyndiq only works in Sweden and Germany. change to correct country. Current Country:', 'fyndiq'),
                WC()->countries->get_base_country()
            ));
        }
        if ($this->checkCredentials()) {
            $url = admin_url('admin.php?page=wc-settings&tab=wcfyndiq');
            $this->fmOutput->output(sprintf(
                '<div class="error"><p><strong>%s</strong>: %s <a href="%s">%s</a></p></div>',
                __('Fyndiq Credentials', 'fyndiq'),
                __('You need to set Fyndiq Credentials to make it work. Do it in ', 'fyndiq'),
                $url,
                __('Woocommerce Settings > Fyndiq', 'fyndiq')
            ));
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


        //We need this JS header in any case. Initialises output var too.
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
                if (FmOrder::getOrdersEnabled()) {
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
        $action = $this->getAction('WP_Posts_List_Table');
        switch ($this->getAction('WP_Posts_List_Table')) {
            case 'fyndiq_handle_order':
                FmOrder::orderHandleBulkAction(true);
                break;
            case 'fyndiq_unhandle_order':
                FmOrder::orderHandleBulkAction(false);
                break;
            case 'fyndiq_delivery':
                FmOrder::deliveryNoteBulkaction();
                break;
            case 'fyndiq_export':
                FmProduct::productExportBulkAction(FmProduct::EXPORTED, $action);
                break;
            case 'fyndiq_no_export':
                FmProduct::productExportBulkAction(FmProduct::NOT_EXPORTED, $action);
                break;
            default:
                break;
        }
    }

    public function do_bulk_action_messages()
    {
        if (isset($_SESSION['bulkMessage']) && $GLOBALS['pagenow'] === 'edit.php') {
            $this->fmOutput->output('<div class="updated"><p>' . $_SESSION['bulkMessage'] . '</p></div>');
            unset($_SESSION['bulkMessage']);
        }
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
        if (!FmOrder::getOrdersEnabled()) {
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
                FmOrder::setOrderError();
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
        $result = file_get_contents($this->filePath);
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
            FmHelpers::get_woocommerce_version(),
            FmHelpers::get_plugin_version(),
            FmHelpers::COMMIT
        );
        $this->fmOutput->outputJSON($info);
        wp_die();
    }

    public function getAction($table)
    {
        $wp_list_table = _get_list_table($table);
        return $wp_list_table->current_action();
    }

    public function returnAndDie($return)
    {
        die($return);
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




    private function checkToken()
    {
        $pingToken = get_option('wcfyndiq_ping_token');

        $token = isset($_GET['pingToken']) ? $_GET['pingToken'] : null;

        if (is_null($token) || $token != $pingToken) {
            $this->fmOutput->showError(400, 'Bad Request', '400 Bad Request');
            wp_die();
        }
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

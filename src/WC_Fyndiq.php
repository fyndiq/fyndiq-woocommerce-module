<?php
//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class WC_Fyndiq
{
    private $filePath = null;


    /**
     * WooCommerce abstraction layer
     *
     * @var FmWoo - FmWoo instance
     */
    private $fmWoo;

    /**
     * Output class
     *
     * @var FyndiqOutput - FyndiqOutput instance
     */
    private $fmOutput = null;
    private $fmExport = null;

    const NOTICES = 'fyndiq_notices';

    /**
     * Key value for Fyndiq order column
     */
    const ORDERS = 'fyndiq_order';

    /**
     * Key value for Fyndiq product column
     */
    const EXPORT = 'fyndiq_export_column';

    /**
     * Key for the bulk action in export
     */
    const EXPORT_HANDLE = 'fyndiq_handle_export';

    /**
     * Key for the bulk action in not export
     */
    const EXPORT_UNHANDLE = 'fyndiq_handle_no_export';

    /**
     * Key for mark imported orders as handled
     */
    const ORDER_HANDLE = 'fyndiq_handle_order';

    /**
     * Key for mark imported orders as not handled
     */
    const ORDER_UNHANDLE = 'fyndiq_unhandle_order';

    /**
     * Key for delivery note action
     */
    const DELIVERY_NOTE = 'fyndiq_delivery';

    /**
     * Key for order import action
     */
    const ORDER_IMPORT = 'order_import';

    const ORDERS_DISABLE = 1;
    const ORDERS_ENABLE = 2;

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

        //This needs to be two-step to ensure compatibility with < PHP5.5
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
        FmDiagnostics::setHooks();
        FmProduct::setHooks();
        FmField::setHooks();
        FmSettings::setHooks();
        FmUpdate::setHooks();
    }

    /**
     * Take care of anything that needs WooCommerce to be loaded.
     * For instance, if you need access to the $woocommerce global
     */
    public function woocommerceLoaded()
    {
        //products
        $this->fmWoo->addAction(
            'woocommerce_process_shop_order_meta',
            array(&$this, 'fyndiqOrderHandledSave')
        );

        $this->fmWoo->addAction(
            'woocommerce_product_write_panel_tabs',
            array(&$this, 'fyndiqProductTab')
        );


        //product list
        $this->fmWoo->addFilter(
            'manage_edit-product_columns',
            array(&$this, 'fyndiqProductAddColumn')
        );
        $this->fmWoo->addAction(
            'manage_product_posts_custom_column',
            array(&$this, 'fyndiqProductColumnExport'),
            5,
            2
        );
        $this->fmWoo->addFilter(
            'manage_edit-product_sortable_columns',
            array(&$this, 'fyndiqProductColumnSort')
        );
        $this->fmWoo->addAction('pre_get_posts', array(&$this, 'fyndiqProductColumnSortBy'));
        $this->fmWoo->addAction('admin_notices', array(&$this, 'fyndiqBulkNotices'));
        $this->fmWoo->addAction('admin_notices', array(&$this, 'doBulkActionMessages'));


        //order list
        if (FmOrder::getOrdersEnabled()) {
            $this->fmWoo->addFilter(
                'manage_edit-shop_order_columns',
                array(&$this, 'fyndiqOrderAddColumn')
            );
            $this->fmWoo->addAction(
                'manage_shop_order_posts_custom_column',
                array(&$this, 'fyndiqOrderColumn'),
                5,
                2
            );
            $this->fmWoo->addFilter(
                'manage_edit-shop_order_sortable_columns',
                array(&$this, 'fyndiqOrderColumnSort')
            );
        }

        //Bulk Action
        //Inserts the JS for the appropriate dropdown items
        $this->fmWoo->addAction('admin_footer-edit.php', array(&$this, 'fyndiqAddBulkAction'));

        //Dispatcher for different bulk actions
        $this->fmWoo->addAction('load-edit.php', array(&$this, 'fyndiqBulkActionDispatcher'));

        //add_action('post_submitbox_misc_actions', array( &$this, 'fyndiq_order_edit_action'));
        $this->fmWoo->addAction('add_meta_boxes', array(&$this, 'fyndiqOrderMetaBoxes'));

        //notice for currency check
        $this->fmWoo->addAction('admin_notices', array(&$this, 'fyndiqAdminNotices'));

        //index
        $this->fmWoo->addAction('load-index.php', array($this->fmUpdate, 'updateNotification'));

        //orders
        $this->fmWoo->addAction('load-edit.php', array(&$this, 'fyndiqShowOrderError'));

        // admin javascripts
        add_action('admin_enqueue_scripts', array(&$this, 'fyndiqLoadJavascript'));

        //functions
        if (isset($_GET['fyndiq_feed'])) {
            $this->fmExport->generateFeed();
        }
        if (isset($_GET['fyndiq_orders'])) {
            FmOrder::generateOrders();
        }
        if (isset($_GET['fyndiq_notification'])) {
            $this->fmWoo->setDoingAJAX(true);
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
            '<a href="'. $checkUrl . '">' . $this->fmWoo->_x('Fyndiq Diagnostics', 'Link to diagnostics page') . '</a>'
        );
    }

    public function fyndiqLoadJavascript()
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
            $this->fmWoo->__('An unknown error occurred!'),
            $this->fmWoo->_x('Loading', 'Present continuous tense e.g. \'The program is loading\'') . '...',
            $this->fmWoo->_x('Done', 'Adjective e.g. \'The loading is done\'')
        );

        if (FmOrder::getOrdersEnabled()) {
            wp_enqueue_script('fyndiq_order', plugins_url('/js/order-import.js', __FILE__), array('jquery'), null);
        }
    }

    public function fyndiqOrderMetaBoxes()
    {
        $meta = $this->fmWoo->getPostCustom(FmOrder::getWordpressCurrentPostID());
        if (is_array($meta) &&
            array_key_exists('fyndiq_delivery_note', $meta) &&
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
                $this->fmWoo->__('Get Fyndiq delivery note')
            )
        );
    }


    //Hooked to WooCommerce_product_write_panel_tabs
    public function fyndiqProductTab()
    {
        $this->fmOutput->output(
            sprintf(
                '<li class="fyndiq_tab"><a href="#fyndiq_tab">%s</a></li>',
                $this->fmWoo->__('Fyndiq')
            )
        );
    }

    public function fyndiqShowOrderError()
    {
        if (isset($_GET['post_type']) && $_GET['post_type'] == 'shop_order') {
            $error = $this->fmWoo->getOption('wcfyndiq_order_error');
            if ($error) {
                $this->fmWoo->addAction('admin_notices', array(&$this, 'fyndiqShowOrderErrorNotice'));
                update_option('wcfyndiq_order_error', false);
            }
        }
    }

    public function fyndiqShowOrderErrorNotice()
    {
        return FmError::renderError(
            $this->fmWoo->__('Some orders from Fyndiq have failed to import, most likely due to insufficient stock or products with non-matching SKUs.'),
            FmError::CLASS_ERROR,
            $this->fmError
        );
    }

    /**
     *
     * Hooked action for saving orders handled status (woocommerce_process_shop_order_meta)
     *
     * @param int $orderId
     */
    public function fyndiqOrderHandledSave($orderId)
    {
        try {
            $orderObject = new FmOrder($orderId);
            $orderObject->setIsHandled($orderObject->getIsHandled());
        } catch (Exception $e) {
            FmError::handleError($e->getMessage());
        }
    }

    //Hooked function for adding columns to the products page (manage_edit-shop_order_columns)
    public function fyndiqOrderAddColumn($defaults)
    {
        $defaults[self::ORDERS] = $this->fmWoo->_x('Fyndiq Order ID', 'Header for column of ID numbers');
        return $defaults;
    }

    public function fyndiqOrderColumn($column, $orderId)
    {
        if ($column === self::ORDERS) {
            $fyndiqOrder = $this->fmWoo->getPostMeta($orderId, 'fyndiq_id', true);
            if ($fyndiqOrder != '') {
                return $this->fmOutput->output($fyndiqOrder);
            }
            $this->fmWoo->updatePostMeta($orderId, 'fyndiq_id', '-');
            $this->fmOutput->output('-');
        }
    }

    //Hooked to manage_edit-shop_order_sortable_columns
    public function fyndiqOrderColumnSort()
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
    public function fyndiqProductAddColumn($defaults)
    {
        $defaults[self::EXPORT] = $this->fmWoo->__('Fyndiq');
        return $defaults;
    }

    public function fyndiqProductColumnSort()
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

    public function fyndiqProductColumnExport($column, $postId)
    {
        try {
            $product = new FmProduct($postId);

            if ($column == self::EXPORT) {
                if ($product->isProductExportable()) {
                    if ($product->getIsExported()) {
                        $this->fmOutput->output($this->fmWoo->_x('Exported', 'verb e.g. It has been exported'));
                    } else {
                        $this->fmOutput->output($this->fmWoo->_x('Not exported', 'verb e.g. It has not been exported'));
                    }
                } else {
                    $this->fmOutput->output($this->fmWoo->_x('Not exportable', 'adjective e.g. The TV was not exportable'));
                }
            }
        } catch (Exception $e) {
            FmError::handleError($e->getMessage());
        }
    }


    public function fyndiqAdminNotices()
    {
        if ($this->checkCurrency()) {
            $message = sprintf(
                '<strong>%s</strong>: %s %s: %s',
                $this->fmWoo->_x('Unsupported Currency', 'Error message - the selected currency is invalid'),
                $this->fmWoo->__('Fyndiq only supports trading in EUR or SEK. Please change your WooCommerce settings to the correct currency.'),
                $this->fmWoo->_x('Current Currency', 'Stating what the current currency is set to, with trailing colon'),
                $this->fmWoo->getWoocommerceCurrency()
            );
            FmError::renderErrorRaw($message, FmError::CLASS_ERROR, $this->fmOutput);
        }
        if ($this->checkCountry()) {
            $message = sprintf(
                '<strong>%s</strong>: %s %s: %s',
                $this->fmWoo->__('Unsupported Country'),
                $this->fmWoo->__('Fyndiq only currently supports trading in Sweden and Germany. Please change your WooCommerce settings to the correct country.'),
                $this->fmWoo->_x('Current Country', 'Stating what the current country is set to, with trailing colon'),
                $this->fmWoo->WC()->countries->get_base_country()
            );
            FmError::renderErrorRaw($message, FmError::CLASS_ERROR, $this->fmOutput);
        }
        if ($this->checkCredentials()) {
            $url = admin_url('admin.php?page=wc-settings&tab=wcfyndiq');
            $message = (sprintf(
                '<strong>%s</strong>: %s <a href="%s">%s</a>',
                $this->fmWoo->_x('Fyndiq Credentials', 'header for error message'),
                $this->fmWoo->__('You need to add your Fyndiq credentials to the settings to start using this plugin. You can find these settings here'),
                $url,
                $this->fmWoo->_x('Woocommerce Settings > Fyndiq', 'Link to respective page')
            ));
            FmError::renderErrorRaw($message, FmError::CLASS_ERROR, $this->fmOutput);
        }
        //TODO: This probably needs to use our error handler properly, pretty sure that NOTICES is evil
        if (isset($_SESSION[self::NOTICES])) {
            $notices = $_SESSION[self::NOTICES];
            foreach ($notices as $type => $noticegroup) {
                //TODO: Class is undefined?
                echo '<div class="fn_message '.$class.'">';
                $message = '<strong>'.$this->fmWoo->_x('Fyndiq Validation Error', 'header for error message').'</strong>';
                $message .= '<ul>';
                foreach ($noticegroup as $notice) {
                    $message .= '<li>'.wp_kses($notice, wp_kses_allowed_html('post')).'</li>';
                }
                $message .= '</ul>';
                $message .= '<p>' . $this->fmWoo->__('This product can not be exported to Fyndiq until it meets the Fyndiq validation criteria') . '</p>';
                $class = 'update' === $type ? 'updated' : $type;
                FmError::renderErrorRaw($message, $class, $this->fmOutput);
            }
            unset($_SESSION[self::NOTICES]);
        }
    }

    /**
     * Adds bulk actions to the drop-down by reading array and generating relevant JS
     */
    public function fyndiqAddBulkAction()
    {
        global $post_type;

        //Define bulk actions for the various page types
        $bulkActionArray = array(
            'product' => array(
                self::EXPORT_HANDLE => $this->fmWoo->_x('Export to Fyndiq', 'verb'),
                self::EXPORT_UNHANDLE => $this->fmWoo->_x('Remove from Fyndiq', 'verb'),
            ),
            'shop_order' => array(
                self::DELIVERY_NOTE => $this->fmWoo->__('Get Fyndiq delivery note'),
                self::ORDER_IMPORT => $this->fmWoo->_x('Import From Fyndiq', 'verb'),
                self::ORDER_HANDLE => $this->fmWoo->__('Mark order(s) as handled on Fyndiq'),
                self::ORDER_UNHANDLE => $this->fmWoo->__('Mark order(s) as not handled on Fyndiq')
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

        if ($post_type === 'shop_order' && FmOrder::getOrdersEnabled()) {
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
     * This function acts as a dispatcher, taking various actions and routing them to the appropriate function*
     *
     * @return mixed - return of function called by dispatcher
     */
    public function fyndiqBulkActionDispatcher()
    {
        try {
            $action = $this->getAction('WP_Posts_List_Table');
            switch ($this->getAction('WP_Posts_List_Table')) {
                case self::ORDER_HANDLE:
                    return FmOrder::orderHandleBulkAction(true);
                case self::ORDER_UNHANDLE:
                    return FmOrder::orderHandleBulkAction(false);
                case self::DELIVERY_NOTE:
                    return FmOrder::deliveryNoteBulkAction();
                case self::EXPORT_HANDLE:
                    return FmProduct::productExportBulkAction(FmProduct::EXPORTED, $action);
                case self::EXPORT_UNHANDLE:
                    return FmProduct::productExportBulkAction(FmProduct::NOT_EXPORTED, $action);
                default:
                    return false;
            }
        } catch (Exception $e) {
            FmError::handleError($e->getMessage());
        }
    }

    public function doBulkActionMessages()
    {
        if (isset($_SESSION['bulkMessage']) && $GLOBALS['pagenow'] === 'edit.php') {
            FmError::renderError($_SESSION['bulkMessage'], FmError::CLASS_UPDATED, $this->fmOutput);
            unset($_SESSION['bulkMessage']);
        }
    }

    public function fyndiqBulkNotices()
    {
        global $post_type, $pagenow;

        if ($pagenow == 'edit.php' && isset($_REQUEST['fyndiq_removed']) && (int)$_REQUEST['fyndiq_removed']) {
            $message = sprintf(
                _n(
                    'Product removed from Fyndiq',
                    '%s products removed from Fyndiq',
                    $_REQUEST['fyndiq_removed']
                ),
                number_format_i18n($_REQUEST['fyndiq_removed'])
            );
            return FmError::renderError($message, FmError::CLASS_UPDATED, $this->fmOutput);
        }
        if ($pagenow == 'edit.php' && isset($_REQUEST['fyndiq_exported']) && (int)$_REQUEST['fyndiq_exported']) {
            $message = sprintf(
                _n(
                    'Product exported to Fyndiq',
                    '%s products exported to Fyndiq',
                    $_REQUEST['fyndiq_exported']
                ),
                number_format_i18n($_REQUEST['fyndiq_exported'])
            );
            return FmError::renderError($message, FmError::CLASS_UPDATED, $this->fmOutput);
        }
    }

    /**
     * Handles notification calls
     *
     *  @param array $get $_GET array
     *
     * @return bool
     */
    public function handleNotification($get)
    {
        // Disable page chrome
        $this->fmWoo->setDoingAJAX(true);
        if (isset($get['event'])) {
            switch ($get['event']) {
                case 'order_created':
                    return $this->orderCreated($get);
                case 'ping':
                    $this->checkToken($get);
                    return $this->ping();
                case 'debug':
                    if ($this->isDebugEnabled()) {
                        $this->checkToken($get);
                        return $this->debug();
                    }
                    $this->fmOutput->showError(403, 'Forbidden', 'Forbidden');
                    return $this->fmWoo->wpDie();
            }
        }
        return $this->fmOutput->showError(400, 'Bad Request', '400 Bad Request');
    }

    /**
     * Handles new order notification
     *
     *  @param array $get - $_GET array
     *
     * @return bool
     */
    protected function orderCreated($get)
    {

        if (!FmOrder::getOrdersEnabled()) {
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
     * Handles the debug page
     *
     * @return bool
     */
    protected function debug()
    {
        FyndiqUtils::debugStart();
        FyndiqUtils::debug('USER AGENT', FmHelpers::getUserAgent());
        $languageId = $this->fmWoo->WC()->countries->get_base_country();
        FyndiqUtils::debug('language', $languageId);
        FyndiqUtils::debug('taxonomy', FmHelpers::getAllTerms());
        $return = $this->fmExport->feedFileHandling();
        $result = file_get_contents($this->filePath);
        FyndiqUtils::debug('$result', $result, true);
        FyndiqUtils::debugStop();
        return true;
    }

    /**
     * Handles ping notification
     *
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

    protected function checkToken($get)
    {
        $pingToken = $this->fmWoo->getOption('wcfyndiq_ping_token');

        $token = isset($get['pingToken']) ? $get['pingToken'] : null;

        if (is_null($token) || $token != $pingToken) {
            $this->fmOutput->showError(400, 'Bad Request', '400 Bad Request');
            $this->fmWoo->wpDie();
        }
    }

    /**
     * Returns true if debug is enabled
     *
     * @return bool - whether debug is enabled
     */
    protected function isDebugEnabled()
    {
        return intval($this->fmWoo->getOption('wcfyndiq_enable_debug')) === FmHelpers::DEBUG_ENABLED;
    }
}

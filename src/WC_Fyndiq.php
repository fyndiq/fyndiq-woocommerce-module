<?php

//Define some globals
$upload_dir = wp_upload_dir();
$GLOBALS['filePath'] = $upload_dir['basedir'] . '/fyndiq-feed.csv';
$GLOBALS['fmUpdate'] = new FmUpdate();
$GLOBALS['fmOutput'] = new FyndiqOutput();
$GLOBALS['fmExport'] = new FmExport($GLOBALS['filePath'], $GLOBALS['fmOutput']);

//Load locale
add_action('init', function () {
    load_plugin_textdomain('fyndiq', false, dirname(plugin_basename(__FILE__)) . '/translations/');
});


/**
 *
 * onWoocommerceLoaded
 *
 * Anything in here fires only once WC is loaded due to being placed after WC in the action queue
 *
 */
add_action('init', function () {
    //Checker Page
    add_filter('plugin_action_links_' . plugin_basename(dirname(__FILE__) . '/woocommerce-fyndiq.php'), 'fyndiq_action_links');

    //index
    add_action('load-index.php', array($GLOBALS['fmUpdate'], 'updateNotification'));

    //orders
    add_action('load-edit.php', 'fyndiq_show_order_error');

}, 250);


function fyndiq_action_links($links)
{
    $checkUrl = esc_url(get_admin_url(null, 'admin.php?page=fyndiq-check'));
    $settingUrl = esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=products&section=wcfyndiq'));
    $links[] = '<a href="' . $settingUrl . '">' . __('Settings', 'fyndiq') . '</a>';
    $links[] = '<a href="' . $checkUrl . '">' . __('Fyndiq Check', 'fyndiq') . '</a>';
    return $links;
}

function updateUrls()
{
    //Generate pingtoken
    $pingToken = md5(uniqid());
    update_option('wcfyndiq_ping_token', $pingToken);

    $data = array(
        FyndiqUtils::NAME_PRODUCT_FEED_URL => get_site_url() . '/?fyndiq_feed&pingToken=' . $pingToken,
        FyndiqUtils::NAME_PING_URL => get_site_url() .
            '/?fyndiq_notification=1&event=ping&pingToken=' . $pingToken
    );
    if (FmHelpers::ordersEnabled()) {
        $data[FyndiqUtils::NAME_NOTIFICATION_URL] = get_site_url() . '/?fyndiq_notification=1&event=order_created';
    }
    return FmHelpers::callApi('PATCH', 'settings/', $data);
}


function fyndiq_show_order_error()
{
    if (isset($_GET['post_type']) && $_GET['post_type'] == 'shop_order') {
        $error = get_option('wcfyndiq_order_error');
        if ($error) {
            add_action('admin_notices', 'fyndiq_show_order_error_notice');
            update_option('wcfyndiq_order_error', false);
        }
    }
}

function fyndiq_show_order_error_notice()
{
    ?>
    <div class="error">
        <p><?php _e('Some Fyndiq Orders failed to be imported, most likely due to stock or couldn\'t find product on Reference.', 'fyndiq'); ?></p>
    </div>
    <?php
}

function fyndiq_show_setting_error_notice()
{
    $GLOBALS['fmOutput']->output(sprintf(
        '<div class="error"><p>%s</p></div>',
        __('Fyndiq credentials was wrong, try again.', 'fyndiq')
    ));
}

/**
 * This is validating product data and show error if
 * it is not following the fyndiq validations
 */
function fyndiq_product_validate($post_id)
{
    if (getExportState() == EXPORTED) {
        $error = false;
        $postTitleLength = mb_strlen($_POST['post_title']);
        if ($postTitleLength < FyndiqFeedWriter::$minLength[FyndiqFeedWriter::PRODUCT_TITLE] ||
            $postTitleLength > FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::PRODUCT_TITLE]
        ) {
            add_fyndiq_notice(
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

        $postDescriptionLength = mb_strlen($GLOBALS['fmExport']->getDescriptionPOST());
        if ($postDescriptionLength < FyndiqFeedWriter::$minLength[FyndiqFeedWriter::PRODUCT_DESCRIPTION] ||
            $postDescriptionLength > FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::PRODUCT_DESCRIPTION]
        ) {
            add_fyndiq_notice(
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
            $postSKULength > FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::ARTICLE_SKU]
        ) {
            add_fyndiq_notice(
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
            add_fyndiq_notice(
                sprintf(
                    __('Regular Price needs to be set above 0, now it is: %s', 'fyndiq'),
                    $postRegularPrice
                ),
                'error'
            );
            $error = true;
        }

        if ($error) {
            update_post_meta($post_id, '_fyndiq_export', NOT_EXPORTED);
        }
    }
}


function notification_handle()
{
    define('DOING_AJAX', true);
    if (isset($_GET['event'])) {
        $event = $_GET['event'];
        $eventName = $event ? 'notice_' . $event : false;
        if ($eventName) {
            if ($eventName[0] != '_' && method_exists($this, $eventName)) {
                checkToken();
                return $eventName();
            }
        }
    }
    $GLOBALS['fmOutput']->showError(400, 'Bad Request', '400 Bad Request');
    wp_die();
}

function notice_order_created()
{
    if (!FmHelpers::ordersEnabled()) {
        wp_die('Orders is disabled');
    }
    $order_id = $_GET['order_id'];
    $orderId = is_numeric($order_id) ? intval($order_id) : 0;
    if ($orderId > 0) {
        try {
            $ret = FmHelpers::callApi('GET', 'orders/' . $orderId . '/');

            $fyndiqOrder = $ret['data'];

            $orderModel = new FmOrderHelper();

            if (!$orderModel->orderExists($fyndiqOrder->id)) {
                $orderModel->createOrder($fyndiqOrder);
            }
        } catch (Exception $e) {
            setOrderError();
            $GLOBALS['fmOutput']->showError(500, 'Internal Server Error', $e);
        }

        wp_die();
    }
}

function notice_debug()
{
    FyndiqUtils::debugStart();
    FyndiqUtils::debug('USER AGENT', FmHelpers::get_user_agent());
    $languageId = WC()->countries->get_base_country();
    FyndiqUtils::debug('language', $languageId);
    FyndiqUtils::debug('taxonomy', getAllTerms());
    $return = $GLOBALS['fmExport']->feedFileHandling();
    $result = file_get_contents($GLOBALS['filePath']);
    FyndiqUtils::debug('$result', $result, true);
    FyndiqUtils::debugStop();
    wp_die();
}

function notice_ping()
{
    $GLOBALS['fmOutput']->flushHeader('OK');
    $lastPing = get_option('wcfyndiq_ping_time');
    $lastPing = $lastPing ? $lastPing : false;
    $locked = $lastPing && $lastPing > strtotime('15 minutes ago');
    if (!$locked) {
        update_option('wcfyndiq_ping_time', time());
        try {
            $GLOBALS['fmExport']->feedFileHandling();
            update_product_info();
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }
    wp_die();
}

function notice_info()
{

    $info = FyndiqUtils::getInfo(
        FmHelpers::PLATFORM,
        FmHelpers::get_woocommerce_version(),
        FmHelpers::get_plugin_version(),
        FmHelpers::COMMIT
    );
    $GLOBALS['fmOutput']->outputJSON($info);
    wp_die();
}

function generate_orders()
{
    define('DOING_AJAX', true);
    try {
        $orderFetch = new FmOrderFetch(false, true);
        $result = $orderFetch->getAll();
        update_option('wcfyndiq_order_time', time());
    } catch (Exception $e) {
        $result = $e->getMessage();
        setOrderError();
    }
    $GLOBALS['fmOutput']->outputJSON($result);
    wp_die();
}

function update_product_info()
{
    $productFetch = new FmProductFetch();
    $productFetch->getAll();
}

function getAction($table)
{
    $wp_list_table = _get_list_table($table);

    return $wp_list_table->current_action();
}

function getRequestPost()
{
    return isset($_REQUEST['post']) ? $_REQUEST['post'] : null;
}

function returnAndDie($return)
{
    die($return);
}

function bulkRedirect($report_action, $changed, $post_ids)
{
    $sendback = add_query_arg(
        array('post_type' => 'product', $report_action => $changed, 'ids' => join(',', $post_ids)),
        ''
    );
    wp_redirect($sendback);
    exit();
}

function getPostId()
{
    return get_the_ID();
}

function getExportState()
{
    return isset($_POST['_fyndiq_export']) ? EXPORTED : NOT_EXPORTED;
}


/**
 *
 * Updates the orders with regards to
 *
 * @param $posts - an array of posts in the structure:
 *
 * array(
 *        array(
 *              id => postIDvalue,
 *              marked => boolean
 *              ),
 *                  ...
 * )
 *
 *
 */
function updateHandledStatuses($posts)
{

    foreach ($posts as &$post) {
        $orderObject = new FmOrder($post);
        $orderObject->setIsHandled((bool)$post['marked']);

        //This modifies the array so that it will work with callAPI()
        $post['id'] = $orderObject->getFyndiqOrderID();
        $post = (object)$post;
    }

    $data = new stdClass();
    $data->orders = $posts;
    FmHelpers::callApi('POST', 'orders/marked/', $data);
}

function getPricePercentage()
{
    return isset($_POST['_fyndiq_price_percentage']) ? $_POST['_fyndiq_price_percentage'] : '';
}

function checkCurrency()
{
    $currency = get_woocommerce_currency();
    return !in_array($currency, FyndiqUtils::$allowedCurrencies);
}

function checkCountry()
{
    $country = WC()->countries->get_base_country();
    return !in_array($country, FyndiqUtils::$allowedMarkets);
}

function checkCredentials()
{
    $username = get_option('wcfyndiq_username');
    $token = get_option('wcfyndiq_apitoken');

    return (empty($username) || empty($token));
}


function add_fyndiq_notice($message, $type = 'update')
{
    $notices = array();
    if (isset($_SESSION[NOTICES])) {
        $notices = $_SESSION[NOTICES];
    }

    if (!isset($notices[$type])) {
        $notices[$type] = array();
    }

    $notices[$type][] = $message;

    $_SESSION[NOTICES] = $notices;
}

function checkToken()
{
    $pingToken = get_option('wcfyndiq_ping_token');

    $token = isset($_GET['pingToken']) ? $_GET['pingToken'] : null;

    if (is_null($token) || $token != $pingToken) {
        $GLOBALS['fmOutput']->showError(400, 'Bad Request', '400 Bad Request');
        wp_die();
    }
}


function setOrderError()
{
    if (get_option('wcfyndiq_order_error') !== false) {
        update_option('wcfyndiq_order_error', true);
    } else {
        add_option('wcfyndiq_order_error', true, null, false);
    }
}

function getAllTerms()
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

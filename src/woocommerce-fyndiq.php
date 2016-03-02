<?php
/**
 * Plugin Name: Fyndiq Woocommerce
 * Plugin URI: http://developers.fyndiq.com/fyndiq-built-integrations/
 * Description: Export products and import orders to woocommerce from Fyndiq.
 * Version: 1.0.3
 * Author: Fyndiq AB
 * Author URI: http://fyndiq.se
 * License: Commercial
 */

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;


//Loads the dependency library
require_once('include/dependency.php');


//Include plugin.php so that is_plugin_inactive() works
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if (is_plugin_inactive('woocommerce/woocommerce.php')) {
    // We're not going further if there's no WooCommerce plugin.
    return;
}


// Handle deactivating the module.
register_deactivation_hook( __FILE__, 'fyndiq_deactivate' );
function fyndiq_deactivate() {
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
    // Delete all settings
    delete_option('wcfyndiq_ping_token');
    delete_option('wcfyndiq_username');
    delete_option('wcfyndiq_apitoken');
}


// Require the necessary files
require_once('models/FmPost.php');
require_once('classes/FmErrorHandler.php');
require_once('include/api/fyndiqAPI.php');
require_once('classes/FmHelpers.php');
require_once('classes/FmUpdate.php');
require_once('classes/FmExport.php');
require_once('classes/FmField.php');
require_once('include/shared/src/init.php');
require_once('models/FmOrder.php');
require_once('models/FmOrderFetch.php');
require_once('models/FmProduct.php');
require_once('models/FmProductFetch.php');
require_once('WC_Fyndiq.php');


//Let's get the ball rolling.
new WC_Fyndiq();

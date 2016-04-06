<?php
/**
 * Plugin Name: Fyndiq Woocommerce
 * Plugin URI: http://developers.fyndiq.com/fyndiq-built-integrations/
 * Description: Export products and import orders to woocommerce from Fyndiq.
 * Version: 1.0.3
 * Author: Fyndiq AB
 * Author URI: http://fyndiq.se
 * License: Commercial
 * PHP Version 5
 */

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

//Include plugin.php so that is_plugin_inactive() works
require_once ABSPATH . 'wp-admin/includes/plugin.php';

require_once 'dependency.php';

if (is_plugin_active('woocommerce/woocommerce.php')) {
    // Handle deactivating the module.
    register_deactivation_hook(__FILE__, 'fyndiqDeactivate');
    function fyndiqDeactivate()
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
        // Delete all settings
        delete_option('wcfyndiq_ping_token');
        delete_option('wcfyndiq_username');
        delete_option('wcfyndiq_apitoken');
    }

    // Require the necessary files
    require_once 'classes/FmWoo.php';
    require_once 'models/FmPost.php';
    require_once 'classes/FmError.php';
    require_once 'include/api/fyndiqAPI.php';
    require_once 'classes/FmHelpers.php';
    require_once 'classes/FmUpdate.php';
    require_once 'classes/FmExport.php';
    require_once 'classes/FmField.php';
    require_once 'include/shared/src/init.php';
    require_once 'models/FmOrder.php';
    require_once 'models/FmOrderFetch.php';
    require_once 'models/FmProduct.php';
    require_once 'classes/FmSettings.php';
    require_once 'classes/FmDiagnostics.php';

    require_once 'WC_Fyndiq.php';

    //Let's get the ball rolling.
    new WC_Fyndiq();
}

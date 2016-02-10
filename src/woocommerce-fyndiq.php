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

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('FMOrder')) {
        session_start();
        require_once('globals.php');
        require_once('include/api/fyndiqAPI.php');
        require_once('classes/FmHelpers.php');
        require_once('models/FmOrder.php');
        require_once('models/FmProduct.php');
        require_once('classes/FmUpdate.php');
        require_once('classes/FmExport.php');
        require_once('include/shared/src/init.php');
        require_once('classes/FmOrderHelper.php');
        require_once('models/FmOrderFetch.php');
        require_once('classes/FmProductHelper.php');
        require_once('models/FmProductFetch.php');
        require_once('getDispatcher.php');
        require_once('deactivate.php');
        require_once('scripts.php');
        require_once('fields.php');
        require_once('bulkActions.php');
        require_once('metaboxes.php');
        require_once('settings.php');
        require_once('columns.php');
        require_once('diagnostics.php');

        //Play ball
        require_once('WC_Fyndiq.php');
    }
}

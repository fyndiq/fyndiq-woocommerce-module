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
    if (!class_exists('WC_Fyndiq')) {
        session_start();

        require_once('classes/FmErrorHandler.php');
        require_once('include/api/fyndiqAPI.php');
        require_once('classes/FmHelpers.php');
        require_once('classes/FmUpdate.php');
        require_once('classes/FmExport.php');
        require_once('classes/FmField.php');
        require_once('include/shared/src/init.php');
        require_once('models/FmPost.php');
        require_once('models/FmOrder.php');
        require_once('models/FmOrderFetch.php');
        require_once('models/FmProduct.php');
        require_once('models/FmProductFetch.php');

        //Dynamically sets the WooCommerce version as a constant
        define('WC_VERSION', FmHelpers::get_woocommerce_version());


        require_once('WC_Fyndiq.php');
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        // finally instantiate our plugin class and add it to the set of globals
        $GLOBALS['wc_fyndiq'] = new WC_Fyndiq( __FILE__);
    }
}

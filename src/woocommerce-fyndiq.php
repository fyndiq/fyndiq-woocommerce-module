<?php
/**
 * Plugin Name: Fyndiq Woocommerce
 * Plugin URI: http://developers.fyndiq.com/fyndiq-built-integrations/
 * Description: Export products and import orders to woocommerce from Fyndiq.
 * Version: 1.0.2
 * Author: Fyndiq AB
 * Author URI: http://fyndiq.se
 * License: Commercial
 */

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('WC_Fyndiq')) {
        session_start();
        require_once('include/api/fyndiqAPI.php');
        require_once('classes/FmHelpers.php');
        require_once('classes/FmUpdate.php');
        require_once('classes/FmExport.php');
        require_once('include/shared/src/init.php');
        require_once('models/FmOrder.php');
        require_once('models/FmOrderFetch.php');
        require_once('models/FmProduct.php');
        require_once('models/FmProductFetch.php');
        require_once('WC_Fyndiq.php');

        $fmOuput = new FyndiqOutput();

        // finally instantiate our plugin class and add it to the set of globals
        $GLOBALS['wc_fyndiq'] = new WC_Fyndiq($fmOuput, __FILE__);
    }
}

<?php
/**
 * Plugin Name: Fyndiq Woocommerce
 * Plugin URI: http://fyndiq.se
 * Description: Export products and import orders to woocommerce from Fyndiq.
 * Version: 1.0.0
 * Author: Fyndiq AB
 * Author URI: http://fyndiq.se
 * License: MIT
 */

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('WC_Fyndiq')) {
        require_once('FmHelpers.php');
        require_once('include/shared/src/init.php');
        require_once('models/FmOrder.php');
        require_once('models/FmOrderFetch.php');
        require_once('models/FmProduct.php');
        require_once('models/FmProductFetch.php');
        require_once('WC_Fyndiq.php');

        $fmOuput = new FyndiqOutput();

        // finally instantiate our plugin class and add it to the set of globals
        $GLOBALS['wc_fyndiq'] = new WC_Fyndiq($fmOuput);
    }
}

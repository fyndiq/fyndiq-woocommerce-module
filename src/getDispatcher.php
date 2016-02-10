<?php
/**
 *This acts as a dispatcher for any GET requests from Fyndiq
 */

if (isset($_GET['fyndiq_feed'])) {
    $GLOBALS['fmExport']->generate_feed();
}
if (isset($_GET['fyndiq_orders'])) {
    generate_orders();
}
if (isset($_GET['fyndiq_products'])) {
    define('DOING_AJAX', true);
    update_product_info();
    $GLOBALS['fmOutput']->outputJSON(array('status' => 'ok'));
    wp_die();
}
if (isset($_GET['fyndiq_notification'])) {
    notification_handle();
}

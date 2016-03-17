<?php
$_tests_dir = getenv('WP_TESTS_DIR');
if (! $_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin()
{
    require '/var/www/html/woocommerce/wp-content/plugins/woocommerce/woocommerce.php';
    // Require the necessary files
    require_once(dirname(__FILE__) . '/../src/models/FmPost.php');
    require_once(dirname(__FILE__) . '/../src/classes/FmErrorHandler.php');
    require_once(dirname(__FILE__) . '/../src/include/api/fyndiqAPI.php');
    require_once(dirname(__FILE__) . '/../src/classes/FmHelpers.php');
    require_once(dirname(__FILE__) . '/../src/classes/FmUpdate.php');
    require_once(dirname(__FILE__) . '/../src/classes/FmExport.php');
    require_once(dirname(__FILE__) . '/../src/classes/FmField.php');
    require_once(dirname(__FILE__) . '/../src/include/shared/src/init.php');
    require_once(dirname(__FILE__) . '/../src/models/FmOrder.php');
    require_once(dirname(__FILE__) . '/../src/models/FmOrderFetch.php');
    require_once(dirname(__FILE__) . '/../src/models/FmProduct.php');
    require_once(dirname(__FILE__) . '/../src/WC_Fyndiq.php');
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';

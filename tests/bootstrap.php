<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/Applications/MAMP/htdocs/wordpress-develop/tests/phpunit';
}

//require_once $_tests_dir . '/includes/functions.php';
/*
function _manually_load_plugin() {
    $_tests_dir = getenv( 'WP_TESTS_DIR' );
    if ( ! $_tests_dir ) {
    	$_tests_dir = '/Applications/MAMP/htdocs/wordpress-develop';
    }
    require $_tests_dir . '/src/wp-content/plugins/woocommerce/woocommerce.php';
	require dirname( dirname( __FILE__ ) ) . '/woocommerce-fyndiq.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
*/
$GLOBALS['wp_tests_options'] = array(
    'active_plugins' => array('woocommerce-fyndiq/woocommerce-fyndiq.php', 'woocommerce/woocommerce.php')
);

// install WC
//tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );

require $_tests_dir . '/includes/bootstrap.php';

WC_Install::install();
// reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374
$GLOBALS['wp_roles']->reinit();
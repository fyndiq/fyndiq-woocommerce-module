<?php
/**
 * WooCommerce Unit Tests Bootstrap
 *
 * @since 2.2
 */
class WC_Fyndiq_Unit_Tests_Bootstrap {
	/** @var \WC_Unit_Tests_Bootstrap instance */
	protected static $instance = null;
	/** @var string directory where wordpress-tests-lib is installed */
	public $wp_tests_dir;
	/** @var string testing directory */
	public $tests_dir;
	/** @var string plugin directory */
	public $plugin_dir;
	/**
	 * Setup the unit testing environment.
	 *
	 * @since 2.2
	 */
	public function __construct() {
		ini_set( 'display_errors','on' );
		error_reporting( E_ALL );
		$this->tests_dir    = dirname( __FILE__ );
		$this->plugin_dir   = '/var/www/html/woocommerce/wp-content/plugins/woocommerce/';
		$this->wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/opt/wptests/wordpress-tests-lib';
		// load test function so tests_add_filter() is available
		require_once( $this->wp_tests_dir . '/includes/functions.php' );
		// load WC
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_wc' ) );
		// install WC
		tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );
		// load the WP testing environment
		require_once( $this->wp_tests_dir . '/includes/bootstrap.php' );
		// load WC testing framework
		$this->includes();
	}
	/**
	 * Load WooCommerce.
	 *
	 * @since 2.2
	 */
	public function load_wc() {
		require_once( $this->plugin_dir . '/woocommerce.php' );
	}
	/**
	 * Install WooCommerce after the test environment and WC have been loaded.
	 *
	 * @since 2.2
	 */
	public function install_wc() {
		// clean existing install first
		define( 'WP_UNINSTALL_PLUGIN', true );
		update_option( 'woocommerce_status_options', array( 'uninstall_data' => 1 ) );
		include( $this->plugin_dir . '/uninstall.php' );
		WC_Install::install();
		// reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374
		$GLOBALS['wp_roles']->reinit();
		echo "Installing WooCommerce..." . PHP_EOL;
	}
	/**
	 * Load Fyndiq-specific test cases and factories.
	 *
	 * @since 2.2
	 */
	public function includes() {
        // Required classes in shared
        require_once(dirname(__FILE__) . '/../src/include/shared/src/init.php');

        // Classes
        require_once(dirname(__FILE__) . '/../src/classes/FmHelpers.php');
        require_once(dirname(__FILE__) . '/../src/classes/FmUpdate.php');
        require_once(dirname(__FILE__) . '/../src/classes/FmExport.php');
        require_once(dirname(__FILE__) . '/../src/classes/FmField.php');
        require_once(dirname(__FILE__) . '/../src/classes/FmDiagnostics.php');
        require_once(dirname(__FILE__) . '/../src/classes/FmSettings.php');
        require_once(dirname(__FILE__) . '/../src/classes/FmError.php');

        // API
        require_once(dirname(__FILE__) . '/../src/include/api/fyndiqAPI.php');

        // Models
        require_once(dirname(__FILE__) . '/../src/models/FmPost.php');
        require_once(dirname(__FILE__) . '/../src/models/FmOrder.php');
        require_once(dirname(__FILE__) . '/../src/models/FmOrderFetch.php');
        require_once(dirname(__FILE__) . '/../src/models/FmProduct.php');
        require_once(dirname(__FILE__) . '/../src/WC_Fyndiq.php');
	}
	/**
	 * Get the single class instance.
	 *
	 * @since 2.2
	 * @return WC_Fyndiq_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
WC_Fyndiq_Unit_Tests_Bootstrap::instance();

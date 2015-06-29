<?php
require_once('include/api/fyndiqAPI.php');
class FmHelpers
{

    public static function apiConnectionExists()
    {
        return !is_null(get_option('wcfyndiq_username')) && !is_null(get_option('wcfyndiq_apitoken'));
    }

    public static function allSettingsExist()
    {
        return FmConfig::getBool('language') && FmConfig::getBool('currency');
    }

    /**
     * wrappers around FyndiqAPI
     * uses stored connection credentials for authentication
     *
     * @param $method
     * @param $path
     * @param array $data
     * @return mixed
     */
    public static function callApi($method, $path, $data = array())
    {
        if ( ! function_exists( 'get_plugins' ) )
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        $plugin_folder = get_plugins( '/' . 'woocommerce-fyndiq' );
        $plugin_file = 'woocommerce-fyndiq.php';

        // If the plugin version number is set, return it
        if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
            $plugin_version = $plugin_folder[$plugin_file]['Version'];
        }
        else {
            $plugin_version = "0.0.0";
        }

        $username = get_option('wcfyndiq_username');
        $apiToken = get_option('wcfyndiq_apitoken');

        $userAgent = "FyndiqMerchantWoocommerce" . $plugin_version . "-" . self::get_woocommerce_version();

        return FyndiqAPICall::callApiRaw($userAgent, $username, $apiToken, $method, $path, $data,
            array('FyndiqAPI', 'call'));
    }

    static function get_woocommerce_version() {
        // If get_plugins() isn't available, require it
        if ( ! function_exists( 'get_plugins' ) )
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        // Create the plugins folder and file variables
        $plugin_folder = get_plugins( '/' . 'woocommerce' );
        $plugin_file = 'woocommerce.php';

        // If the plugin version number is set, return it
        if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
            return $plugin_folder[$plugin_file]['Version'];

        } else {
            // Otherwise return null
            return NULL;
        }
    }
}
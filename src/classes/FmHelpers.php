<?php
//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

/**
 * Class FmHelpers
 */
class FmHelpers
{

    /** Commit hash constant*/
    const COMMIT = 'XXXXXX';

    /** Plugin platform*/
    const PLATFORM = 'WooCommerce';

    /** Debug disabled truth value */
    const DEBUG_DISABLED = 0;

    /** Debug enabled truth value */
    const DEBUG_ENABLED = 1;

    /**
     * Checks whether options for an API connection have been set
     *
     * @return bool - true if options have been set
     */
    public static function apiConnectionExists()
    {
        return !is_null(get_option('wcfyndiq_username')) && !is_null(get_option('wcfyndiq_apitoken'));
    }


    /**
     * Wrappers around FyndiqAPI
     * uses stored connection credentials for authentication
     *
     * @param $method
     * @param $path
     * @param array $data
     * @return mixed
     * @throws Exception - when the API call fails
     */
    public static function callApi($method, $path, $data = array())
    {
        $username = get_option('wcfyndiq_username');
        $apiToken = get_option('wcfyndiq_apitoken');

        $userAgent = self::getUserAgent();

        return FyndiqAPICall::callApiRaw(
            $userAgent,
            $username,
            $apiToken,
            $method,
            $path,
            $data,
            array('FyndiqAPI', 'call')
        );
    }

    /**
     * Gets the version of the Fyndiq plugin
     *
     * @return string - version string of the plugin
     */
    static function getPluginVersion()
    {
        $plugin_folder = get_plugins('/' . 'woocommerce-fyndiq');
        $plugin_file = 'woocommerce-fyndiq.php';

        // If the plugin version number is set, return it
        if (isset($plugin_folder[$plugin_file]['Version'])) {
            $plugin_version = $plugin_folder[$plugin_file]['Version'];
        } else {
            $plugin_version = "0.0.0";
        }

        return $plugin_version;
    }

    /**
     * Gets the user agent of the browser
     *
     * @return string - user agent string
     */
    public static function getUserAgent()
    {
        return FyndiqUtils::getUserAgentString(
            self::PLATFORM,
            WC()->version,
            'module',
            self::getPluginVersion(),
            self::COMMIT
        );
    }


    /**
     * TODO: Why do we do this filtering stuff?
     *
     * @return bool|mixed|void
     */
    static function fyndiq_wc_tax_enabled()
    {
        if (function_exists('wc_tax_enabled')) {
            return wc_tax_enabled();
        }
        return apply_filters('wc_tax_enabled', get_option('woocommerce_calc_taxes') === 'yes');
    }

    /**
     * TODO: Why do we do this filtering stuff?
     *
     * @return bool
     */
    static function fyndiq_wc_prices_include_tax()
    {
        if (function_exists('wc_tax_enabled')) {
            return wc_prices_include_tax();
        }
        return self::fyndiq_wc_tax_enabled() && get_option('woocommerce_prices_include_tax') === 'yes';
    }

    /**
     * getAllTerms - gets an array of product attributes TODO: explain further
     *
     * @return array - an array of product attributes
     */
    public static function getAllTerms()
    {
        $attributes = array('' => '');
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if ($attribute_taxonomies) {
            foreach ($attribute_taxonomies as $tax) {
                $attributes[$tax->attribute_name] = $tax->attribute_label;
            }
        }

        // Get products attributes
        // This can be set per product and some product can have no attributes at all
        global $wpdb;
        $results = $wpdb->get_results('SELECT * FROM wp_postmeta WHERE meta_key = "_product_attributes" AND meta_value != "a:0:{}"', OBJECT);
        foreach ($results as $meta) {
            $data = unserialize($meta->meta_value);
            foreach ($data as $key => $attribute) {
                $attributes[$key] = $attribute['name'];
            }
        }
        return $attributes;
    }
}

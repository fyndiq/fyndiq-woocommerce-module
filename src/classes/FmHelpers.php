<?php

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class FmHelpers
{

    const COMMIT = 'XXXXXX';
    const PLATFORM = 'WooCommerce';

    public static function apiConnectionExists()
    {
        return !is_null(get_option('wcfyndiq_username')) && !is_null(get_option('wcfyndiq_apitoken'));
    }

    public static function allSettingsExist()
    {
        return FmConfig::getBool('language') && FmConfig::getBool('currency');
    }

    /**
     * Wrappers around FyndiqAPI
     * uses stored connection credentials for authentication
     *
     * @param $method
     * @param $path
     * @param array $data
     * @return mixed
     */
    public static function callApi($method, $path, $data = array())
    {
        $username = get_option('wcfyndiq_username');
        $apiToken = get_option('wcfyndiq_apitoken');

        $userAgent = self::get_user_agent();

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

    static function get_woocommerce_version()
    {
        // If get_plugins() isn't available, require it
        if (!function_exists('get_plugins')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        // Create the plugins folder and file variables
        $plugin_folder = get_plugins('/' . 'woocommerce');
        $plugin_file = 'woocommerce.php';

        // If the plugin version number is set, return it
        if (isset($plugin_folder[$plugin_file]['Version'])) {
            return $plugin_folder[$plugin_file]['Version'];

        } else {
            // Otherwise return null
            return null;
        }
    }

    static function get_version_label()
    {
        return FyndiqUtils::getVersionLabel(self::get_plugin_version(), self::COMMIT);
    }

    static function get_plugin_version()
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

    public static function get_user_agent()
    {
        return FyndiqUtils::getUserAgentString(
            self::PLATFORM,
            self::get_woocommerce_version(),
            'module',
            self::get_plugin_version(),
            self::COMMIT
        );
    }


    static function fyndiq_wc_tax_enabled()
    {
        if (function_exists('wc_tax_enabled')) {
            return wc_tax_enabled();
        }
        return apply_filters('wc_tax_enabled', get_option('woocommerce_calc_taxes') === 'yes');
    }

    static function fyndiq_wc_prices_include_tax()
    {
        if (function_exists('wc_tax_enabled')) {
            return wc_prices_include_tax();
        }
        return self::fyndiq_wc_tax_enabled() && get_option('woocommerce_prices_include_tax') === 'yes';
    }

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

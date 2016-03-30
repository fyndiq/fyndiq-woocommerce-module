<?php

class FmWoo
{
    private $defaultTextDomain = '';

    public function __construct($textDomain)
    {
        $this->defaultTextDomain = $textDomain;
    }

    public function addAction($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        return add_action($tag, $function_to_add, $priority, $accepted_args);
    }

    public function addFilter($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        return add_filter($tag, $function_to_add, $priority, $accepted_args);
    }

    public function pluginBasename($file)
    {
        return plugin_basename($file);
    }

    public function setDoingAJAX($value = true)
    {
        define('DOING_AJAX', $value);
    }

    public function addSubmenuPage($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '')
    {
        return add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
    }

    public function escURL($url, $protocols = null, $_context = 'display')
    {
        return esc_url($url, $protocols, $_context);
    }

    public function getAdminURL($blog_id, $path = '', $scheme = 'admin')
    {
        return get_admin_url($blog_id, $path, $scheme);
    }

    public function getPostCustom($post_id)
    {
        return get_post_custom($post_id);
    }

    public function addMetaBox($id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null)
    {
        return add_meta_box($id, $title, $callback, $screen = null, $context, $priority, $callback_args);
    }

    public function __($text, $domain = null)
    {
        if (is_null($domain)) {
            $domain = $this->defaultTextDomain;
        }
        return __($text, $domain);
    }

    public function loadPluginTextdomain($domain, $deprecated = false, $plugin_rel_path = false)
    {
        return load_plugin_textdomain($domain, $deprecated, $plugin_rel_path);
    }

    public function wpUploadDir($time = null)
    {
        return wp_upload_dir($time);
    }

    public function wpDie($message = '', $title = '', $args = array())
    {
        return wp_die($message, $title, $args);
    }

    public function getPostMeta($post_id, $key = '', $single = false)
    {
        return get_post_meta($post_id, $key, $single);
    }

    public function updatePostMeta($post_id, $meta_key, $meta_value, $prev_value = '')
    {
        return update_post_meta($post_id, $meta_key, $meta_value, $prev_value);
    }

    public function isAdmin()
    {
        return is_admin();
    }

    public function getOption($option, $default = false)
    {
        return get_option($option, $default);
    }

    /** WooCommerce specific functions */

    public function WC()
    {
        return WC();
    }

    public function getWoocommerceCurrency()
    {
        return get_woocommerce_currency();
    }

    public function woocommerceAdminFields($options)
    {
        return woocommerce_admin_fields($options);
    }
}
<?php
/**
 * This uses the TGM plugin activation library to check for dependent plugins and warn a user if they are not
 * installed.
 */

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

require_once 'include/tgm/class-tgm-plugin-activation.php';

add_action('tgmpa_register', 'fyndiqRegisterRequiredPlugins');

/**
 * Hooked to 'tgmpa_register' - function that loads the dependency library
 *
 * @return bool - always true
 */
function fyndiqRegisterRequiredPlugins()
{
    tgmpa(
        array(
        // This uses the WordPress plugin repository
        array(
            'name'      => 'WooCommerce',
            'slug'      => 'woocommerce',
            'required'  => true,
        )
        ),
        // These are the settings for the library
        array(
            // Unique ID for hashing notices for multiple instances of TGMPA
            'id'           => 'fyndiq',
            // Default absolute path to bundled plugins
            'default_path' => '',
            // Menu slug
            'menu'         => 'fyndiq-tgmpa-install-plugins',
            // Parent menu slug
            'parent_slug'  => 'plugins.php',
            // Capability needed to view plugin install page
            'capability'   => 'manage_options',
            // Show admin notices or not.
            'has_notices'  => true,
            // If false, a user cannot dismiss the nag message.
            'dismissable'  => false,
            // If 'dismissable' is false, this message will be output at top of nag.
            'dismiss_msg'  => sprintf(
                '<div class=\'error\'><p>%s</p></div>',
                __('The Fyndiq WooCommerce integration plugin requires WooCommerce to function.', 'fyndiq')
            ),
            // Automatically activate plugins after installation or not.
            'is_automatic' => false,
            // Message to output right before the plugins table.
            'message'      => sprintf(
                '<div class=\'error\'><p>%s</p></div>',
                __('The Fyndiq WooCommerce integration plugin requires WooCommerce to function.', 'fyndiq')
            ),
        )
    );
    return true;
}

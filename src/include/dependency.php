<?php
/**
 * This uses the TGM plugin activation library to check for dependent plugins and warn a user if they are not
 * installed.
 */

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

require_once('tgm-plugin-activation/class-tgm-plugin-activation.php');
add_action( 'tgmpa_register', 'fyndiq_register_required_plugins' );
function fyndiq_register_required_plugins() {
    tgmpa(array(
        // This uses the WordPress plugin repository
        array(
            'name'      => 'WooCommerce',
            'slug'      => 'woocommerce',
            'required'  => true,
        )
    ),
        // These are the settings for the library
        array(
            'id'           => 'fyndiq',                 // Unique ID for hashing notices for multiple instances of TGMPA.
            'default_path' => '',                      // Default absolute path to bundled plugins.
            'menu'         => 'tgmpa-install-plugins', // Menu slug.
            'parent_slug'  => 'plugins.php',            // Parent menu slug.
            'capability'   => 'manage_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
            'has_notices'  => true,                    // Show admin notices or not.
            'dismissable'  => false,                    // If false, a user cannot dismiss the nag message.
            'dismiss_msg'  => sprintf('<div class=\'error\'><p>%s</p></div>',__('The Fyndiq WooCommerce integration plugin requires WooCommerce to function.')),                      // If 'dismissable' is false, this message will be output at top of nag.
            'is_automatic' => false,                   // Automatically activate plugins after installation or not.
            'message'      => sprintf('<div class=\'error\'><p>%s</p></div>',__('The Fyndiq WooCommerce integration plugin requires WooCommerce to function.')),                      // Message to output right before the plugins table.
        ));
}

# fyndiq-woocommerce-module
This is the official Fyndiq integration plugin for WooCommerce.

## Installation
Installing the plugin is simple, requiring the following steps:

1. Add this plugin directory to `wp-content/plugins`
1. Navigate to `http://yoursitenamehere/wp-admin`
1. Go to `Plugins -> Installed Plugins`
1. Find the plugin called `Fyndiq WooCommerce` and click `Activate`
1. Go to `WooCommerce -> Settings`
1. Click the `Fyndiq` tab
1. Enter your `Username` and `API-token`, then click `Save changes`.

Assuming that the username and API Token were valid, you can now begin to use the plugin.
## Good to know

* The feed url is `http://yoursitenamehere/?fyndiq_feed`
* This plugin integrates natively with WooCommerce, so lacks a separate user interface.

## Development

### Vagrant
To use the vagrant box for development, go to vagrant/ and run:

`vagrant up`

to bootstrap the machine.

Add `192.168.13.102 woocommerce` to your hosts file to access the server. 

### Make commands:

* `build` - builds the module package from source;
* `compatinfo` - checks the code for the lowest compatible PHP version;
* `coverage` - generates test coverage report in `coverage/`;
* `css` - builds the CSS file from SCSS using SASS;
* `php-lint` - checks the files with the PHP internal linter;
* `phpmd` - checks the code with [PHP Mess Detector](http://phpmd.org/);
* `scss-lint` - lint checks the SCSS files using `scss-lint`;
* `sniff` - checks the code for styling issues;
* `sniff-fix` - tries to fix the styling issues
* `test` - runs the PHPUnit tests;
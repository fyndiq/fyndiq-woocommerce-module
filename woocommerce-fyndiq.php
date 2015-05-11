<?php
/**
 * Plugin Name: Fyndiq Woocommerce
 * Plugin URI: http://fyndiq.se
 * Description: Export products and import orders to woocommerce from Fyndiq.
 * Version: 1.0.0
 * Author: Fyndiq AB
 * Author URI: http://fyndiq.se
 * License: MIT
 */

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('WC_Fyndiq')) {


        /**
         * Localisation
         **/
        load_plugin_textdomain('wc_fyndiq', false, dirname(plugin_basename(__FILE__)) . '/');

        class WC_Fyndiq
        {
            public function __construct()
            {
                // called only after woocommerce has finished loading
                add_action('woocommerce_init', array(&$this, 'woocommerce_loaded'));

                // called after all plugins have loaded
                add_action('plugins_loaded', array(&$this, 'plugins_loaded'));

                // called just before the woocommerce template functions are included
                add_action('init', array(&$this, 'include_template_functions'), 20);

                // indicates we are running the admin
                if (is_admin()) {

                }

                // indicates we are being served over ssl
                if (is_ssl()) {
                    // ...
                }

                // take care of anything else that needs to be done immediately upon plugin instantiation, here in the constructor
            }

            /**
             * Take care of anything that needs woocommerce to be loaded.
             * For instance, if you need access to the $woocommerce global
             */
            public function woocommerce_loaded()
            {
                //Settings
                add_filter('woocommerce_get_sections_products', array(&$this, 'fyndiq_settings_action'));
                add_filter('woocommerce_get_settings_products', array(&$this, 'fyndiq_all_settings'), 10, 2);

                //products
                add_action(
                    'woocommerce_product_options_general_product_data',
                    array(&$this, 'fyndiq_add_product_field')
                );
                add_action('woocommerce_process_product_meta', array(&$this, 'fyndiq_product_save'));

                //product list
                add_filter('manage_edit-product_columns', array(&$this, 'fyndiq_product_add_column'));
                add_action('manage_product_posts_custom_column', array(&$this, 'fyndiq_product_column_export'), 5, 2);
                add_filter("manage_edit-product_sortable_columns", array(&$this, 'fyndiq_product_column_sort'));
                add_action('pre_get_posts', array(&$this, 'fyndiq_product_column_sort_by'));

                //bulk action
                add_action('admin_footer-edit.php', array(&$this, 'fyndiq_product_add_bulk_action'));
                //add_action('load-edit.php', array( &$this, 'fyndiq_product_export_bulk_action'));
            }

            function fyndiq_settings_action($sections)
            {

                $sections['wcfyndiq'] = __('Fyndiq', 'text-domain');

                return $sections;

            }

            function fyndiq_all_settings($settings, $current_section)
            {

                /**
                 * Check the current section is what we want
                 **/

                if ($current_section == 'wcfyndiq') {

                    $settings_slider = array();

                    // Add Title to the Settings
                    $settings_slider[] = array(
                        'name' => __('Fyndiq Settings', 'text-domain'),
                        'type' => 'title',
                        'desc' => __('The following options are used to configure Fyndiq', 'text-domain'),
                        'id' => 'wcfyndiq'
                    );

                    // Add second text field option
                    $settings_slider[] = array(

                        'name' => __('Username', 'text-domain'),
                        'desc_tip' => __('This is the username you use for login on Fyndiq Merchant', 'text-domain'),
                        'id' => 'wcfyndiq_username',
                        'type' => 'text',
                        'desc' => __('Must be your username', 'text-domain'),

                    );

                    // Add second text field option
                    $settings_slider[] = array(

                        'name' => __('API-token', 'text-domain'),
                        'desc_tip' => __('This is the API V2 Token on Fyndiq', 'text-domain'),
                        'id' => 'wcfyndiq_apitoken',
                        'type' => 'text',
                        'desc' => __('Must be API v2 token', 'text-domain'),

                    );

                    $settings_slider[] = array('type' => 'sectionend', 'id' => 'wcfyndiq');

                    return $settings_slider;

                    /**
                     * If not, return the standard settings
                     **/

                } else {

                    return $settings;

                }
            }


            function fyndiq_add_product_field()
            {
                $product = get_product( get_the_ID() );

                if(!$product->is_downloadable()) {

                    echo '<div class="options_group">';

                    woocommerce_wp_checkbox(
                        array(
                            'id' => '_fyndiq_export',
                            'wrapper_class' => 'show_if_simple',
                            'label' => __('Export to Fyndiq', 'woocommerce'),
                            'description' => __('mark this as true if you want to export to Fyndiq', 'woocommerce')
                        )
                    );

                    echo '</div>';
                }
                else {
                    echo '<div class="options_group">Can\'t export this product to Fyndiq</div>';
                }
            }

            function fyndiq_product_save($post_id)
            {
                $woocommerce_checkbox = isset($_POST['_fyndiq_export']) ? 'exported' : 'not exported';
                update_post_meta($post_id, '_fyndiq_export', $woocommerce_checkbox);
            }

            function fyndiq_product_add_column($defaults)
            {
                $defaults['fyndiq_export'] = 'Fyndiq Exported';

                return $defaults;
            }

            function fyndiq_product_column_export($column, $postid)
            {
                $product = new WC_Product($postid);
                if ($column == 'fyndiq_export') {
                    if (!$product->is_downloadable()) {
                        $exported = get_post_meta($postid, '_fyndiq_export', true);
                        if ($exported != "") {
                            echo $exported;
                        } else {
                            update_post_meta($postid, '_fyndiq_export', 'not exported');
                            echo "not exported";
                        }
                    }
                    else {
                        echo "Can't be exported";
                    }
                }
            }

            function fyndiq_product_column_sort()
            {
                return array(
                    'fyndiq_export' => 'fyndiq_export'
                );
            }

            function fyndiq_product_column_sort_by($query)
            {
                if (!is_admin()) {
                    return;
                }
                $orderby = $query->get('orderby');
                if ('fyndiq_export' == $orderby) {
                    $query->set('meta_key', '_fyndiq_export');
                    $query->set('orderby', 'meta_value_boolean');
                }
            }

            function fyndiq_product_add_bulk_action()
            {
                global $post_type;

                if ($post_type == 'product') {
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            jQuery('<option>').val('fyndiq_export').text('<?php _e('Export to Fyndiq')?>').appendTo("select[name='action']");
                            jQuery('<option>').val('fyndiq_export').text('<?php _e('Export to Fyndiq')?>').appendTo("select[name='action2']");
                        });
                    </script>
                <?php
                }
            }

            function fyndiq_product_export_bulk_action()
            {

                // ...

                // 1. get the action
                $wp_list_table = _get_list_table('WP_Posts_List_Table');
                $action = $wp_list_table->current_action();

                // ...

                // 2. security check
                check_admin_referer('bulk-posts');

                // ...

                switch ($action) {
                    // 3. Perform the action
                    case 'export':
                        // if we set up user permissions/capabilities, the code might look like:
                        //if ( !current_user_can($post_type_object->cap->export_post, $post_id) )
                        //  pp_die( __('You are not allowed to export this post.') );

                        $exported = 0;

                        foreach ($post_ids as $post_id) {
                            if (!$this->perform_export($post_id)) {
                                wp_die(__('Error exporting post.'));
                            }
                            $exported++;
                        }

                        // build the redirect url
                        $sendback = add_query_arg(
                            array('exported' => $exported, 'ids' => join(',', $post_ids)),
                            $sendback
                        );

                        break;
                    default:
                        return;
                }

                // ...

                // 4. Redirect client
                wp_redirect($sendback);

                exit();
            }

            public function perform_export($post_id)
            {
                if (!update_post_meta($post_id, '_fyndiq_export', 'exported')) {
                    add_post_meta($post_id, '_fyndiq_export', 'exported', true);
                };
            }

            /**
             * Take care of anything that needs all plugins to be loaded
             */
            public function plugins_loaded()
            {
                include_once('fyndiqHelper.php');
            }

            /**
             * Override any of the template functions from woocommerce/woocommerce-template.php
             * with our own template functions file
             */
            public function include_template_functions()
            {

            }
        }

        // finally instantiate our plugin class and add it to the set of globals
        $GLOBALS['wc_fyndiq'] = new WC_Fyndiq();
    }
}

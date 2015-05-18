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
                add_action('load-edit.php', array( &$this, 'fyndiq_product_export_bulk_action'));
                if(isset($_GET['fyndiq_feed'])) {
                    $this->generate_feed();
                }

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
                    $value = (get_post_meta(get_the_ID(), '_fyndiq_export', true) == "exported") ? 1 : 0;

                    woocommerce_form_field( '_fyndiq_export', array(
                            'type' => 'checkbox',
                            'class' => array('input-checkbox'),
                            'label' => __('Export to Fyndiq', 'woocommerce'),
                            'description' => __('mark this as true if you want to export to Fyndiq', 'woocommerce'),
                            'required' => false,
                        ), $value );

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
                            jQuery('<option>').val('fyndiq_no_export').text('<?php _e('Remove from Fyndiq')?>').appendTo("select[name='action']");
                            jQuery('<option>').val('fyndiq_no_export').text('<?php _e('Remove from Fyndiq')?>').appendTo("select[name='action2']");
                        });
                    </script>
                <?php
                }
            }

            function fyndiq_product_export_bulk_action()
            {
                $wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
                $action = $wp_list_table->current_action();

                switch ( $action ) {
                    case 'fyndiq_export':
                        $report_action = 'exported';
                        $exporting = true;
                        break;
                    case 'fyndiq_no_export':
                        $report_action = 'removed';
                        $exporting = false;
                        break;
                    default:
                        return;
                }

                $changed = 0;
                $post_ids = array();
                if($exporting) {
                    foreach( $_REQUEST['post'] as $post_id ) {
                        $product = new WC_Product($post_id);
                        if(!$product->is_downloadable()) {
                            $this->perform_export($post_id);
                            $post_ids[] = $post_id;
                            $changed++;
                        }
                    }
                }
                else {
                    foreach( $_REQUEST['post'] as $post_id ) {
                        $product = new WC_Product($post_id);
                        if(!$product->is_downloadable()) {
                            $this->perform_no_export($post_id);
                            $post_ids[] = $post_id;
                            $changed++;
                        }
                    }
                }
                $sendback = add_query_arg( array( 'post_type' => 'product', $report_action => $changed, 'ids' => join( ',', $post_ids ) ), '' );
                wp_redirect( $sendback );
                exit();
            }

            public function perform_export($post_id)
            {
                if (!update_post_meta($post_id, '_fyndiq_export', 'exported')) {
                    add_post_meta($post_id, '_fyndiq_export', 'exported', true);
                };
            }
            public function perform_no_export($post_id)
            {
                if (!update_post_meta($post_id, '_fyndiq_export', 'not exported')) {
                    add_post_meta($post_id, '_fyndiq_export', 'not exported', true);
                };
            }

            function fyndiq_export_products_button($wp_admin){
                $this->fyndiq_admin_bar_render($wp_admin,'Fyndiq'); // Parent item
                $this->fyndiq_admin_bar_render($wp_admin,'Export Products', 'http://', 'Fyndiq');
                $this->fyndiq_admin_bar_render($wp_admin,'Import Orders', 'http://', 'Fyndiq');
            }

            /**
             * Add's menu parent or submenu item.
             * @param string $name the label of the menu item
             * @param string $href the link to the item (settings page or ext site)
             * @param string $parent Parent label (if creating a submenu item)
             *
             * @return void
             * */
            function fyndiq_admin_bar_render($wp_admin_bar, $name, $href = '', $parent = '', $custom_meta = array() ) {

                // Generate ID based on the current filename and the name supplied.
                $id = sanitize_key( $name );

                // Generate the ID of the parent.
                $parent = sanitize_key( $parent );

                // links from the current host will open in the current window

                $wp_admin_bar->add_node( array(
                        'parent' => $parent,
                        'id' => $id,
                        'title' => $name,
                        'href' => $href,
                    ) );
            }

            /**
             * Take care of anything that needs all plugins to be loaded
             */
            public function plugins_loaded()
            {
                include_once('fyndiqHelper.php');
                require_once('shared/src/init.php');
            }

            /**
             * Override any of the template functions from woocommerce/woocommerce-template.php
             * with our own template functions file
             */
            public function include_template_functions()
            {
            }

            public function generate_feed() {

                $paged = get_query_var('paged');
                $args = array(
                    'numberposts'       => -1,
                    'orderby'           => 'post_date',
                    'order'             => 'DESC',
                    'post_type'         => 'product',
                    'post_status'       => 'publish',
                    'suppress_filters'  => true,
                    'meta_key'          => '_fyndiq_export',
                    'meta_value'        => 'exported'
                );
                $posts_array = get_posts( $args );
                if (get_option('wcfyndiq_username') != '' && get_option('wcfyndiq_apitoken') != '') {
                $filePath = plugin_dir_path( __FILE__ ) . 'files/feed.csv';
                $fileExistsAndFresh = file_exists($filePath) && filemtime($filePath) > strtotime('-1 hour');
                if (!$fileExistsAndFresh) {
                    $file = fopen($filePath, 'w+');
                    $feedWriter = new FyndiqCSVFeedWriter($file);
                    foreach ($posts_array as $product) {
                        $product = new WC_Product($product->ID);
                        $feedWriter->addProduct($this->getProduct($product));
                    }
                    $feedWriter->write();
                }
                $result = file_get_contents($filePath);
                die($result);
                }
                else {
                    die();
                }
            }

            private function getProduct($product)
            {
                //Initialize models here so it saves memory.
                $feedProduct['product-id'] = $product->id;
                $feedProduct['product-title'] = $product->post->post_title;
                $feedProduct['product-description'] = $product->post->post_content;

                $discount = 10;
                $product_price = get_post_meta( $product->id, '_regular_price');
                $price = FyndiqUtils::getFyndiqPrice($product_price[0], $discount);
                $_tax = new WC_Tax();//looking for appropriate vat for specific product
                $rates = $_tax->get_rates( $product->get_tax_class() );


                $feedProduct['product-price'] = FyndiqUtils::formatPrice($price);
                $feedProduct['product-vat-percent'] = !empty($rates['rate']) ? $rates['rate'] : 0;
                $feedProduct['product-oldprice'] = FyndiqUtils::formatPrice($product_price);
                $feedProduct['product-market'] = 'SE';
                $feedProduct['product-currency'] = 'SEK';
                $feedProduct['product-brand'] = 'UNKNOWN';

                $terms = get_the_terms( $product->id, 'product_cat' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    foreach ($cats as $term) {
                        var_dump($term);
                        $feedProduct['product-category-id'] = $term->term_id;
                        $feedProduct['product-category-name'] = $term->term_name;
                        break;
                    }
                }

                $attachment_ids = $product->get_gallery_attachment_ids();
                $imageId=1;
                foreach( $attachment_ids as $attachment_id )
                {
                    $image_link = wp_get_attachment_url( $attachment_id );
                    $feedProduct['product-image-' . $imageId . '-url'] = $image_link;
                    $feedProduct['product-image-' . $imageId . '-identifier'] = substr(md5($image_link), 0, 10);
                    $imageId++;
                }

                $feedProduct['article-quantity'] = intval(get_post_meta( $product->id, '_stock')[0]);

                $feedProduct['article-location'] = 'unknown';
                $feedProduct['article-sku'] = get_post_meta( $product->id, '_sku')[0];
                $feedProduct['article-name'] = $product->post->post_title;

                return $feedProduct;
            }
        }

        // finally instantiate our plugin class and add it to the set of globals
        $GLOBALS['wc_fyndiq'] = new WC_Fyndiq();
    }
}

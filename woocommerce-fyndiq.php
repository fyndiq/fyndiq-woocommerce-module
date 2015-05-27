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
                //javascript
                add_action('admin_head', array(&$this, 'get_url'));


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
                add_action('load-edit.php', array( &$this, 'fyndiq_order_delivery_note_bulk_action'));

                //add_action('post_submitbox_misc_actions', array( &$this, 'fyndiq_order_edit_action'));
                add_action( 'add_meta_boxes', array( &$this, 'fyndiq_order_meta_boxes') );

                //functions
                if(isset($_GET['fyndiq_feed'])) {
                    $this->generate_feed();
                }
                if(isset($_GET['fyndiq_orders'])) {
                    $this->generate_orders();
                }

            }


            function fyndiq_order_meta_boxes()
            {
                global $post;
                $post_id = $post->ID;
                $meta = get_post_custom( $post_id );
                if(isset($meta['fyndiq_delivery_note']) && isset($meta['fyndiq_delivery_note'][0]) && $meta['fyndiq_delivery_note'][0] != "") {
                    add_meta_box(
                        'woocommerce-order-fyndiq-delivery-note',
                        __( 'Fyndiq' ),
                        array( &$this, 'order_meta_box_delivery_note'),
                        'shop_order',
                        'side',
                        'default'
                    );
                }
            }
            function order_meta_box_delivery_note()
            {
                global $post;
                $post_id = $post->ID;
                $meta = get_post_custom( $post_id );

                echo '<a href="'.$meta['fyndiq_delivery_note'][0].'" class="button button-primary">Get Fyndiq Delivery Note</a>';
            }

            function get_url()
            {
                ?>
                <script type="text/javascript">
                    var wordpressurl = '<?php echo get_site_url(); ?>' ;
                </script>
                <script src="<?php echo plugins_url( '/stylesheet/order-import.js', __FILE__ ); ?>" type="text/javascript"></script>
                <?
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
                else if($post_type == 'shop_order') {
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            jQuery('<option>').val('fyndiq_delivery').text('<?php _e('Get Fyndiq Delivery Note')?>').appendTo("select[name='action']");
                            jQuery('<option>').val('fyndiq_delivery').text('<?php _e('Get Fyndiq Delivery Note')?>').appendTo("select[name='action2']");
                            jQuery(jQuery(".wrap h2")[0]).append("<a href='#' id='fyndiq-order-import' class='add-new-h2'>Import From Fyndiq</a>");
                        });
                    </script>
                <?php
                }
            }

            function fyndiq_product_export_bulk_action()
            {
                $action = $this->getAction( 'WP_Posts_List_Table' );

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
                    foreach( $this->getRequestPost() as $post_id ) {
                        $product = new WC_Product($post_id);
                        if(!$product->is_downloadable()) {
                            $this->perform_export($post_id);
                            $post_ids[] = $post_id;
                            $changed++;
                        }
                    }
                }
                else {
                    foreach( $this->getRequestPost() as $post_id ) {
                        $product = new WC_Product($post_id);
                        if(!$product->is_downloadable()) {
                            $this->perform_no_export($post_id);
                            $post_ids[] = $post_id;
                            $changed++;
                        }
                    }
                }
                return $this->bulkRedirect($report_action, $changed, $post_ids);
            }

            function fyndiq_order_delivery_note_bulk_action()
            {
                $wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
                $action = $wp_list_table->current_action();

                switch ( $action ) {
                    case 'fyndiq_delivery':
                        break;
                    default:
                        return;
                }

                $orders = array(
                    'orders' => array()
                );
                if (!isset($_REQUEST['post'])) {
                    throw new Exception('Pick at least one order');
                }
                foreach ($_REQUEST['post'] as $order) {
                    $meta = get_post_custom( $order );
                    if(isset($meta['fyndiq_id']) && isset($meta['fyndiq_id'][0]) && $meta['fyndiq_id'][0] != "")
                    $orders['orders'][] = array('order' => intval($meta['fyndiq_id'][0]));
                }

                $ret = FmHelpers::callApi('POST', 'delivery_notes/', $orders, true);

                if ($ret['status'] == 200) {
                    $fileName = 'delivery_notes-' . implode('-', $_REQUEST['post']) . '.pdf';

                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $fileName . '"');
                    header('Content-Transfer-Encoding: binary');
                    header('Content-Length: ' . strlen($ret['data']));
                    header('Expires: 0');
                    $handler = fopen('php://temp', 'wb+');
                    // Saving data to file
                    fputs($handler, $ret['data']);
                    rewind($handler);
                    fpassthru($handler);
                    fclose($handler);
                }
                else {
                    $sendback = add_query_arg( array( 'post_type' => 'shop_order', $report_action => $changed, 'ids' => join( ',', $post_ids ) ), '' );
                    wp_redirect($sendback);
                }
                exit();
            }

            private function perform_export($post_id)
            {
                if (!update_post_meta($post_id, '_fyndiq_export', 'exported')) {
                    add_post_meta($post_id, '_fyndiq_export', 'exported', true);
                };
            }
            private function perform_no_export($post_id)
            {
                if (!update_post_meta($post_id, '_fyndiq_export', 'not exported')) {
                    add_post_meta($post_id, '_fyndiq_export', 'not exported', true);
                };
            }

            /**
             * Take care of anything that needs all plugins to be loaded
             */
            public function plugins_loaded()
            {
                include_once('fyndiqHelper.php');
                require_once('shared/src/init.php');
                require_once('models/order.php');
                require_once('models/orderFetch.php');
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
                return $this->returnAndDie($result);
                }
                else {
                    return $this->returnAndDie(false);
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

            public function generate_orders() {
                define('DOING_AJAX', true);
                $orderFetch = new FmOrderFetch(false);
                $return = $orderFetch->getAll();
                echo json_encode($return);
                wp_die();
            }

            function getAction($table) {
                $wp_list_table = _get_list_table( $table );
                return $wp_list_table->current_action();
            }

            function getRequestPost() {
                return $_REQUEST['post'];
            }

            function returnAndDie($return) {
                die($return);
            }

            function bulkRedirect($report_action, $changed, $post_ids) {
                $sendback = add_query_arg( array( 'post_type' => 'product', $report_action => $changed, 'ids' => join( ',', $post_ids ) ), '' );
                wp_redirect( $sendback );
                exit();
            }
        }

        // finally instantiate our plugin class and add it to the set of globals
        $GLOBALS['wc_fyndiq'] = new WC_Fyndiq();
    }
}

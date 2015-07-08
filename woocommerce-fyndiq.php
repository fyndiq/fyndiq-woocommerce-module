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
        load_plugin_textdomain('fyndiq', false, dirname(plugin_basename(__FILE__)) . '/translations/');

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
                add_action( 'woocommerce_update_options_products', array(&$this, 'update_settings') );

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
                add_action('admin_notices', array(&$this, 'fyndiq_bulk_notices'));

                //order list
                add_filter('manage_edit-shop_order_columns', array(&$this, 'fyndiq_order_add_column'));
                add_action('manage_shop_order_posts_custom_column', array(&$this, 'fyndiq_order_column'), 5, 2);
                add_filter("manage_edit-shop_order_sortable_columns", array(&$this, 'fyndiq_order_column_sort'));


                //bulk action
                add_action('admin_footer-edit.php', array(&$this, 'fyndiq_product_add_bulk_action'));
                add_action('load-edit.php', array( &$this, 'fyndiq_product_export_bulk_action'));
                add_action('load-edit.php', array( &$this, 'fyndiq_order_delivery_note_bulk_action'));

                //add_action('post_submitbox_misc_actions', array( &$this, 'fyndiq_order_edit_action'));
                add_action( 'add_meta_boxes', array( &$this, 'fyndiq_order_meta_boxes') );

                //notice for currency check
                add_action('admin_notices', array( &$this, 'my_admin_notice'));

                //functions
                if(isset($_GET['fyndiq_feed'])) {
                    $this->generate_feed();
                }
                if(isset($_GET['fyndiq_orders'])) {
                    $this->generate_orders();
                }
                if(isset($_GET['fyndiq_notification'])) {
                    $this->notification_handle();
                    die();
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
                <?php
            }

            function fyndiq_settings_action($sections)
            {

                $sections['wcfyndiq'] = __('Fyndiq', 'fyndiq');

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
                        'name' => __('Fyndiq Settings', 'fyndiq'),
                        'type' => 'title',
                        'desc' => __('The following options are used to configure Fyndiq', 'fyndiq'),
                        'id' => 'wcfyndiq'
                    );

                    // Add second text field option
                    $settings_slider[] = array(

                        'name' => __('Username', 'fyndiq'),
                        'desc_tip' => __('This is the username you use for login on Fyndiq Merchant', 'fyndiq'),
                        'id' => 'wcfyndiq_username',
                        'type' => 'text',
                        'desc' => __('Must be your username', 'fyndiq'),

                    );

                    // Add second text field option
                    $settings_slider[] = array(

                        'name' => __('API-token', 'fyndiq'),
                        'desc_tip' => __('This is the API V2 Token on Fyndiq', 'fyndiq'),
                        'id' => 'wcfyndiq_apitoken',
                        'type' => 'text',
                        'desc' => __('Must be API v2 token', 'fyndiq'),

                    );

                    //Price Percentage
                    $settings_slider[] = array(

                        'name' => __('Global Price Percentage', 'fyndiq'),
                        'desc_tip' => __('The percentage that will be removed from the price when sending to fyndiq.', 'fyndiq'),
                        'id' => 'wcfyndiq_price_percentage',
                        'type' => 'text',
                        'default'  => '10',
                        'desc' => __('Can be 0 if the price should be the same as in your shop.', 'fyndiq'),

                    );

                    // Add order status setting
                    $settings_slider[] = array(

                        'name' => __('Order Status', 'fyndiq'),
                        'desc_tip' => __('When a order is imported from fyndiq, this status will be applied.', 'fyndiq'),
                        'id' => 'wcfyndiq_create_order_status',
                        'type' => 'select',
                        'options' => array('completed' => 'completed', 'processing' => 'processing', 'pending' => 'pending', 'on-hold' => 'on-hold'),
                        'desc' => __('This must be picked accurate', 'fyndiq'),

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

            function update_settings() {
                woocommerce_update_options($this->fyndiq_all_settings(array(), "wcfyndiq"));
                try {
                $this->updateUrls();
                }
                catch (Exception $e) {
                    if ($e->getMessage() == "Unauthorized") {
                        //echo "Wrong api-token or username to Fyndiq.";
                        ?><div class="error">
                            <p><?php _e( 'Fyndiq credentials was wrong, try again.', 'fyndiq_username' ); ?></p>
                        </div><?php
                    }
                    //die();
                }
            }

            function updateUrls() {
                //Generate pingtoken
                $pingToken = md5(uniqid());
                update_option("wcfyndiq_ping_token", $pingToken);

                $data = array(
                    FyndiqUtils::NAME_PRODUCT_FEED_URL => get_site_url().'/?fyndiq_feed',
                    FyndiqUtils::NAME_NOTIFICATION_URL => get_site_url().'/?fyndiq_notification',
                    FyndiqUtils::NAME_PING_URL => get_site_url().'/?fyndiq_notification&event=ping&pingToken='.$pingToken
                );
                return FmHelpers::callApi('PATCH', 'settings/', $data);
            }


            function fyndiq_add_product_field()
            {
                $product = get_product( $this->getProductId() );

                if(!$product->is_downloadable()) {

                    echo '<div class="options_group">';
                    $value = (get_post_meta(get_the_ID(), '_fyndiq_export', true) == "exported") ? 1 : 0;

                    woocommerce_form_field( '_fyndiq_export', array(
                            'type' => 'checkbox',
                            'class' => array('input-checkbox'),
                            'label' => __('Export to Fyndiq', 'fyndiq'),
                            'description' => __('mark this as true if you want to export to Fyndiq', 'fyndiq'),
                            'required' => false,
                        ), $value );
                    $discount = $this->getDiscount();
                    $product_price = get_post_meta( $product->id, '_regular_price');
                    $price = FyndiqUtils::getFyndiqPrice($product_price[0], $discount);

                    echo '<p>' . __('Fyndiq Price with set Discount percentage: ', 'fyndiq').$price.' '.get_woocommerce_currency().'</p></div>';
                }
                else {
                    echo '<div class="options_group"><p>' . __('Can\'t export this product to Fyndiq', 'fyndiq') . '</p></div>';
                }
            }

            function fyndiq_product_save($post_id)
            {
                $woocommerce_checkbox = $this->getExportState();
                update_post_meta($post_id, '_fyndiq_export', $woocommerce_checkbox);
            }

            function fyndiq_product_add_column($defaults)
            {
                $defaults['fyndiq_export'] = __('Fyndiq Exported');

                return $defaults;
            }

            function fyndiq_product_column_export($column, $postid)
            {
                $product = new WC_Product($postid);
                if ($column == 'fyndiq_export') {
                    if (!$product->is_downloadable()) {
                        $exported = get_post_meta($postid, '_fyndiq_export', true);
                        if ($exported != "") {
                            _e($exported);
                        } else {
                            update_post_meta($postid, '_fyndiq_export', 'not exported');
                            _e("Not exported");
                        }
                    }
                    else {
                        _e("Can't be exported");
                    }
                }
            }


            function my_admin_notice()
            {
                if($this->checkCurrency()) {
                    echo '<div class="error">
                   <p><strong>' . __("Wrong Currency") . '</strong>: ' . __("Fyndiq only works in EUR and SEK. change to correct currency. Current Currency:") . ' '.get_woocommerce_currency().'</p>
                </div>';
                }
                if($this->checkCountry()) {
                    echo '<div class="error">
                   <p><strong>' . __("Wrong Country") . '</strong>: ' . __("Fyndiq only works in Sweden and Germany. change to correct country. Current Country:") . ' '.WC()->countries->get_base_country().'</p>
                </div>';
                }
                if($this->checkCredentials()) {
                    echo '<div class="error">
                   <p><strong>' . __("Fyndiq Credentials") . '</strong>: ' . __("You need to set Fyndiq Credentials to make it work. Do it in Woocommerce Settings > Products > Fyndiq.") . '</p>
                </div>';
                }
            }



            function fyndiq_order_column_sort()
            {
                return array(
                    'fyndiq_order' => 'fyndiq_order'
                );
            }

            function fyndiq_order_column_sort_by($query)
            {
                if (!is_admin()) {
                    return;
                }
                $orderby = $query->get('orderby');
                if ('fyndiq_order' == $orderby) {
                    $query->set('meta_key', 'fyndiq_id');
                    $query->set('orderby', 'meta_value_integer');
                }
            }



            function fyndiq_order_add_column($defaults)
            {
                $defaults['fyndiq_order'] = 'Fyndiq Order';

                return $defaults;
            }

            function fyndiq_order_column($column, $postid)
            {
                $product = new WC_Order($postid);
                if ($column == 'fyndiq_order') {
                        $fyndiq_order = get_post_meta($postid, 'fyndiq_id', true);
                        if ($fyndiq_order != "") {
                            echo $fyndiq_order;
                        } else {
                            update_post_meta($postid, 'fyndiq_id', '-');
                            echo "-";
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
                            jQuery(jQuery(".wrap h2")[0]).append("<a href='#' id='fyndiq-order-import' class='add-new-h2'><?php _e("Import From Fyndiq"); ?></a>");
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
                        $report_action = 'fyndiq_exported';
                        $exporting = true;
                        break;
                    case 'fyndiq_no_export':
                        $report_action = 'fyndiq_removed';
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

            function fyndiq_bulk_notices() {
                global $post_type, $pagenow;

                if($pagenow == 'edit.php' && isset($_REQUEST['fyndiq_removed']) && (int) $_REQUEST['fyndiq_removed']) {
                    $message = sprintf( _n( 'Products removed from Fyndiq.', '%s products removed from Fyndiq.', $_REQUEST['fyndiq_removed'] ), number_format_i18n( $_REQUEST['fyndiq_removed'] ) );
                    echo "<div class=\"updated\"><p>{$message}</p></div>";
                }
                if($pagenow == 'edit.php' && isset($_REQUEST['fyndiq_exported']) && (int) $_REQUEST['fyndiq_exported']) {
                    $message = sprintf( _n( 'Products exported to Fyndiq.', '%s products exported to Fyndiq.', $_REQUEST['fyndiq_exported'] ), number_format_i18n( $_REQUEST['fyndiq_exported'] ) );
                    echo "<div class=\"updated\"><p>{$message}</p></div>";
                }
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
                require_once('include/shared/src/init.php');
                require_once('models/order.php');
                require_once('models/orderFetch.php');
            }

            public function generate_feed() {

                $filePath = plugin_dir_path( __FILE__ ) . 'files/feed.csv';
                $return = $this->feed_write($filePath);
                if($return) {
                    $result = file_get_contents($filePath);
                    return $this->returnAndDie($result);
                }
                else {
                    return $this->returnAndDie("");
                }
            }

            private function feed_write($filePath) {
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

                    $fileExistsAndFresh = file_exists($filePath) && filemtime($filePath) > strtotime('-1 hour');
                    if (!$fileExistsAndFresh) {
                        $file = fopen($filePath, 'w+');
                        $feedWriter = new FyndiqCSVFeedWriter($file);
                        foreach ($posts_array as $product) {
                            $product = new WC_Product_Variable($product->ID);
                            $feedWriter->addProduct($this->getProduct($product));
                            $variations = $product->get_available_variations();
                            foreach($variations as $variation) {
                                $feedWriter->addProduct($this->getVariation($product, $variation));
                            }
                        }
                        $feedWriter->write();
                    }
                    return true;
                }
                return false;
            }

            private function getProduct($product)
            {
                //Initialize models here so it saves memory.
                $feedProduct['product-id'] = $product->id;
                $feedProduct['product-title'] = $product->post->post_title;
                $feedProduct['product-description'] = $product->post->post_content;

                $discount = $this->getDiscount();
                $product_price = get_post_meta( $product->id, '_regular_price');
                $price = FyndiqUtils::getFyndiqPrice($product_price[0], $discount);
                $_tax = new WC_Tax();//looking for appropriate vat for specific product
                $rates = $_tax->get_rates( $product->get_tax_class() );


                $feedProduct['product-price'] = FyndiqUtils::formatPrice($price);
                $feedProduct['product-vat-percent'] = !empty($rates['rate']) ? $rates['rate'] : 0;
                $feedProduct['product-oldprice'] = FyndiqUtils::formatPrice($product_price);
                $feedProduct['product-market'] = WC()->countries->get_base_country();
                $feedProduct['product-currency'] = get_woocommerce_currency();
                $feedProduct['product-brand'] = 'UNKNOWN';

                $terms = get_the_terms( $product->id, 'product_cat' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    foreach ($terms as $term) {
                        $feedProduct['product-category-id'] = $term->term_id;
                        $feedProduct['product-category-name'] = $term->name;
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

                $variations = $product->get_available_variations();
                foreach($variations as $variation) {
                    if($variation['image_src'] != "") {
                        $feedProduct['product-image-' . $imageId . '-url'] = $variation['image_src'];
                        $feedProduct['product-image-' . $imageId . '-identifier'] = substr(md5($variation['image_src']), 0, 10);
                        $imageId++;
                    }
                }

                $stock = get_post_meta($product->id, '_stock');
                $feedProduct['article-quantity'] = intval($stock[0]);

                $feedProduct['article-location'] = 'unknown';
                $sku = get_post_meta($product->id, '_sku');
                $feedProduct['article-sku'] = $sku[0];
                $feedProduct['article-name'] = $product->post->post_title;

                return $feedProduct;
            }


            private function getVariation($product, $variation)
            {
                if($variation['is_purchasable'] && $variation['is_in_stock'] && !$variation['is_downloadable'] && !$variation['is_virtual']) {
                    //Initialize models here so it saves memory.
                    $feedProduct['product-id'] = $product->id;
                    $feedProduct['product-title'] = $product->post->post_title;
                    $feedProduct['product-description'] = $product->post->post_content;

                    $discount = $this->getDiscount();
                    $product_price = get_post_meta( $product->id, '_regular_price');
                    $price = FyndiqUtils::getFyndiqPrice($product_price[0], $discount);
                    $_tax = new WC_Tax();//looking for appropriate vat for specific product
                    $rates = $_tax->get_rates( $product->get_tax_class() );


                    $feedProduct['product-price'] = FyndiqUtils::formatPrice($variation['display_price']);
                    $feedProduct['product-vat-percent'] = !empty($rates['rate']) ? $rates['rate'] : 0;
                    $feedProduct['product-oldprice'] = FyndiqUtils::formatPrice($product_price);
                    $feedProduct['product-market'] = WC()->countries->get_base_country();
                    $feedProduct['product-currency'] = get_woocommerce_currency();
                    $feedProduct['product-brand'] = 'UNKNOWN';

                    $terms = get_the_terms( $product->id, 'product_cat' );
                    if ( $terms && ! is_wp_error( $terms ) ) {
                        foreach ($terms as $term) {
                            $feedProduct['product-category-id'] = $term->term_id;
                            $feedProduct['product-category-name'] = $term->name;
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


                    $stock = get_post_meta($product->id, '_stock');
                    $feedProduct['article-quantity'] = intval($stock[0]);

                    $feedProduct['article-location'] = 'unknown';
                    if($variation['sku'] != "") {
                        $sku = $variation['sku'];
                    }
                    else {
                        $sku = $product->id."-".$variation['variation_id'];
                    }
                    $feedProduct['article-sku'] = $sku;
                    $tag_values = array_values($variation['attributes']);
                    $feedProduct['article-name'] = array_shift($tag_values);

                    return $feedProduct;
                }
            }

            public function notification_handle() {
                if(isset($_GET['event'])) {
                    $event = $_GET['event'];
                    $eventName = $event ? $event : false;
                    if ($eventName) {
                        if ($eventName[0] != '_' && method_exists($this, $eventName)) {
                            return $this->$eventName();
                        }
                    }
                }
                header('HTTP/1.0 400 Bad Request');
                die('400 Bad Request');
            }

            public function order_created() {
                $order_id = $_GET['order_id'];
                $orderId = is_numeric($order_id) ? intval($order_id) : 0;
                if ($orderId > 0) {
                    try {
                        $ret = FmHelpers::callApi('GET', 'orders/' . $orderId . '/');

                        $fyndiqOrder = $ret['data'];

                        $orderModel = new FmOrder();

                        if (!$orderModel->orderExists($fyndiqOrder->id)) {
                            $orderModel->createOrder($fyndiqOrder);
                        }
                    } catch (Exception $e) {
                        header('HTTP/1.0 500 Internal Server Error');
                        die('500 Internal Server Error');
                    }
                    return true;
                }
            }

            public function ping() {
                $pingToken = get_option("wcfyndiq_ping_token");

                $token = $_GET['token'];

                if (is_null($token) || $token != $pingToken) {
                    header('HTTP/1.0 400 Bad Request');

                    return die('400 Bad Request');
                }

                // http://stackoverflow.com/questions/138374/close-a-connection-early
                ob_end_clean();
                header('Connection: close');
                ignore_user_abort(true); // just to be safe
                ob_start();
                echo 'OK';
                $size = ob_get_length();
                header('Content-Length: ' . $size);
                ob_end_flush(); // Strange behaviour, will not work
                flush(); // Unless both are called !

                $locked = false;
                $lastPing = get_option("wcfyndiq_ping_time");
                $lastPing = $lastPing ? unserialize($lastPing) : false;
                if ($lastPing && $lastPing > strtotime('15 minutes ago')) {
                    $locked = true;
                }
                if (!$locked) {
                    update_option('wcfyndiq_ping_time', time());
                    $filePath = plugin_dir_path( __FILE__ ) . 'files/feed.csv';
                    $this->feed_write($filePath);
                }
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

            function getProductId() {
                return get_the_ID();
            }

            function getExportState() {
                return isset($_POST['_fyndiq_export']) ? 'exported' : 'not exported';
            }

            function checkCurrency() {
                return (get_woocommerce_currency() != "SEK" && get_woocommerce_currency() != "EUR");
            }
            function checkCountry() {
                return (WC()->countries->get_base_country() != "SE" && WC()->countries->get_base_country() != "DE");
            }
            function checkCredentials() {
                return empty(get_option('wcfyndiq_username')) || empty(get_option('wcfyndiq_apitoken'));
            }

            private function getDiscount() {
                $discount = get_option('wcfyndiq_price_percentage');
                if($discount > 100) {
                    $discount = 100;
                }
                elseif($discount < 0) {
                    $discount = 0;
                }
                return $discount;
            }
        }

        // finally instantiate our plugin class and add it to the set of globals
        $GLOBALS['wc_fyndiq'] = new WC_Fyndiq();
    }
}

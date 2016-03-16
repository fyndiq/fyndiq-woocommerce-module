<?php

class FyndiqTest extends WP_UnitTestCase {

    public function setUp() {
        parent::setUp();
        $hook = parse_url('edit.php?post_type=product');
        $GLOBALS['hook_suffix'] = $hook['path'];
        set_current_screen();
        $this->wc_fyndiq = $this->getMockBuilder('WC_Fyndiq')->setMethods(array('getAction','getRequestPost', 'bulkRedirect', 'returnAndDie', 'getProductId', 'getExportState', 'checkCurrency', 'checkCountry'))->getMock();
        $this->wc_fyndiq->woocommerce_loaded();
        //$this->wc_fyndiq->plugins_loaded();
    }

	function test_fyndiq_class_should_exist() {
		// replace this with some actual testing code
		$this->assertTrue(isset($this->wc_fyndiq));
	}

    /**
     * @group ignore
     */
    function test_setting_action_should_exist() {
        $fakeSections = array();
        //$this->assertTrue(isset($this->wc_fyndiq->fyndiq_settings_action($fakeSections)['wcfyndiq']));
    }

    // Columnable' );
    function test_fyndiq_order_column_sort_return_array() {
        $data = array(
            'fyndiq_order' => 'fyndiq_order'
        );
        $this->assertEquals($data, $this->wc_fyndiq->fyndiq_order_column_sort());
    }

    // Columnable' );
    function test_fyndiq_product_column_sort_return_array() {
        $data = array(
            'fyndiq_export' => 'fyndiq_export'
        );
        $this->assertEquals($data, $this->wc_fyndiq->fyndiq_product_column_sort());
    }

    // Columnable' );
    function test_fyndiq_order_add_column() {
        $default = array();
        $data = $this->wc_fyndiq->fyndiq_order_add_column($default);
        $this->assertEquals(array('fyndiq_order' => 'Fyndiq Order'), $data);
    }

    /**
     * @group ignore
     */
    function test_fyndiq_product_add_bulk_action_product() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );
        global $post_type;
        $post_type = 'product';
        //$this->wc_fyndiq->fyndiq_product_add_bulk_action();
        $this->expectOutputString("                    <script type=\"text/javascript\">
                        jQuery(document).ready(function () {
                            jQuery('<option>').val('fyndiq_export').text('Export to Fyndiq').appendTo(\"select[name='action']\");
                            jQuery('<option>').val('fyndiq_export').text('Export to Fyndiq').appendTo(\"select[name='action2']\");
                            jQuery('<option>').val('fyndiq_no_export').text('Remove from Fyndiq').appendTo(\"select[name='action']\");
                            jQuery('<option>').val('fyndiq_no_export').text('Remove from Fyndiq').appendTo(\"select[name='action2']\");
                        });
                    </script>
                ");
    }

    /**
     * @group ignore
     */
    function test_fyndiq_product_add_bulk_action_order() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );
        global $post_type;
        $post_type = 'shop_order';
        //$this->wc_fyndiq->fyndiq_product_add_bulk_action();
        $this->expectOutputString("                    <script type=\"text/javascript\">
                        jQuery(document).ready(function () {
                            jQuery('<option>').val('fyndiq_delivery').text('Get Fyndiq Delivery Note').appendTo(\"select[name='action']\");
                            jQuery('<option>').val('fyndiq_delivery').text('Get Fyndiq Delivery Note').appendTo(\"select[name='action2']\");
                            jQuery(jQuery(\".wrap h2\")[0]).append(\"<a href='#' id='fyndiq-order-import' class='add-new-h2'>Import From Fyndiq</a>\");
                        });
                    </script>
                ");
    }


    function test_order_meta_box_fyndiq_should_echo_link() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $order_id = $this->createOrder();

        $order = get_post($order_id);
        global $post;
        $post = $order;

        $this->wc_fyndiq->order_meta_box_delivery_note();

        $this->expectOutputString('<a href="https://fyndiq.se/merchant/fake/delivery/note/32" class="button button-primary">Get Fyndiq Delivery Note</a>');
    }

    function test_fyndiq_product_add_column_return_right_array() {
        $defaults = array();
        $return = $this->wc_fyndiq->fyndiq_product_add_column($defaults);
        $correct = array('fyndiq_export' => 'Fyndiq Exported');
        $this->assertEquals($return, $correct);
    }

    function test_fyndiq_product_column_export() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $p = $this->createProduct();

        $this->wc_fyndiq->fyndiq_product_column_export('fyndiq_export', $p);

        $this->expectOutputString("not exported");

        $this->wc_fyndiq->fyndiq_product_column_export('fyndiq_export', $p);

        $this->expectOutputString("not exportednot exported");
    }

    function test_fyndiq_product_column_export_downloadable() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $p = $this->createProduct(true);

        $this->wc_fyndiq->fyndiq_product_column_export('fyndiq_export', $p);

        $this->expectOutputString("Can't be exported");
    }

    function test_fyndiq_all_settings_correct_section() {
        $settings = array();
        $return = $this->wc_fyndiq->fyndiq_all_settings($settings, 'wcfyndiq');

        $expected = array(
            array('name' => 'Fyndiq Settings',
                  'type' => 'title',
                  'desc' => 'The following options are used to configure Fyndiq',
                  'id' => 'wcfyndiq'),
            array('name' => 'Username',
                  'desc_tip' => 'This is the username you use for login on Fyndiq Merchant',
                  'id' => 'wcfyndiq_username',
                  'type' => 'text',
                  'desc' => 'Must be your username'),
            array('name' => 'API-token',
                  'desc_tip' => 'This is the API V2 Token on Fyndiq',
                  'id' => 'wcfyndiq_apitoken',
                  'type' => 'text',
                  'desc' => 'Must be API v2 token'),
            Array (
                  'type' => 'select',
                  'id' => 'wcfyndiq_create_order_status',
                  'name' => 'Order Status',
                  'desc_tip' => 'When a order is imported from fyndiq, this status will be applied.',
                  'options' => array('completed' => 'completed', 'processing' => 'processing', 'pending' => 'pending', 'on-hold' => 'on-hold'),
                  'desc' => 'This must be picked accurate'),
            array('type' => 'sectionend',
                  'id' => 'wcfyndiq')
        );

        $this->assertEquals($expected, $return);
    }

    function test_fyndiq_all_settings_wrong_section() {
        $settings = array();
        $return = $this->wc_fyndiq->fyndiq_all_settings($settings, 'wrong_section');

        $this->assertEquals($settings, $return);
    }

    function test_fyndiq_order_meta_boxes() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $p = $this->createProduct(false, true);
        global $post;
        $post = get_post($p);

        $this->wc_fyndiq->fyndiq_order_meta_boxes();
        global $wp_meta_boxes;
        $expected = array('shop_order' => array('side' => array('default' => array('woocommerce-order-fyndiq-delivery-note' => array(
            'id' => 'woocommerce-order-fyndiq-delivery-note',
            'title' => 'Fyndiq',
            'callback' => Array (0 => $this->wc_fyndiq,
                                 1 => 'order_meta_box_delivery_note'),
            'args' => null
        )))));
        $this->assertEquals($expected, $wp_meta_boxes);
    }

    /**
     * @group ignore
     */
    function test_fyndiq_product_export_bulk_action() {
        //$return = $this->wc_fyndiq->fyndiq_product_export_bulk_action();
        $this->expectOutputString("");
    }

    /**
     * @group ignore
     */
    function test_fyndiq_product_export_bulk_action_working() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $p = $this->createProduct(false, true);

        $this->wc_fyndiq->expects($this->once())->method('getAction')->with($this->equalTo('WP_Posts_List_Table'))->willReturn('fyndiq_export');
        $this->wc_fyndiq->expects($this->once())->method('getRequestPost')->willReturn(array($p));
        $this->wc_fyndiq->expects($this->once())->method('bulkRedirect')->will($this->returnArgument(1));
        //$return = $this->wc_fyndiq->fyndiq_product_export_bulk_action();
        $this->assertEquals(1, $return);
    }

    /**
     * @group ignore
     */
    function test_fyndiq_product_export_bulk_action_remove_working() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $p = $this->createProduct(false, true);

        $this->wc_fyndiq->expects($this->once())->method('getAction')->with($this->equalTo('WP_Posts_List_Table'))->willReturn('fyndiq_no_export');
        $this->wc_fyndiq->expects($this->once())->method('getRequestPost')->willReturn(array($p));
        $this->wc_fyndiq->expects($this->once())->method('bulkRedirect')->will($this->returnArgument(1));
        //$return = $this->wc_fyndiq->fyndiq_product_export_bulk_action();
        $this->assertEquals(1, $return);
    }

    /**
     * @group ignore
     */
    function test_generate_feed_notset() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $p = $this->createProduct(false, true);

        $this->wc_fyndiq->expects($this->once())->method('returnAndDie')->will($this->returnArgument(0));

        //$return = $this->wc_fyndiq->generate_feed();

        $this->assertEquals("", $return);
    }

    /**
     * @group ignore
     */
    function test_generate_feed_working() {
        // Removing the feed so it will test correct part of the function
        $filePath = dirname(dirname(dirname(__FILE__))) . '/files/feed.csv';
        if(file_exists($filePath)) {
            unlink($filePath);
        }

        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $p = $this->createProduct(false, true);

        $this->wc_fyndiq->expects($this->once())->method('getExportState')->willReturn("exported");

        //$this->wc_fyndiq->fyndiq_product_save($p);

        update_option('wcfyndiq_username', 'test');
        update_option('wcfyndiq_apitoken', 'test');

        $this->wc_fyndiq->expects($this->once())->method('returnAndDie')->will($this->returnArgument(0));

        //$return = $this->wc_fyndiq->generate_feed();

        $this->assertEquals("product-id,product-image-1-identifier,product-image-1-url,product-title,product-market,product-description,product-price,product-oldprice,product-currency,product-vat-percent,article-quantity,article-sku,article-name
", $return);
    }

    function test_get_url() {
        $this->wc_fyndiq->get_url();
        $this->expectOutputString('                <script type="text/javascript">
                    var wordpressurl = \'http://example.org\' ;
                </script>
                <script src="http://example.org/wp-content/plugins/woocommerce-fyndiq/stylesheet/order-import.js" type="text/javascript"></script>
                ');
    }

    /**
     * @group ignore
     */
    function test_fyndiq_add_product_field() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $p = $this->createProduct(false, true);

        $this->wc_fyndiq->expects($this->once())->method('getProductId')->willReturn($p);

        //$this->wc_fyndiq->fyndiq_add_product_field();

        $this->expectOutputString('<div class="options_group"><p class="form-row input-checkbox" id="_fyndiq_export_field">
						<label class="checkbox " >
						<input type="checkbox" class="input-checkbox " name="_fyndiq_export" id="_fyndiq_export" value="1"  /> Export to Fyndiq</label><span class="description">mark this as true if you want to export to Fyndiq</span></p></div>');
    }

    /**
     * @group ignore
     */
    function test_fyndiq_add_product_field_downloadable() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $p = $this->createProduct(true, false);

        $this->wc_fyndiq->expects($this->once())->method('getProductId')->willReturn($p);

        //$this->wc_fyndiq->fyndiq_add_product_field();

        $this->expectOutputString('<div class="options_group">Can\'t export this product to Fyndiq</div>');
    }

    /**
     * @group ignore
     */
    function test_fyndiq_product_save() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $p = $this->createProduct(true, false);

        $this->wc_fyndiq->expects($this->once())->method('getExportState')->willReturn("exported");

        //$this->wc_fyndiq->fyndiq_product_save($p);

        $exported = get_post_meta($p, '_fyndiq_export', true);

        $this->assertEquals("exported", $exported);
    }

    function test_fyndiq_notice_currency() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $this->wc_fyndiq->expects($this->once())->method('checkCurrency')->willReturn(true);

        $this->wc_fyndiq->my_admin_notice();
        $this->expectOutputString('<div class="error">
                   <p><Storng>Wrong Currency</Storng>: Fyndiq only works in EUR and SEK. change to correct currency. Current Currency: GBP</p>
                </div>');
    }

    function test_fyndiq_notice_country() {
        $contributor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $contributor_id );

        $this->wc_fyndiq->expects($this->once())->method('checkCountry')->willReturn(true);

        $this->wc_fyndiq->my_admin_notice();
        $this->expectOutputString('<div class="error">
                   <p><Storng>Wrong Country</Storng>: Fyndiq only works in Sweden and Germany. change to correct country. Current Country: GB</p>
                </div>');
    }


    private function createProduct($downloadable = false, $fyndiq = false) {
        $post = array(
            'post_author' => 0,
            'post_content' => '',
            'post_status' => "publish",
            'post_title' => "Test Product",
            'post_parent' => '',
            'post_type' => "product",

        );
        //Create post
        $post_id = wp_insert_post( $post );
        wp_set_object_terms( $post_id, 'Test', 'product_cat' );
        wp_set_object_terms($post_id, 'simple', 'product_type');



        update_post_meta( $post_id, '_visibility', 'visible' );
        update_post_meta( $post_id, '_stock_status', 'instock');
        update_post_meta( $post_id, 'total_sales', '0');
        if ($downloadable) {
            update_post_meta( $post_id, '_downloadable', 'yes');
        }
        else {
            update_post_meta( $post_id, '_downloadable', 'no');
        }

        if($fyndiq) {
            update_post_meta( $post_id, 'fyndiq_delivery_note', 'https://fyndiq.se/merchant/delivery_note');
            update_post_meta( $post_id, '_fyndiq_export', 'exported');
        }
        update_post_meta( $post_id, '_virtual', 'yes');
        update_post_meta( $post_id, '_regular_price', "1" );
        update_post_meta( $post_id, '_sale_price', "1" );
        update_post_meta( $post_id, '_purchase_note', "" );
        update_post_meta( $post_id, '_featured', "no" );
        update_post_meta( $post_id, '_weight', "" );
        update_post_meta( $post_id, '_length', "" );
        update_post_meta( $post_id, '_width', "" );
        update_post_meta( $post_id, '_height', "" );
        update_post_meta($post_id, '_sku', "webtest item no");
        update_post_meta( $post_id, '_product_attributes', array());
        update_post_meta( $post_id, '_sale_price_dates_from', "" );
        update_post_meta( $post_id, '_sale_price_dates_to', "" );
        update_post_meta( $post_id, '_price', "1" );
        update_post_meta( $post_id, '_sold_individually', "" );
        update_post_meta( $post_id, '_manage_stock', "no" );
        update_post_meta( $post_id, '_backorders', "no" );
        update_post_meta( $post_id, '_stock', "" );

        return $post_id;
    }

    function createOrder() {
        // build order data
        $order_data = array(
            'post_name' => 'order-' . date_format(new DateTime(date("Y-m-d H:i:s")), 'M-d-Y-hi-a'), //'order-jun-19-2014-0648-pm'
            'post_type' => 'shop_order',
            'post_title' => 'Order &ndash; ' . date_format(new DateTime(date("Y-m-d H:i:s")), 'F d, Y @ h:i A'), //'June 19, 2014 @ 07:19 PM'
            'post_status' => 'wc-completed',
            'ping_status' => 'closed',
            'post_excerpt' => 'Generated from Fyndiq',
            'post_author' => 0,
            'post_password' => uniqid('order_'), // Protects the post just in case
            'post_date' => date_format(new DateTime(date("Y-m-d H:i:s")), 'Y-m-d H:i:s e'), //'order-jun-19-2014-0648-pm'
            'comment_status' => 'open'
        );

// create order
        $order_id = wp_insert_post($order_data, true);

        if(!is_wp_error($order_id)) {
            add_post_meta($order_id, 'fyndiq_id', 32, true);
            add_post_meta($order_id, 'fyndiq_delivery_note', 'https://fyndiq.se' . "/merchant/fake/delivery/note/32", true);
        }
        return $order_id;
    }
}
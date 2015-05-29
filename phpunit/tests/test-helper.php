<?php

class HelperTest extends WP_UnitTestCase {

    public function setUp() {
        parent::setUp();
    }

    function test_wordpress_version() {
        $return = FmHelpers::get_woocommerce_version();
        $this->assertTrue(($return > "2.0.0" && $return < "2.5.0"));
    }
}
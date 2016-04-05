<?php

class FmHelperTest extends WP_UnitTestCase
{

    public function setUp()
    {
        parent::setUp();
    }

    /**
     * @group ignore
     */
    public function test_wordpress_version()
    {
        $this->markTestIncomplete('This test has not been completed yet.');
        $return = FmHelpers::get_woocommerce_version();

        $this->assertTrue(($return > "2.0.0" && $return < "2.5.0"));
    }
}

<?php

/**
 *
 * File for handling .
 *
 */
class FmDiagnosticsTest extends WP_UnitTestCase
{
    public function setUp()
    {
        parent::setUp();
        $hook = parse_url('edit.php?post_type=product');
        $GLOBALS['hook_suffix'] = $hook['path'];
        set_current_screen();
        $this->wc_fyndiq = $this->getMockBuilder('WC_Fyndiq')->setMethods(array('getAction','getRequestPost', 'bulkRedirect', 'returnAndDie', 'getProductId', 'getExportState', 'checkCurrency', 'checkCountry'))->getMock();
        $this->wc_fyndiq->woocommerce_loaded();
        //$this->wc_fyndiq->plugins_loaded();
    }

    public function test_pluginActionLink()
    {
        $actionLinks = array();
        $return = FmDiagnostics::pluginActionLink($actionLinks);
        $this->assertEquals($expected, $return);
    }
}

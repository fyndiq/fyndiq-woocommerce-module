<?php

/**
 *
 * File for handling .
 *
 */
class FmDiagnosticsTest extends WP_UnitTestCase
{

    public function testSetHooks()
    {
        $this->assertTrue(FmDiagnostics::setHooks());
    }

    public function testPluginActionLink()
    {
        $actionLinks = array();
        $return = FmDiagnostics::pluginActionLink($actionLinks);

        $expected = array(
            0 => '<a href="http://example.org/wp-admin/admin.php?page=fyndiq-check">Fyndiq Check</a>'
        );
        $this->assertEquals($expected, $return);
    }

    public function testAddDiagnosticMenuItem()
    {
        $user_id = $this->factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($user_id);
        $return = FmDiagnostics::addDiagnosticMenuItem();
        $this->assertEquals('admin_page_fyndiq-check', $return);
    }

    public function testDiagPage()
    {
        $expected = "<h1>Fyndiq Integration Diagnostic Page</h1><p>This page contains diagnostic information that may be useful in the 
        event that the Fyndiq WooCommerce integration plugin runs in to problems.</p><h2>File Permissions</h2>Feed file name: `/opt/wptests/wordpress/wp-content/uploads/fyndiq-feed.csv` (does not exist)<br />Cannot create file. Please make sure that the server can create new files in `/opt/wptests/wordpress/wp-content/uploads`<h2>Classes</h2>Class `FyndiqAPI` is found.<br />Class `FyndiqAPICall` is found.<br />Class `FyndiqCSVFeedWriter` is found.<br />Class `FyndiqFeedWriter` is found.<br />Class `FyndiqOutput` is found.<br />Class `FyndiqPaginatedFetch` is found.<br />Class `FyndiqUtils` is found.<br />Class `FmHelpers` is found.<br />Class `FmDiagnostics` is found.<br />Class `FmError` is found.<br />Class `FmExport` is found.<br />Class `FmField` is found.<br />Class `FmSettings` is found.<br />Class `FmUpdate` is found.<br />Class `FmOrder` is found.<br />Class `FmOrderFetch` is found.<br />Class `FmPost` is found.<br />Class `FmProduct` is found.<br />Class `TGM_Plugin_Activation` is <strong>NOT</strong> found.<h2>API Connection</h2>Module is not authorized.<h2>Installed Plugins</h2>Akismet v. 3.1.7<br />Hello Dolly v. 1.6";
        FmDiagnostics::diagPage();
        $this->expectOutputString($expected);
    }
}

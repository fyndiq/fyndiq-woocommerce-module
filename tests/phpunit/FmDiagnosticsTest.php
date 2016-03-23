<?php

/**
 *
 * File for handling .
 *
 */
class FmDiagnosticsTest extends WP_UnitTestCase
{

    public function test_setHooks()
    {
        $this->assertTrue(FmDiagnostics::setHooks());
    }

    public function test_pluginActionLink()
    {
        $actionLinks = array();
        $return = FmDiagnostics::pluginActionLink($actionLinks);

        $expected = array (
            0 => '<a href="http://example.org/wp-admin/admin.php?page=fyndiq-check">Fyndiq Check</a>'
        );
        $this->assertEquals($expected, $return);
    }

    public function test_addDiagnosticMenuItem()
    {
        $user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );
        $return = FmDiagnostics::addDiagnosticMenuItem();
        $this->assertEquals('admin_page_fyndiq-check', $return);
    }
}

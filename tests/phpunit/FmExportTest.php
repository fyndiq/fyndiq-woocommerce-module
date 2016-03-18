<?php

class FmExportTest extends WP_UnitTestCase
{
    function test_getDescriptionPOST()
    {
        add_option('wcfyndiq_description_picker', FmExport::DESCRIPTION_SHORT);
        $_POST['post_excerpt'] = "test";
        $return = FmExport::getDescriptionPOST();
        $expected = "test";
        $this->assertEquals($expected, $return);
    }
}

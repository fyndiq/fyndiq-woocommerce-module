<?php

class FmErrorTest extends WP_UnitTestCase
{

    public function test_renderErrorEmpty()
    {
        FmError::renderError();
        $this->expectOutputString("");
    }

    public function test_renderErrorReal()
    {
        $_REQUEST['fyndiqMessageType'] = "test";
        $_REQUEST['fyndiqMessage'] = "test";

        FmError::renderError();
        $this->expectOutputString("<div class='test'><p>test</p></div>");
    }
}

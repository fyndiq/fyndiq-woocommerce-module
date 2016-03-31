<?php

class FyndiqTest extends PHPUnit_Framework_TestCase
{

    protected function setUp()
    {
        $this->fmWoo = $this->getMockBuilder('stdClass')
            ->setMethods(array(
                'addAction',
                'wpUploadDir',
                'loadPluginTextdomain',
                'pluginBasename'
            ))
            ->getMock();

        $this->fmOutput = $this->getMockBuilder('stdClass')
            ->getMock();

        $this->wcFyndiq = new WC_Fyndiq($this->fmWoo, $this->fmOutput);
    }

    public function testLocaleLoad()
    {
        $this->fmWoo->expects($this->once())
            ->method('loadPluginTextdomain')
            ->with(
                $this->equalTo(WC_Fyndiq::TEXT_DOMAIN),
                $this->equalTo(false),
                $this->equalTo('/translations/')
            )
            ->willReturn(true);

        $result = $this->wcFyndiq->localeLoad();
        $this->assertTrue($result);
    }

}

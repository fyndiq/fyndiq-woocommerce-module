<?php

class FyndiqTest extends PHPUnit_Framework_TestCase
{

    protected function setUp()
    {
        $this->fmWoo = $this->getMockBuilder('stdClass')
            ->setMethods(
                array(
                'addAction',
                'wpUploadDir',
                'loadPluginTextdomain',
                'pluginBasename',
                'setDoingAJAX',
                'getOption',
                'wpDie',
                )
            )
            ->getMock();

        $this->fmOutput = $this->getMockBuilder('stdClass')
            ->setMethods(
                array(
                'showError',
                )
            )
            ->getMock();

    }

    public function testLocaleLoad()
    {
        $this->wcFyndiq = new WC_Fyndiq($this->fmWoo, $this->fmOutput);
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


    public function testCheckDebugEnabled()
    {
        $get = array('event' => 'debug');

        $this->fmWoo->expects($this->once())
            ->method('getOption')
            ->with(
                $this->equalTo('wcfyndiq_enable_debug')
            )
            ->willReturn(null);

        $this->fmOutput->expects($this->once())
            ->method('showError')
            ->with(
                $this->equalTo(403),
                $this->equalTo('Forbidden'),
                $this->equalTo('Forbidden')
            )
            ->willReturn(true);

        $this->fmWoo->expects($this->once())
            ->method('wpDie')
            ->willReturn(true);

        $result = $this->wcFyndiq->handleNotification($get);
        $this->assertTrue($result);
    }
}

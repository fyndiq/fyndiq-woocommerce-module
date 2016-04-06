<?php

class FmErrorTest extends PHPUnit_Framework_TestCase
{

    public function testRenderErrorProvider()
    {
        return array(
            array(
                'message',
                FmError::CLASS_ERROR,
                '<div class="error"><p>message</p></div>'
            ),
            array(
                '<a href="#">a</a>',
                FmError::CLASS_ERROR,
                '<div class="error"><p>&lt;a href=&quot;#&quot;&gt;a&lt;/a&gt;</p></div>'
            ),
        );
    }

    /**
     * @dataProvider testRenderErrorProvider
     */
    public function testRenderError($message, $messageType, $expected)
    {
        $fmOutput = $this->getMockBuilder('stdClass')
            ->setMethods(array('output'))
            ->getMock();
        $fmOutput->expects($this->once())
            ->method('output')
            ->with($expected)
            ->willReturn(true);
        $result = FmError::renderError($message, $messageType, $fmOutput);
        $this->assertTrue($result);
    }

    public function testRenderErrorRawProvider()
    {
        return array(
            array(
                'message',
                FmError::CLASS_ERROR,
                '<div class="error"><p>message</p></div>'
            ),
            array(
                '<a href="#">a</a>',
                FmError::CLASS_ERROR,
                '<div class="error"><p><a href="#">a</a></p></div>'
            ),
        );
    }

    /**
     * @dataProvider testRenderErrorRawProvider
     */
    public function testRenderErrorRaw($message, $messageType, $expected)
    {
        $fmOutput = $this->getMockBuilder('stdClass')
            ->setMethods(array('output'))
            ->getMock();
        $fmOutput->expects($this->once())
            ->method('output')
            ->with($expected)
            ->willReturn(true);
        $result = FmError::renderErrorRaw($message, $messageType, $fmOutput);
        $this->assertTrue($result);
    }


}

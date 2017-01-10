<?php
namespace Hoborg\Widget;

class ContentTest extends \PHPUnit_Framework_TestCase {

	/** @test */
	public function shouldCreate() {
		$kernelMock = $this->getKernelMock();
		$widget = new Content($kernelMock, array());

		$this->assertInstanceOf('\\Hoborg\\Dashboard\\Widget', $widget);
	}

	protected function getKernelMock() {
		return $this->getMock('\\Hoborg\\Dashboard\\Kernel', array(), array(''));
	}
}

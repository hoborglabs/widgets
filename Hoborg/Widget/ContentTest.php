<?php
namespace Hoborg\Widget;

include_once __DIR__ . '/Content.php';

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

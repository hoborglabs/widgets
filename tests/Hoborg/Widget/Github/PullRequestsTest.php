<?php
namespace Hoborg\Widget\Github;

class PullRequestsTest extends \PHPUnit_Framework_TestCase {

	protected $baseDir = '';

	public function setup() {
		$this->baseDir = preg_replace('/tests\/Hoborg/', 'Hoborg', __DIR__);
	}

	public function testSettingDefaultTemplate() {
		$kernelMock = $this->getKernelMock();
		$widget = new PullRequests($kernelMock, array());

		$this->assertEquals($this->baseDir . '/PullRequests/views/default.html', $widget->getViewFile(),
				"The default view for PullRequests widget should be set to 'default.html'");
	}

	public function testSettingTemplateFromViewProperty() {
		$kernelMock = $this->getKernelMock();
		$widget = new PullRequests($kernelMock, array(
			'config' => array(
				'view' => 'my-test-view'
			)
		));

		$this->assertEquals($this->baseDir . '/PullRequests/views/my-test-view.html', $widget->getViewFile(),
				"view file should be using `config.view` for the view name `my-test-view`");
	}

	protected function getKernelMock() {
		return $this->getMock('\\Hoborg\\Dashboard\\Kernel', array(), array(''));
	}
}

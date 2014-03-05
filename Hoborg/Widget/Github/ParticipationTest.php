<?php
namespace Hoborg\Widget\Github;

include_once __DIR__ . '/Participation.php';

class ParticipationTest extends \PHPUnit_Framework_TestCase {

	public function testSettingDefaultTemplate() {
		$kernelMock = $this->getKernelMock();
		$widget = new Participation($kernelMock, array());

		$this->assertEquals(__DIR__ . '/Participation/views/default.html', $widget->getViewFile(),
				"The default view for Participation widget should be set to 'default.html'");
	}

	public function testSettingTemplateFromViewProperty() {
		$kernelMock = $this->getKernelMock();
		$widget = new Participation($kernelMock, array(
			'config' => array(
				'view' => 'my-test-view'
			)
		));

		$this->assertEquals(__DIR__ . '/Participation/views/my-test-view.html', $widget->getViewFile(),
				"view file should be using `config.view` for the view name `my-test-view`");
	}

	public function testGetDataSetsFakeReposData() {
		$fakeOrg = array(
			array('name' => 'repo-one', 'full_name' => 'test-organization/repo-one')
		);
		$fakeRepoParticipation = array(
			'all' => array(0,1,2,3,4,0,1,2,3,4),
			'owner' => array(0,1,0,0,0,0,0,1,0,0),
		);
		$kernelMock = $this->getKernelMock();

		$githubMock = $this->getMock('\\Hoborg\\Dashboard\\Client\\Github', array('get'), array('fake/url'));
		$githubMock->expects($this->at(0))
			->method('get')
			->with('/orgs/test-organization/repos')
			->will($this->returnValue($fakeOrg));
		$githubMock->expects($this->at(1))
			->method('get')
			->with('/repos/test-organization/repo-one/stats/participation')
			->will($this->returnValue($fakeRepoParticipation));

		$widgetData = array(
			'config' => array(
				'organization' => 'test-organization'
			)
		);
		$widget = $this->getMock('\\Hoborg\\Widget\\Github\\Participation', array('getGithubClient'),
				array($kernelMock, $widgetData));
		$widget->expects($this->once())
			->method('getGithubClient')
			->will($this->returnValue($githubMock));

		$this->assertEquals(array('repos' => array(
			array(
				'participation' => $fakeRepoParticipation,
				'name' => 'repo-one'
			))),
			$widget->getData()
		);
	}

	protected function getKernelMock() {
		return $this->getMock('\\Hoborg\\Dashboard\\Kernel', array(), array(''));
	}
}
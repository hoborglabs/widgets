<?php
namespace Hoborg\Widget\Aws;

use Aws\Result as AwsResult;

include_once __DIR__ . '/Ips.php';

class IpsTest extends \PHPUnit_Framework_TestCase {

	public function testSettingDefaultTemplate() {
		$kernelMock = $this->getKernelMock();
		$widget = new Ips($kernelMock, array());

		$this->assertEquals(__DIR__ . '/Ips/views/default.html', $widget->getViewFile(),
				"The default view for Ips widget should be set to 'default.html'");
	}

	public function testGetDataSetsFakeReposData() {
		$fakeEc2Data = new AwsResult(array(
			'Reservations' => (
				array(
					array(
						'Instances' => array(
							array(
								'PrivateIpAddress' => '10.1.1.1',
								'Tags' => array(
									array(
										'Key' => 'Role',
										'Value' => 'api_test'
									)
								)
							)
						)
					)
				)
			)
		));
		$ec2ExpectedArgs = array(
			'Filters' => array(
				array(
					'Name' => 'instance-state-name',
					'Values' => array( 'running' )
				),
				array(
					'Name' => 'tag:Environment',
					'Values' => array( 'dev' )
				)
			)
		);

		$kernelMock = $this->getKernelMock();

		$ec2Mock = $this->getMock('\\Aws\\Ec2\\Ec2Client', array('describeInstances'), array(), '', false);
		$ec2Mock->expects($this->at(0))
			->method('describeInstances')
			->with($ec2ExpectedArgs )
			->will($this->returnValue($fakeEc2Data));

		$widgetData = array(
			'config' => array(
				'environment' => 'dev',
				'region' => 'eu-west-1'
			)
		);
		$widget = $this->getMock('\\Hoborg\\Widget\\Aws\\Ips', array('getEc2Client'),
				array($kernelMock, $widgetData));
		$widget->expects($this->once())
			->method('getEc2Client')
			->will($this->returnValue($ec2Mock));

		$this->assertEquals(array(
				array(
					'role' => 'api_test',
					'ip' => '10.1.1.1'
				)
			),
			$widget->getData()
		);
	}

	protected function getKernelMock() {
		return $this->getMock('\\Hoborg\\Dashboard\\Kernel', array(), array(''));
	}
}

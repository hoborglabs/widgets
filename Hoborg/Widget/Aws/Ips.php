<?php
namespace Hoborg\Widget\Aws;

use Aws\Ec2\Ec2Client;
use Aws\Ec2\Exception\Ec2Exception;
use Aws\Exception\AwsException;
use Aws\Exception\CredentialsException;

use Hoborg\Dashboard\Widget;

class Ips extends Widget {

	public function bootstrap() {
		$this->setupTemplate();
		$this->data['data'] = $this->getData();
	}

	public function getData() {
		$cfg = $this->get('config', array());
		$key = md5('ips' . serialize($cfg));

		if (extension_loaded('apc')) {
			$data = apc_fetch($key);
			if ($data) {
				return $data;
			}
		}

		$env = $cfg['environment'];
		$data = array();

		$ec2Client = $this->getEc2Client();

		try {
			$result = $ec2Client->describeInstances(array(
				'Filters' => array(
					array(
						'Name' => 'instance-state-name',
						'Values' => array( 'running' )
					),
					array(
						'Name' => 'tag:Environment',
						'Values' => [ $env ]
					)
				)
			));
		} catch (Ec2Exception $e) {
			echo $e->getMessage();
			return $data;
		} catch (CredentialsException $e) {
			echo $e->getMessage();
			return $data;
		} catch (AwsException $e) {
			echo $e->getMessage();
			return $data;
		}

		$data = array_map(function($reservation) {

			$ip_data = array_map(function($instance) {
				$roleTags = array_filter($instance['Tags'], function($tag) {
					return $tag['Key'] == 'Role';
				});
				if(empty($roleTags)) {
					return false;
				}
				$roleTag = reset($roleTags);
				return array(
					'role' => $roleTag['Value'],
					'ip' => $instance['PrivateIpAddress']
				);
			}, $reservation['Instances']);

			if(empty($ip_data)) {
				return false;
			}

			return reset($ip_data);
		}, $result['Reservations']);

		usort($data, function($a, $b) {
			return strcmp($a['role'], $b['role']);
		});

		if (extension_loaded('apc')) {
			apc_store($key, $data, 180);
		}

		return $data;
	}

	public function getViewFile() {
		$cfg = $this->get('config', array());
		return __DIR__ . '/Ips/views/' . (empty($cfg['view']) ? 'default' : $cfg['view'] ) . '.html';
	}

	protected function setupTemplate() {
		$this->data['template'] = file_get_contents($this->getViewFile());
	}

	protected function getEc2Client() {
		$cfg = $this->get('config', array());

		$ec2Client = new Ec2Client(array(
			'region'  => $cfg['region'],
			'version' => 'latest'
		));

		return $ec2Client;
	}
}

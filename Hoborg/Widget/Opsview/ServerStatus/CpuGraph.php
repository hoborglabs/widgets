<?php
namespace Hoborg\Widget\Opsview\ServerStatus;

require_once __DIR__ . '/../Opsview.php';

use Hoborg\Widget\Opsview\Opsview;

/**
 * 
 * conf.template: "valid-tpl"
 * conf.opsviewUrl: "http://opsview.company.net"
 * conf.groups: [
 *   { name: "WEB", hosts: ["web01", "web02", "web03"], metric: "idle", service: "Linux Prod CPU Stats" }
 *   { name: "DB", hosts: ["db01", "db02"], metric: "idle", service: "Linux Prod CPU Stats" }
 *   { name: "CACHE", hosts: ["cache01", "cache02"], metric: "idle", service: "Linux Prod CPU Stats" }
 * ]
 */
class CpuGraph extends Opsview {

	public function bootstrap() {

		$config = $this->getData('config', array());
		$this->opsviewUrl = $config['opsviewUrl'];

		$srv = $this->getGroups($config['groups']);

		$this->data['data'] = array(
			'servers' => $srv,
		);
		$this->data['data']['legend'] = array();
		foreach ($config['groups'] as $group) {
			$this->data['data']['legend'][] = $group['name'];
		}

		$tplName = empty($config['view']) ? 'cpu-graph' : $config['view'];
		$this->data['template'] = file_get_contents(__DIR__ . "/{$tplName}.mustache");
	}

	protected function getGroups(array $groups) {
		$servers = array();
		foreach ($groups as $group) {
			$params = array(
				'hostname' => $group['hosts'],
				'servicename' => 'Linux Prod CPU Stats'
			);
			$data = $this->getOpsviewData('/rest/status/service', $params);

			if (empty($data)) {
				continue;
			}

			$srvs = $this->analyseData($data['list']);
			foreach ($srvs as $srv) {
				$servers[] = $srv + array('type' => $group['name']);
			}
		}

		return $servers;
	}

	protected function analyseData(array $servers) {
		$filtered = array();

		foreach ($servers as $server) {
			if ('up' !== strtolower($server['state'])) {
				continue;
			}

			$v = $this->getServerVector($server);
			if (!empty($v)) {
				$filtered[] = array(
					'name' => $server['name'],
					'vector' => $v,
				);
			}
		}

		return $filtered;
	}

	protected function getServerVector($server) {
		preg_match_all('/([^, ]+):\s*([0-9.]+)/', $server['services'][0]['output'], $matches);
		$metrics = array_combine($matches[1], $matches[2]);
	
		$usrL = $metrics['user'];
		$sysL = $metrics['sys'];
		$iowaitL = $metrics['iowait'];
	
		$usrV = array(0, $usrL);
		// sys on 120 deg on right
		$sysV = array($sysL * -1 * sin(deg2rad(-120)), $sysL * cos(deg2rad(-120)));
		// iowait on the left
		$iowaitV = array($iowaitL * -1 * sin(deg2rad(120)), $iowaitL * cos(deg2rad(120)));
	
		return array(
				$iowaitV[0] + $sysV[0] + $usrV[0],
				$iowaitV[1] + $sysV[1] + $usrV[1],
				$iowaitL + $sysL + $usrL
		);
	}

}
<?php
namespace Hoborg\Widget\Opsview\ServerStatus;

require_once __DIR__ . '/../Opsview.php';

use Hoborg\Widget\Opsview\Opsview;

/**
 * 
 * conf.template: "valid-tpl"
 * conf.opsviewUrl: "http://opsview.company.net"
 * conf.username: "api-user",
 * conf.password: "api-password"
 * conf.groups: [
 *   {
 *     name: "WEB",
 *     hosts: ["web01", "web02", "web03", "web04"],
 *     monitor: [
 *       {
 *         metric: "idle",
 *         service: "Linux Prod CPU Stats"
 *         warm: "<= 60"
 *         hot: "<= 30"
 *       },
 *       {
 *         metric: "iowait",
 *         service: "Linux Prod CPU Stats"
 *         warm: "<= 5"
 *         hot: "<= 15"
 *       },
 *     ]
 *   }
 *   { name: "DB", hosts: ["db01", "db02"], metric: "idle", service: "Linux Prod CPU Stats" }
 *   { name: "CACHE", hosts: ["cache01", "cache02"], metric: "idle", service: "Linux Prod CPU Stats" }
 * ]
 */
class Monitor extends Opsview {
	
	public function bootstrap() {
		
		$config = $this->getData('config', array());
		$this->opsviewUrl = $config['opsviewUrl'];

		$params = array(
			'hostname' => $config['groups'][0]['hosts'],
			'servicename' => 'Linux Prod CPU Stats'
		);
		$data = $this->getOpsviewData('/rest/status/service', $params);
		
		$filter = empty($config['groups'][0]['filter']) ? 'defaultFilter' : $config['groups'][0]['filter'];
		$srv = $this->filterData($data['list'], $filter);

		$this->data['data'] = array(
			'servers' => $srv,
		);

		$tplName = empty($config['view']) ? 'simple-list' : $config['view'];
		$this->data['template'] = file_get_contents(__DIR__ . "/{$tplName}.mustache");
	}

	protected function cpuHotOnlyFilter($server) {
		preg_match_all('/([^, ]+):\s*([0-9.]+)/', $server['services'][0]['output'], $matches);
		$metrics = array_combine($matches[1], $matches[2]);

		if ($metrics['idle'] < 40 || $metrics['iowait'] > 15) {
			return array(
				'name' => $server['name'],
				'color' => 'red',
				'metrics' => $metrics,
			);
		}
		return null;
	}

	protected function defaultFilter($server) {
		preg_match_all('/([^, ]+):\s*([0-9.]+)/', $server['services'][0]['output'], $matches);
		$metrics = array_combine($matches[1], $matches[2]);
		
		// hot
		if ($metrics['idle'] < 40 || $metrics['iowait'] > 15) {
			return array(
				'name' => $server['name'],
				'color' => 'red',
				'metrics' => $metrics,
			);
		}

		// worm
		if ($metrics['idle'] < 60 || $metrics['iowait'] > 5) {
			return array(
				'name' => $server['name'],
				'color' => 'white',
				'metrics' => $metrics,
			);
		}

		return array(
			'name' => $server['name'],
			'color' => 'grey',
			'metrics' => $metrics,
			'state' => $server['state'],
		);
	}

	protected function filterData(array $servers, $filterFunction = 'defaultFilter') {
		$filtered = array();

		foreach ($servers as $server) {
			if ('up' !== strtolower($server['state'])) {
				$filtered[] = array(
					'name' => $server['name'],
					'color' => 'black',
					'metrics' => $metrics,
					'state' => $server['state'],
				);
				continue;
			}

			$f = $this->$filterFunction($server);
			if (!empty($f)) {
				$filtered[] = $f;
			}
		}
		
		return $filtered;
	}
}
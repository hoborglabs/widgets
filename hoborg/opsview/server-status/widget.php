<?php

/**
 * This is a general widget for displaying number and trends.
 */
class OpsViewServerStatusWidget extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$widget = $this->data;
		$config = $this->getData('config', array());

		$data = $this->getOpsViewData();
		$upServers = $deadServers = $servers = array();
		foreach ($data['list'] as $srv) {
			if ('up' !=$srv['state']) {
				$deadServers[] = $srv;
				continue;
			}

			$memory = $lan = $cpu = array();
			foreach ($srv['services'] as $service) {
				if ('Connectivity - LAN' == $service['name']) {
					$m = array();
					preg_match('/.*rta ([\d\.]+)ms.*lost (\d+).*/', $service['output'], $m);
					$lan = array(
						'rta' => $m[1],
						'lost' => $m[2]
					);
				}
				if ('Linux Prod CPU Stats' == $service['name']) {
					$m = array();
					preg_match('/.*user: ([\d\.]+).*nice: ([\d\.]+).*sys: ([\d\.]+).*iowait: ([\d\.]+).* irq: ([\d\.]+).* softirq: ([\d\.]+).*idle: ([\d\.]+).*/', $service['output'], $m);
					$cpu = array(
				        'user' => $m[1],
				        'nice' => $m[2],
						'sys' => $m[3],
						'iowait' => $m[4],
						'irq' => $m[5],
						'softirq' => $m[6],
						'idle' => $m[7],
					);
				}
				if ('Linux Prod Memory Stats' == $service['name']) {
					$m = array();
					preg_match('/.*real (\d+)\%.*buffer: (\d+).*cache: (\d+).*swap: (\d+)/', $service['output'], $m);
					$memory = array(
						'real' => $m[1], // %
						'buffer' => $m[2], // MB
						'cache' => $m[3], // MB
						'swap' => $m[4], // %
					);
				}
			}

			$srv['memory'] = $memory;
			$srv['lan'] = $lan;
			$srv['cpu'] = $cpu;
			$upServers[] = $srv;
		}


		foreach ($upServers as $srv) {
			if (!empty($config['display-conditions']['cpu'])) {
				if ($srv['cpu']['user'] > $config['display-conditions']['cpu']['user']) {
					$servers[] = $srv;
				}
			}
		}

		$widget['data'] = array(
			'servers' =>  $servers,
		);
		$tplName = empty($config['view']) ? 'view' : $config['view'];
//		ob_start();
//		include __DIR__ . "/{$tplName}.phtml";
//		$widget['body'] = ob_get_clean();
		$widget['template'] = file_get_contents(__DIR__ . "/{$tplName}.mustache");

		$this->data = $widget;
	}

	protected function getOpsViewData() {
		$config = $this->getData('config', array());

		$url = $config['url'];
		$post = array(
			'username' => $config['username'],
			'password' => $config['password'],
		);
		$postString;
		foreach ($post as $k => $v) {$postString[] = urlencode($k) . '=' . urlencode($v); }
		$postString = implode('&', $postString);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url . '/rest/login');
		curl_setopt($curl, CURLOPT_POST, count($post));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postString);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		//execute post
		$result = curl_exec($curl);
		$loginData = json_decode($result, true);
		curl_close($curl);


		$getQuery = array();
		foreach ($config['params'] as $k => $v) {
			$getQuery[] = urlencode($k) . '=' . urlencode($v);
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url . '/rest/status/service?' . implode('&', $getQuery));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-type: application/json",
			"X-Opsview-Username: {$post['username']}",
			"X-Opsview-Token: {$loginData['token']}",
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($ch);
//   		var_dump(json_decode($result, true));
		curl_close($ch);

		//close connection
		return json_decode($result, true);
	}
}

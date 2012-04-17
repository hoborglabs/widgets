<?php

class ServerStatusWidget extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$widget = $this->data;

		if (empty($widget['conf']['data'])) {
			$widget['body'] = $widget['error'] = 'Missing or empty data configuration `widget.conf.data`';
			return $widget;
		}

		$dataFile = $path = $this->kernel->findFileOnPath(
			$widget['conf']['data'],
			$this->kernel->getDataPath()
		);
		$serversStatus = json_decode(file_get_contents($dataFile), true);
		$serverMap = array();

		foreach ($serversStatus as $srv) {
			$serverMap[$srv['name']] = $srv;
		}

		// now prepare groups of servers
		$servers = $widget['conf']['servers'];
		$labelWidth = empty($widget['conf']['label-width']) ?
			100 : $widget['conf']['label-width'];
		$displayGroup = true;

		$first = reset($servers);

		if (!is_array($first)) {
			$servers = array($servers);
			$displayGroup = false;
		}
		foreach ($servers as $groupName => & $group) {
			foreach ($group as $key => $srvName) {
				if (empty($serverMap[$srvName])) {
					$group[$key] = array(
						'name' => $srvName,
						'class_on' => 'label-on',
						'class_off' => 'label-off',
					);
				} else {
					$group[$key] = $serverMap[$srvName];
					$group[$key] += array(
						'class_on' => 'label-on',
						'class_off' => 'label-off',
					);
					$status = strtolower($group[$key]['status']);
					if ('halted' == $status) {
						$group[$key]['class_off'] .= ' active';
					} else if ('running' == $status) {
						$group[$key]['class_on'] .= ' active';
					}
				}
			}
		}
		unset($group);

		ob_start();
		include __DIR__ . '/view.phtml';
		$widget['body'] = ob_get_clean();

		$this->data = $widget;
	}
}

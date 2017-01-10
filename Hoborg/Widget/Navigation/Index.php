<?php
namespace Hoborg\Widget\Navigation;

use Hoborg\Dashboard\Widget;

class Index extends Widget {

	public function bootstrap() {
		$config = $this->get('config', array());
		$view = empty($config['view']) ? 'index' : $config['view'];

		$this->data['template'] = file_get_contents(__DIR__ . "/Index/{$view}.mustache");
		$this->data['data'] = $this->getWidgetData();
	}

	public function getWidgetData() {
		$index = array(
			'list' => array(),
			'tags' => array(),
		);

		$configPaths = $this->kernel->getConfigPath();

		foreach ($configPaths as $path) {
			$configs = $this->getConfigs($path);
			foreach ($configs as $config) {
				$index['list'][] = $config;
				if (!empty($config['tags'])) {
					foreach ($config['tags'] as $tagName) {
						// check if tag array exists, and create if needed
						if (empty($index['tags'][$tagName])) {
							$index['tags'][$tagName] = array(
								'name' => $tagName,
								'list' => array(),
							);
						}

						$index['tags'][$tagName]['list'][] = $config;
					}
				}
			}
		}

		// sort by name
		usort($index['list'], function($a, $b) { return strnatcasecmp($a['name'], $b['name']);});
		$index['tags'] = array_values($index['tags']);

		return $index;
	}

	protected function getConfigs($path) {
		$configs = array();

		// open folder and read all config files
		$dir = scandir($path);
		foreach ($dir as $configFile) {
			if (preg_match('/.*\.(json|js)$/', $configFile)) {
				$config = json_decode(file_get_contents("{$path}/{$configFile}"), true);
				if (!empty($config)) {
					$link = preg_replace('/(.*).jso?n?$/', '$1', $configFile);
					$name = empty($config['name']) ? $link : $config['name'];
					$configs[] = array(
						'link' => $link,
						'file' => "{$path}/{$configFile}",
						'fileName' => $configFile,
						'name' => $name,
						'description' => empty($config['description']) ? '' : $config['description'],
						'tags' => empty($config['tags']) ? array(): $config['tags'],
					);
				}
			}
		}

		return $configs;
	}
}

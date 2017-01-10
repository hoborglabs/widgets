<?php
namespace Hoborg\Widget\Graphite\Table;
include_once __DIR__ . '/Graphite.php';

/**
 * conf.graphiteUrl: "http://graphs.company.net/"
 * conf.target: "lorem.ipsum.*"
 */
class Treemap extends \Hoborg\Widget\Graphite\Graphite {

	public function bootstrap() {
		$config = $this->getData('conf', array());

		// main check for data file.
		if (empty($config['target'])) {
			$this->data['body'] = 'Missing or empty data configuration `widget.conf.targets`';
			$this->data['error'] = 'Missing or empty data configuration `widget.conf.targets`';

			return;
		}

		$this->data['data'] = $this->getTreemapData($config['graphiteUrl'], $config['target']);
		$config['width'] = empty($config['width']) ? 600 : $config['width'];
		$config['height'] = empty($config['height']) ? 200 : $config['height'];

		if (!empty($widget['data-only'])) {
			return;
		}

		$tplName = empty($config['view']) ? 'default' : $config['view'];
		ob_start();
		include __DIR__ . "/Treemap/{$tplName}.phtml";
		$this->data['body'] = ob_get_clean();
	}

	protected function getTreemapData($graphiteUrl, $target) {
		$treemap = array('name' => 'bets', 'children' => array());
		$from = '-10min';
		$until = 'now';

		$dataUrl = $graphiteUrl . "/render?from={$from}&until={$until}&target={$target}";
		$data = $this->getJsonData($dataUrl);

		$min = $max  = 0;
		foreach ($data as $targetData) {
			$size = 0;
			$name = substr( $targetData['target'], 18);
			$name = str_replace('_', ' ', $name);
			foreach ($targetData['datapoints'] as $p) {
				if (empty($p[0])) {
					continue;
				}
				$size += $p[0];
			}
			$size = $size / count($targetData['datapoints']);

			if ($size > 0) {
				$count = ceil($size);
				$size = max(10, $size * 10);
				$min = min($size, $min);
				$max = max($max, $size);
				$treemap['children'][] = array('name' => $name, 'size' => $size, 'count' => $count);
			}
		}

		foreach ($treemap['children'] as & $child) {
			$child['fontSize'] = 14 + (10 * ($child['size'] - $min)/($max - $min) );
		}

		return $treemap;
	}

}

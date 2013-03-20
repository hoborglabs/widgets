<?php
namespace Hoborg\Widget\Graphite\Table;
include_once __DIR__ . '/../Graphite.php';

/**
 * conf.graphiteUrl: "http://graphs.company.net/"
 * conf.predictions: [
 *   {name: "target 1", target: "", prediction: "20"}
 *   {name: "target 2", target: "", prediction: "123"}
 *   {name: "target 3", target: "", prediction: "6"}
 * ]
 */
class Predictions extends \Hoborg\Widget\Graphite\Graphite {

	public function bootstrap() {
		$data = array('errors' => array());
		$conf = $this->getData('conf', array());
		$tplName = empty($conf['template']) ? 'predictions' : $conf['template'];

		if (empty($conf['predictions'])) {
			$data['errors'][] = 'missing conf.predictions array';
			$conf['predictions'] = array();
		}

		if (empty($conf['graphiteUrl'])) {
			$data['errors'][] = 'missing conf.graphiteUrl';
			return;
		}

		$data['predictions'] = $this->getPredictions($conf);

		$this->data['template'] = file_get_contents(__DIR__ . "/{$tplName}.mustache");
		$this->data['data'] = $data;
	}

	protected function getPredictions(array $conf) {
		$predictions = array();

		foreach ($conf['predictions'] as $prediction) {
			$current = $this->getAvgTargetValue($prediction['target'], $conf['graphiteUrl']);

			$predictions[] = array(
				'name' => $prediction['name'],
				'current' => round($current),
				'prediction' => $prediction['prediction'],
				'p' => round(100 * $current / $prediction['prediction']),
			);
		}

		return $predictions;
	}
}

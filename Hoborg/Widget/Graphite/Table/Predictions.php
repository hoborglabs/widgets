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
		$data['id'] = $conf['id'];
		$conf['width'] = empty($conf['width']) ? 500 : $conf['width'];
		$conf['height'] = empty($conf['height']) ? 200 : $conf['height'];

		$this->data['template'] = file_get_contents(__DIR__ . "/{$tplName}.mustache");
		$this->data['data'] = $data;
	}

	protected function getPredictions(array $conf) {
		$predictions = array();
		$targets = array();
		$predictionsMap = array();

		// get all targets
		foreach ($conf['predictions'] as $prediction) {
			$targets[] = $prediction['target'];
			$predictionsMap[md5($prediction['target'])] = $prediction;
		}

		$data = $this->getTargetsStatisticalData($conf['graphiteUrl'], $targets);

		foreach ($data as $index => $target) {
			$prediction = $conf['predictions'][$index];

			$predictions[] = array(
				'name' => $prediction['name'],
				'current' => round($target['avg']),
				'max' => round($target['max']),
				'prediction' => $prediction['prediction'],
				'p' => round(100 * $target['avg'] / $prediction['prediction']),
			);
		}

		return $predictions;
	}
}

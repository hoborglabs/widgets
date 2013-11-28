<?php
namespace Hoborg\Widget\Graphite;

class Graphite extends \Hoborg\Dashboard\Widget {

	protected function getAvgTargetValue($target, $graphiteUrl, $from = '1min') {
		$url = $graphiteUrl . "/render?target={$target}&from=-{$from}";
		$data = $this->getJsonData($url);
		$avg = array();

		foreach ($data[0]['datapoints'] as $p) {
			$s = $p[0];
			if (null === $s) {
				continue;
			}
			$avg[] = $s;
		}

		return array_sum($avg) / count($avg);
	}

	protected function getJsonData($url) {
		$jsonData = file_get_contents($url . '&format=json');
		$data = json_decode($jsonData, true);

		if (empty($data)) {
			return array();
		}

		return $data;
	}

}
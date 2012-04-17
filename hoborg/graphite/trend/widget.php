<?php

class GraphiteTrendWidget extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$widget = $this->data;

		// main check for data file.
		if (empty($widget['conf']['targets'])) {
			$widget['body'] = 'Missing or empty data configuration `widget.conf.targets`';
			$widget['error'] = 'Missing or empty data configuration `widget.conf.targets`';

			$this->data = $widget;
			return;
		}

		$targetStats = array();
		foreach ($widget['conf']['targets'] as $targetConf) {
		    $targetStats[] = $this->processTarget($targetConf);
		}

		ob_start();
		include __DIR__ . '/view.phtml';
		$widget['body'] = ob_get_clean();

		$this->data = $widget;
	}

	protected function processTarget(array $targetConf) {
	    $widget = $this->data;
	    $target = $targetConf['target'];
	    $factor = empty($targetConf['factor']) ? 1 : $targetConf['factor'];
	    $from = '-10min';
	    $until = 'now';
	    $graphiteUrl = 'http://graphs.skybet.net';

	    $imageUrl = $graphiteUrl . "/render?from=-30min&until={$until}&target={$target}&width=100&height=40&bgcolor=282828&hideLegend=true&hideAxes=true&margin=0";
	    $dataUrl = $graphiteUrl . "/render?from={$from}&until={$until}&target={$target}&format=json";

	    $jsonData = file_get_contents($dataUrl);
	    if (empty($jsonData)) {
	        $widget['body'] = 'no data';
	        $widget['error'] = 'no data';

	        $this->data = $widget;
	        return;
	    }

	    $data = json_decode($jsonData, true);
	    $datapoints = $data[0]['datapoints'];

	    $stats = array();
	    $first = array_shift($datapoints);
	    $min = $max = $sum = $prev = $first[0];
	    $stats[] = $min;
	    $delta = 0;
	    foreach ($datapoints as $datapoint) {
	        $s = $datapoint[0];
	        if (null === $s) {
	            continue;
	        }
	        $delta += ($s - $prev);
	        $min = min($min, $s);
	        $max = max($max, $s);
	        $sum += $s;

	        $stats[] = $s;
	        $prev = $s;
	    }
	    $avg = round($sum / (count($stats) * $factor));
	    $delta = round($delta / $factor);

	    $class = ($delta > 0) ? 'positive' : 'negative';
	    if ($delta == 0) {
	        $class = 'neutral';
	    }
	    $class = "text-S icon {$class}";

	    $targetConf += array(
	            'name' => $targetConf['label'],
	            'avg' => $avg,
	            'delta' => empty($delta) ? '' : $delta,
	            'min' => $min,
	            'max' => $max,
	            'class' => $class,
	            'img' => $imageUrl,
	    );

	    return $targetConf;
	}
}

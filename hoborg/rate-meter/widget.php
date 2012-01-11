<?php

/**
* This is a general widget for displaying number and trends.
*/
class RateMeterWidget extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$widget = $this->data;

		if (empty($widget['conf']['data'])) {
			$widget['body'] = 'no conf';
			$widget['error'] = "Data file not configured: widget.conf.data";
			return $widget;
		}

		$dataFile = $this->kernel->findFileOnPath(
		$widget['conf']['data'],
		$this->kernel->getDataPath()
		);

		if (!is_readable($dataFile)) {
			$widget['body'] = "$dataFile not readable";
			$widget['error'] = "Data file ({$dataFile}) not readable";
			return $widget;
		}

		$data = json_decode(file_get_contents($dataFile));

		$lastValue = array_shift($data);
		$previousValue = array_shift($data);
		$delta = number_format(($lastValue - $previousValue) / $lastValue, 2);
		$lastValue = number_format($lastValue, 2);
		$class = $delta >= 0 ? 'positive' : 'negative';

		ob_start();
		include __DIR__ . '/view.phtml';
		$widget['body'] = ob_get_clean();

		$this->data = $widget;
	}
}

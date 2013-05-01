<?php
namespace Hoborg\Graphite\Graph;

class Panel extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$widget = $this->data;
		$conf = $widget['conf'];

		// main check for data file.
		if (empty($conf['targets'])) {
			$widget['body'] = 'Missing or empty data configuration `widget.conf.targets`';
			$widget['error'] = 'Missing or empty data configuration `widget.conf.targets`';

			$this->data = $widget;
			return;
		}

		$tplName = empty($conf['view']) ? 'panel' : $conf['view'];
		$height = empty($conf['height']) ? 180 : round($conf['height']);

		$data = array(
			'graph' => $this->getGraphUrl($conf),
			'height' => $height,
		);

		$widget['template'] = file_get_contents(__DIR__ . "/{$tplName}.mustache");
		$widget['data'] = $data;

		$this->data = $widget;
	}

	protected function getGraphUrl(array $conf) {
		$graphiteUrl = $conf['graphiteUrl'];
		$from = empty($conf['from']) ? '30min' : $conf['from'];
		$until = empty($conf['until']) ? 'now' : $conf['until'];
		$height = empty($conf['height']) ? 180 : round($conf['height']);
		$width = empty($conf['width']) ? 390 : round($conf['width']);

		$imageUrl = $graphiteUrl . "/render?from=-{$from}&until={$until}&width={$width}&height={$height}"
				. "&bgcolor=282828&hideLegend=true&hideAxes=false&margin=5";

		foreach ($conf['targets'] as $target) {
			foreach ($target as $func => $val) {
				if (in_array($func, array('color'))) {
					if ('color' == $func) {
						$val = preg_replace('/#?(.*)/', '$1', $val);
					}
					$target['target'] = "{$func}({$target['target']}%2C'{$val}')";
				} else if (in_array($func, array('scale', 'movingAverage', 'highestAverage', 'lineWidth'))) {
					$target['target'] = "{$func}({$target['target']}%2C{$val})";
				} else if (in_array($func, array('drawAsInfinite', 'stacked'))) {
					// no params functions
					if (!empty($val)) {
						$target['target'] = "{$func}({$target['target']})";
					}
				}
			}
			$imageUrl .= "&target={$target['target']}";
		}

		foreach ($conf['options'] as $opt => $val) {
			if (in_array($opt, array('yMin', 'drawNullAsZero', 'areaMode', 'fontSize'))) {
				$imageUrl .= "&{$opt}={$val}";
			}
		}

		return $imageUrl;
	}
}

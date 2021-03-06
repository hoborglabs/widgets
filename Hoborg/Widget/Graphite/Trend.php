<?php

class GraphiteTrendWidget extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$widget = $this->data;
		$config = $this->get('config', array());

		// main check for data file.
		if (empty($config['targets'])) {
			$this->data['error'] = $this->data['body'] = 'Missing or empty data configuration `widget.config.targets`';

			return;
		}

		$tplName = empty($config['view']) ? 'view' : $config['view'];

		// 07.05.2014 - breaking change
		if (in_array($tplName, array('horizontal-fourcol', 'horizontal-threecol', 'horizontal-twocol'))) {
			$this->data['error']  = $this->data['body'] =
					"Please use 'horizontal' instead of '{$tplName}' for `config.view`. You can set columns number with
					config.view_options.columns: \"five-col\"";

			return;
		}

		$targetStats = array();
		$this->graphiteUrl = $config['url'];
		foreach ($config['targets'] as $targetConf) {
			if (0 === strpos($tplName, 'horizontal')) {
				$targetConf['image']['height'] = 36;
			}

			$p = $this->processTarget($this->prepareTargetConfig($targetConf));
			if (null !== $p) {
				$targetStats[] = $p;
			}
		}

		$this->data['template'] = file_get_contents(__DIR__ . "/Trend/{$tplName}.html");
		$this->data['data'] = array(
			'stats' => $targetStats,
		);
	}

	protected function prepareTargetConfig(array $target) {
		$cfg = $this->get('config', array());

		// top level defaults
		$defaultTarget = empty($cfg['defaults']['target']) ? array() : $cfg['defaults']['target'];
		$target = $target + $defaultTarget;

		// image defaults
		$defaultImage = empty($cfg['defaults']['image']) ? array() : $cfg['defaults']['image'];
		$target['image'] = $target['image'] + $defaultImage;

		return $target;
	}

	protected function processTarget(array $targetConf) {
		$widget = $this->data;
		$target = $targetConf['target'];
		$factor = empty($targetConf['factor']) ? 1 : $targetConf['factor'];
		$from = empty($targetConf['from']) ? '-10min' : $targetConf['from'];
		$imgFrom = empty($targetConf['image']['from']) ? '60min' : $targetConf['image']['from'];
		$imgWidth = empty($targetConf['image']['width']) ? '100' : $targetConf['image']['width'];
		$imgHeight = empty($targetConf['image']['height']) ? '56' : $targetConf['image']['height'];
		$alertOnHoltWinters = isset($targetConf['alertOnHoltWinters']) ? $targetConf['alertOnHoltWinters'] : -1;
		$decimals = empty($targetConf['decimals']) ? 0 : $targetConf['decimals'];

		$hideBellow = !isset($targetConf['hideBellow']) ? false : $targetConf['hideBellow'];

		$until = 'now';
		$bgcolor = '282828';

		$dataUrl = $this->graphiteUrl . "/render?from={$from}&until={$until}&target={$target}&format=json";

		if ($alertOnHoltWinters > -1) {
			$alertDataUrl = $this->graphiteUrl . "/render?from=-60s&until={$until}"
					. "&target=holtWintersAberration(keepLastValue({$target}))&format=json";

			// check if we should alert based on holt winters
			$jsonData = file_get_contents($alertDataUrl);
			$alerts = json_decode($jsonData, true);
			$alerts = $alerts[0]['datapoints'];
			$alertsAvg = array(
				'min' => array(),
				'max' => array()
			);
			// get the avg
			foreach ($alerts as $datapoint) {
				$s = $datapoint[0];
				if (null === $s || 0 == $s) {
					continue;
				}
				if ($s > 0) {
					$alertsAvg['max'][] = $s;
				} else {
					$alertsAvg['min'][] = $s;
				}
			}
			$alertsAvg = array(
				'min' => empty($alertsAvg['min']) ? 0 : array_sum($alertsAvg['min']) / count($alertsAvg['min']),
				'max' => empty($alertsAvg['max']) ? 0 : array_sum($alertsAvg['max']) / count($alertsAvg['max']),
			);
		} else {
			$alertsAvg = array(
				'min' => 0,
				'max' => 0,
			);
		}

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
		$min = PHP_INT_MAX;
		$max = ~PHP_INT_MAX;
		$sum = 0;
		$prev = null;
		$delta = 0;
		foreach ($datapoints as $datapoint) {
			$s = $datapoint[0];
			if (null === $s) {
				continue;
			}
			if ($prev !== null) {
				$delta += ($s - $prev);
			}
			$min = min($min, $s);
			$max = max($max, $s);
			$sum += $s;

			$stats[] = $s;
			$prev = $s;
		}
		$avg = $sum / count($stats);
		$delta = round($delta / $factor);

		$class = ($delta > 0) ? 'positive' : 'negative';
		if ($delta == 0) {
			$class = 'neutral';
		}
		$class = "text-S icon {$class}";

		$coldColor = empty($targetConf['colors']['cold']['color']) ? '070707' : $targetConf['colors']['cold']['color'];
		$hotColor = empty($targetConf['colors']['hot']['color']) ? 'FF0000' : $targetConf['colors']['hot']['color'];

		if (isset($targetConf['colors']['range'])) {
			if (2 == count($targetConf['colors']['range'])) {
				list ($rmin, $rmax) = $targetConf['colors']['range'];
				$rmmin = $rmin;
				$rmmax = $rmax;
			} else if (4 == count($targetConf['colors']['range'])) {
				list ($rmmin, $rmin, $rmax, $rmmax) = $targetConf['colors']['range'];
			}

			if ($avg > $rmin) {
				$color = $this->getColor($avg, $rmax, $rmmax, $coldColor, $hotColor);
			} else {
				$color = $this->getColor($avg, $rmmin, $rmin, $hotColor, $coldColor);
			}
		} else {
			$coldValue = empty($targetConf['colors']['cold']['value']) ? 0 : $targetConf['colors']['cold']['value'];
			$hotValue = empty($targetConf['colors']['hot']['value']) ? 100 : $targetConf['colors']['hot']['value'];
			$color = $this->getColor($avg, $coldValue, $hotValue, $coldColor, $hotColor);
		}
		if ($alertOnHoltWinters > -1 && $alertsAvg['min'] < -1 * $alertOnHoltWinters) {
			$color = $hotColor;
		}

		if (false !== $hideBellow) {
			if ($avg <= $hideBellow) {
				return null;
			}
		}

		// backward compatibility
		$imgFrom = preg_replace('/-?(.*)/', '$1', $imgFrom);

		$t = time();
		$imageUrl = $this->graphiteUrl . "/render?from=-{$imgFrom}&until={$until}&width={$imgWidth}&height={$imgHeight}&bgcolor={$bgcolor}&hideLegend=true&hideAxes=true&margin=0&t={$t}&yMin=0";
		if (!empty($targetConf['image']['drawNullAsZero'])) {
			$imageUrl .= "&drawNullAsZero=true";
		}

		$imgTargets = array();
		$trgs = array();
		if (!empty($targetConf['image']['targets'])) {
			$imgTargets = $targetConf['image']['targets'];
		} else {
			$imgTargets[0] = $targetConf['image'];
			$imgTargets[0]['target'] = $target;
		}

		foreach ($imgTargets as $trg) {
			$origTarget = $trg['target'];
			if (empty($trg['color'])) {
				$trg['color'] = $color;
			}
			foreach ($trg as $func => $val) {
				if (in_array($func, array('lineWidth', 'movingAverage'))) {
					$trg['target'] = "{$func}({$trg['target']}%2C{$val})";
				} else if (in_array($func, array('color'))) {
					if ('color' == $func) {
						$val = preg_replace('/#?(.*)/', '$1', $val);
					}
					$trg['target'] = "{$func}({$trg['target']}%2C'{$val}')";
				} else if (in_array($func, array('stacked', 'keepLastValue'))) {
					if (!empty($val)) {
						$trg['target'] = "{$func}({$trg['target']})";
					}
				}
			}

			if (!empty($trg['bands'])) {
				$c = !empty($trg['color']) ? $trg['color'] : '3366FF';
				$bc = '0099ff';// $this->getColor(0, 0, 100, '000000', $c);
				$trg['target'] = "color(movingAverage(holtWintersConfidenceBands(keepLastValue({$origTarget}))%2C10)%2C'{$bc}')&target={$trg['target']}";
			}

			if (!empty($trg['baseline'])) {
				$trg['target'] = "color(constantLine({$trg['baseline']})%2C'{$bgcolor}')&target={$trg['target']}";
			}

			$trgs[] = 'target='.$trg['target'];
		}

		$imageUrl .= '&' . implode('&', $trgs);

		$targetConf += array(
			'name' => $targetConf['label'],
			'avg' => number_format($avg / $factor, $decimals),
			'delta' => empty($delta) ? '' : $delta,
			'min' => round($min / $factor),
			'max' => round($max / $factor),
			'class' => $class,
			'img' => $imageUrl,
			'img-link' => isset($targetConf['image']['link']) ? $targetConf['image']['link'] : '',
			'color' => $color
		);

		return $targetConf;
	}

	protected function getColor($value, $min, $max, $minColor = 'FFFFFF', $maxColor = 'FF0000') {
		$value = min($max, max($min, $value));
		$value = abs($value - $min);
		$range = abs($max - $min);
		$delta = $value / $range;

		// now, lets calculate color on a 3D matrix
		list($ax, $ay, $az) = array(hexdec(substr($minColor, 0, 2)), hexdec(substr($minColor, 2, 2)),
				hexdec(substr($minColor, 4, 2)));
		list($bx, $by, $bz) = array(hexdec(substr($maxColor, 0, 2)), hexdec(substr($maxColor, 2, 2)),
				hexdec(substr($maxColor, 4, 2)));

		$cx = $ax + ($bx - $ax) * $delta;
		$cy = $ay + ($by - $ay) * $delta;
		$cz = $az + ($bz - $az) * $delta;

		return str_pad(dechex($cx), 2, '0') . str_pad(dechex($cy), 2, '0') . str_pad(dechex($cz), 2, '0');
	}
}

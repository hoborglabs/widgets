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

		$imageUrl = $graphiteUrl . "/render?from=-{$from}&until={$until}&width={$width}&height={$height}&bgcolor=282828&hideLegend=true&hideAxes=false&margin=5&fontSize=14";

		foreach ($conf['targets'] as $target) {
			foreach ($target as $func => $val) {
				if (in_array($func, array('color'))) {
					if ('color' == $func) {
						$val = preg_replace('/#?(.*)/', '$1', $val);
					}
					$target['target'] = "{$func}({$target['target']}%2C'{$val}')";
				} else if (in_array($func, array('scale', 'movingAverage', 'highestAverage', 'lineWidth'))) {
					$target['target'] = "{$func}({$target['target']}%2C{$val})";
				}
			}
			$imageUrl .= "&target={$target['target']}";
		}

		return $imageUrl;
	}

	protected function processTarget(array $targetConf) {
	    $widget = $this->data;
	    $target = $targetConf['target'];
	    $factor = empty($targetConf['factor']) ? 1 : $targetConf['factor'];
	    $from = empty($targetConf['from']) ? '-10min' : $targetConf['from'];
		$imgFrom = empty($targetConf['image']['from']) ? '60min' : $targetConf['image']['from'];
		$imgWidth = empty($targetConf['image']['width']) ? '100' : $targetConf['image']['width'];
		$imgHeight = empty($targetConf['image']['height']) ? '56' : $targetConf['image']['height'];
	    $until = 'now';
	    $graphiteUrl = 'http://graphs.skybet.net';

		// backward compatibility
		$imgFrom = preg_replace('/-?(.*)/', '$1', $imgFrom);

	    $t = time();
	    $imageUrl = $graphiteUrl . "/render?from=-{$imgFrom}&until={$until}&width={$imgWidth}&height={$imgHeight}&bgcolor=282828&hideLegend=true&hideAxes=true&margin=0&t={$t}";
		if (!empty($targetConf['image']['color'])) {
			$target = "color({$target}%2C'{$targetConf['image']['color']}')";
		}
		if (!empty($targetConf['image']['bands'])) {
			$imageUrl .= "&target=color(movingAverage(holtWintersConfidenceBands({$target})%2C10)%2C'B5AB81')&target=lineWidth(color({$target}%2C'3366FF')%2C2)";
		} else {
			$imageUrl .= "&target={$target}";
		}
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

	    $coldColor = empty($targetConf['colors']['cold']['color']) ? 'FFFFFF' : $targetConf['colors']['cold']['color'];
	    $coldValue = empty($targetConf['colors']['cold']['value']) ? 0 : $targetConf['colors']['cold']['value'];
	    $hotColor = empty($targetConf['colors']['hot']['color']) ? 'FF0000' : $targetConf['colors']['hot']['color'];
	    $hotValue = empty($targetConf['colors']['hot']['value']) ? 100 : $targetConf['colors']['hot']['value'];

	    $targetConf += array(
	            'name' => $targetConf['label'],
	            'avg' => $avg,
	            'delta' => empty($delta) ? '' : $delta,
	            'min' => round($min / $factor),
	            'max' => round($max / $factor),
	            'class' => $class,
	            'img' => $imageUrl,
	            'img-link' => isset($targetConf['image']['link']) ? $targetConf['image']['link'] : '',
	    		'color' => $this->getColor($avg, $coldValue, $hotValue, $coldColor, $hotColor)
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

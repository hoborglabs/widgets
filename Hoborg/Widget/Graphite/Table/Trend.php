<?php
namespace Hoborg\Widget\Graphite\Table;

include_once __DIR__ . '/../Graphite.php';

use Hoborg\Widget\Graphite\Graphite;

/**
 * conf.graphiteUrl: "http://graphs.company.net/"
 * conf.defaultTarget: {
 *   "from": "-2min",
 *   "image": {
 *     "drawNullAsZero": 1,
 *     "from": "60min",
 *     "movingAverage": 6,
 *     "lineWidth": 2,
 *     "baseline": 5,
 *     "width": 100
 *   },
 * }
 * conf.columns: [
 *   {
 *     "name": "CODE ERRORS"
 *   },
 *   {
 *     "name": "CODE WARNINGS"
 *   },
 *   {
 *     "name": "ERROR PAGES"
 *     "defaults": {
 *       "image": {
 *         "drawNullAsZero": 1,
 *         "from": "60min",
 *         "movingAverage": 6,
 *         "lineWidth": 2,
 *         "baseline": 5,
 *         "width": 100
 *       }
 *     }
 *   }
 * ]
 * conf.rows: [
 *   [
 *     // First element is alway label - it will be availabe in data.rows{}.label
 *     {
 *       "name": "www.skybet.com"
 *     },
 *     // all other elements will be available in data.rows{}.trends
 *     {
 *       "target": "lamp.sites.bet.logs.ERR",
  *       "colors": {
 *         "range": [-1, 0, 5, 10]
 *       }
 *     },
 *     {
 *       "target": "lamp.sites.bet.logs.WARN",
  *       "colors": {
 *         "range": [-1, 0, 5, 10]
 *       }
 *     },
 *     {}
 *   ],
 *   [
 *   ]
 * ]
 */
class Trend extends Graphite {

	public function bootstrap() {
		$config = $this->getData('config', array());
		$data = array(
			'columns' => array(),
			'rows' => array(),
		);
		$tplName = empty($config['view']) ? 'table' : $config['view'];

		// get columns data
		$data['columns'] = $this->getColumns($config['columns']);

		// get normalization data (optional)
		$normalizations = $this->getRowsNormalization($config['rows']);

		// get rows data
		$columnDefaults = $this->getColumnDefaults($config['columns']);
		$data['rows'] = $this->getRows($config['rows'], $columnDefaults);

		foreach ($normalizations as $index => $norm) {
			if (empty($norm)) {
				continue;
			}
			$data['rows'][$index]['label']['normalization'] = $norm;
		}

		$this->data['template'] = file_get_contents(__DIR__ . "/{$tplName}.mustache");
		$this->data['data'] = $data;
	}

	/**
	 * Returns columns view data.
	 * 
	 * The following fields will be removed (reserved)
	 * '_defaults'
	 * 
	 * @param array $columnsConfig
	 */
	protected function getColumns(array $columnsConfig) {
		$columns = array();

		foreach ($columnsConfig as $column) {
			unset($column['_defaults']);
			$columns[] = $column;
		}

		return $columns;
	}

	protected function getRowsNormalization(array $rows) {
		$normalizations = array();
		$targets = array();
		$config = $this->getData('config', array());

		foreach ($rows as $row) {
			$label = array_shift($row);
			if (empty($label['_normalization'])) {
				$normalizations[] = null;
				continue;
			}
			
			$norm = $label['_normalization'];
			$targets[] = $norm['target'];
			$normalizations[] = array(
				'range' => $norm['range']
			);
		}
		$data = $this->getTargetsStatisticalData($config['graphiteUrl'], $targets, '-3min', 'now');

		foreach ($data as $index => $target) {
			$range = $normalizations[$index]['range'];
			$rangeSize = $range[3] - $range[0];
			$normalizations[$index]['bar'] = array(
				'min' => 100 * ($range[1]/$rangeSize),
				'max' => 100 * ($range[2]/$rangeSize),
				'avg' => 100 * ($target['avg']/$rangeSize),
				'cmax' => 100 * ($target['max']/$rangeSize),
			);
		}

		return $normalizations;
	}

	protected function getColumnDefaults(array $columnsConfig) {
		$defautls = array();

		foreach ($columnsConfig as $column) {
			if (!empty($column['_defaults'])) {
				$defautls[] = $column['_defaults'];
			} else {
				$defautls[] = array();
			}
		}
		
		return $defautls;
	}
	
	/**
	 * 
	 * @param array $rowsConfig
	 * @param array $default
	 */
	protected function getRows(array $rowsConfig, array $default) {
		if (empty($rowsConfig)) {
			return array();
		}

		$columnTargets = $newRow = $rows = array();

		// create empty arrays for each column
		for ($i = 1; $i < count($rowsConfig[0]); $i++) {
			$columnTargets[] = array();
		}

		// * get targets for each columns - we will make single graphite call for each column
		// * get row label object
		foreach ($rowsConfig as $row) {
			$newRow = $newColumns = array();

			$newRow['label'] = array_shift($row);
			$newRow['trends'] = array();

			foreach ($row as $index => $target) {
				$columnTargets[$index][] = $target + $default[$index];
			}

			$rows[] = $newRow;
		}

		foreach ($columnTargets as $colIndex => $targets) {
			$data = $this->processColumnTargets($targets);
			foreach ($data as $rowIndex => $trend) {
				$trend['avg-disp'] = ($trend['avg'] >=10) ? round($trend['avg']) : number_format($trend['avg'], 1);
				$trend['max-disp'] = number_format($trend['max'], 1);
				$trend['min-disp'] = number_format($trend['min'], 1);

				$trend['img'] = $this->getImageData($targets[$rowIndex], $trend);

				$rows[$rowIndex]['trends'][$colIndex] = $trend;
			}
		}

		return $rows;
	}

	protected function processColumnTargets(array $columnTargets) {
		$config = $this->getData('config', array());
		$data = array();

		// common params
		$from = '-2min';
		$until = 'now';

		// get data from graphite
		$data = $this->getTargetsStatisticalData($config['graphiteUrl'], 
				array_map(function($t) { return $t['target']; }, $columnTargets),
				$from, $until);

		return $data;
	}
	
	protected function getImageData(array $targetConfig, array $targetData) {
		$config = $this->getData('config', array());
		$img = array(
			'src' => '',
			'width' => $targetConfig['image']['width'],
			'height' => $targetConfig['image']['height']
		);
		$until = 'now';
		$bgcolor = '282828';
		$imgFrom = empty($targetConfig['image']['from']) ? '60min' : $targetConfig['image']['from'];
		$imgWidth = empty($targetConfig['image']['width']) ? '100' : $targetConfig['image']['width'];
		$imgHeight = empty($targetConfig['image']['height']) ? '56' : $targetConfig['image']['height'];
		$coldColor = empty($targetConfig['colors']['cold']['color']) ? '070707' : $targetConfig['colors']['cold']['color'];
		$hotColor = empty($targetConfig['colors']['hot']['color']) ? 'FF0000' : $targetConfig['colors']['hot']['color'];

		$imageUrl = $config['graphiteUrl'] . "/render?from=-{$imgFrom}&until={$until}&width={$imgWidth}&height={$imgHeight}&bgcolor={$bgcolor}&hideLegend=true&hideAxes=true&margin=0&yMin=0";
		if (!empty($targetConfig['image']['drawNullAsZero'])) {
			$imageUrl .= "&drawNullAsZero=true";
		}

		if (isset($targetConfig['colors']['range'])) {
			if (2 == count($targetConfig['colors']['range'])) {
				list ($rmin, $rmax) = $targetConfig['colors']['range'];
				$rmmin = $rmin;
				$rmmax = $rmax;
			} else if (4 == count($targetConfig['colors']['range'])) {
				list ($rmmin, $rmin, $rmax, $rmmax) = $targetConfig['colors']['range'];
			}

			if ($targetData['avg'] > $rmin) {
				$color = $this->getColor($targetData['avg'], $rmax, $rmmax, $coldColor, $hotColor);
			} else {
				$color = $this->getColor($targetData['avg'], $rmmin, $rmin, $hotColor, $coldColor);
			}

			if (empty($targetConfig['image']['color'])) {
				$targetConfig['image']['color'] = $color;
				$img['color'] = $color;
			}
		}

		$origTarget = $targetConfig['target'];
		foreach ($targetConfig['image'] as $func => $val) {
			if (in_array($func, array('lineWidth', 'movingAverage'))) {
				$targetConfig['target'] = "{$func}({$targetConfig['target']}%2C{$val})";
			} else if (in_array($func, array('color'))) {
				if ('color' == $func) {
					$val = preg_replace('/#?(.*)/', '$1', $val);
				}
				$targetConfig['target'] = "{$func}({$targetConfig['target']}%2C'{$val}')";
			} else if (in_array($func, array('stacked'))) {
				if (!empty($val)) {
					$targetConfig['target'] = "{$func}({$targetConfig['target']})";
				}
			}
		
		}
		
		if (!empty($targetConfig['image']['bands'])) {
			$c = !empty($targetConfig['image']['color']) ? $targetConfig['image']['color'] : '3366FF';
			$bc = '0099ff';// $this->getColor(0, 0, 100, '000000', $c);
			$targetConfig['target'] = "color(movingAverage(holtWintersConfidenceBands(keepLastValue({$origTarget}))%2C10)%2C'{$bc}')&target={$targetConfig['target']}";
		}
		
		if (!empty($targetConfig['image']['baseline'])) {
			$targetConfig['target'] = "color(constantLine({$targetConfig['image']['baseline']})%2C'{$bgcolor}')&target={$targetConfig['target']}";
		}

		$imageUrl .= '&target=' . $targetConfig['target'];

		$img['src'] = $imageUrl;

		return $img;
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
		$graphiteUrl = 'http://graphs.skybet.net';

		$dataUrl = $graphiteUrl . "/render?from={$from}&until={$until}&target={$target}&format=json";

		if ($alertOnHoltWinters > -1) {
			$alertDataUrl = $graphiteUrl . "/render?from=-60s&until={$until}"
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
		$imageUrl = $graphiteUrl . "/render?from=-{$imgFrom}&until={$until}&width={$imgWidth}&height={$imgHeight}&bgcolor={$bgcolor}&hideLegend=true&hideAxes=true&margin=0&t={$t}&yMin=0";
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
				} else if (in_array($func, array('stacked'))) {
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

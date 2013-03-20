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

	/**
	 * Returns hex color code for given value.
	 * 
	 * @param int $value
	 * @param int $min
	 * @param int $max
	 * @param string $minColor min hex color code
	 * @param string $maxColor max hex color code
	 */
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
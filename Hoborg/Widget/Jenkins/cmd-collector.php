<?php
namespace Hoborg\Dashboard\Widget\Jenkins;

class Collector {

	public function execute($params) {
		$defaults = array(
			'url' => null,
			'out' => null
		);
		$options = $params + $defaults;
		if (empty($options['url']) || empty($options['out'])) {
			exit("please specify Jenkins URL `url` and Output File `out`\n");
		}

		$tree = array(
			'jobs' => array(
				'name',
				'id',
				'color',
				'inQueue',
				'lastBuild' => array(
					'number',
					'timestamp',
					'result',
					'url',
					'building',
				),
				'healthReport' => array('score', 'description'),
			)
		);
		$url = $options['url'] . '/api/json?tree=';
		$url .= urlencode($this->get_tree_value($tree));

		// get data from url
		$data = file_get_contents($url);
		if (empty($data)) {
			die('no Data returned from ' . $url);
		}
		file_put_contents($options['out'], $data);
	}

	public function get_tree_value(array $tree) {
		$value = '';

		foreach ($tree as $key => $val) {
			if (is_array($val)) {
				$value .= ','.$key.'['. $this->get_tree_value($val) . ']';
			} else {
				$value .= ','.$val;
			}
		}

		return substr($value, 1);
	}
}
<?php
namespace Hoborg\Widget\Jenkins;

use Hoborg\Dashboard\Client\Jenkins;

class Cobertura extends \Hoborg\Dashboard\Widget  {

	public function bootstrap() {
		// get and mustashify data - Copy hash tables to arrays
		$codeCoverage = $this->getCodeCoverage();
		$codeCoverage['modules'] = array_values($codeCoverage['modules']);
		foreach ($codeCoverage['modules'] as &$module ) {
			$module['elements'] = array_values($module['elements']);
		} unset($module);

		$cfg = $this->get('config', array());
		$view = empty($cfg['view']) ? 'default' : $cfg['view'];

		switch ($view) {
			case 'shame':
				$this->data['data'] = $this->getShameData($codeCoverage);
				break;

			default:
				$this->data['data'] = $codeCoverage;
		}

		$this->data['template'] = file_get_contents(__DIR__ . "/Cobertura/{$view}.tache");
	}

	protected function getShameData(array $codeCoverage) {
		$cfg = $this->get('config', array());
		$shameLimit = empty($cfg['shameLimit']) ? 100 : $cfg['shameLimit'];

		$shameData = array(
			'build' => $codeCoverage['build'],
			'graph' => array(),
			'shame' => array(),
		);
		$graph = array();
		$modules = array();

		// create initial graph data
		$firstModule = reset($codeCoverage['modules']);
		foreach ($firstModule['elements'] as $el) {
			$graph[$el['name']] = array(
				'label' => $el['name'],
				'points' => array()
			);
			foreach ($el['ratios'] as $ratio) {
				$graph[$el['name']]['points'][$ratio['build']] = array(
					'build' => $ratio['build'],
					'ratioMin' => $ratio['ratio'],
					'ratioMax' => $ratio['ratio']
				);
			}
		}

		// get list o "shame" modules
		foreach ($codeCoverage['modules'] as $module) {

			foreach ($module['elements'] as $el) {
				foreach ($el['ratios'] as $ratio) {
					$graph[$el['name']]['points'][$ratio['build']] = array(
						'build' => $ratio['build'],
						'ratioMin' => min($ratio['ratio'], $graph[$el['name']]['points'][$ratio['build']]['ratioMin']),
						'ratioMax' => max($ratio['ratio'], $graph[$el['name']]['points'][$ratio['build']]['ratioMax']),
					);
				}
			}

			$score = round(array_reduce(
				$module['elements'],
				function($result, $item) { return $result + $item['ratio']; },
				0
			) / count($module['elements']));

			if ($score >= $shameLimit) {
				continue;
			}

			$modules[] = array(
				'name' => $module['name'],
				'score' => $score,
				'elements' => $module['elements'],
			);
		}

		foreach ($codeCoverage['elements'] as $buildEl) {
			foreach ($buildEl['elements'] as $el) {
				$graph[$el['name']]['points'][$buildEl['build']]['ratio'] = $el['ratio'];
			}
		}

		// make arrays for view
		$graph = array_values($graph);
		foreach ($graph as &$el) {
			$el['points'] = array_values($el['points']);
		} unset($el);

		// get 2 "top" modules
		usort($modules, function($a, $b) {return $a['score'] - $b['score']; });
		$shameData['shame'] = array_slice($modules, 0, 2);
		// this needs to go to data attribute and be parsed by JS
		$shameData['graph'] = json_encode($graph);
		$shameData['graph-raw'] = $graph;

		return $shameData;
	}

	public function populateCache() {
		$data = $this->getCodeCoverage();
		$dataFile = $this->get('static', 'cobertura.json');

		// find file
		$file = $this->kernel->findFileOnPath($dataFile, $this->kernel->getDataPath());
		if (empty($file)) {
			$file = reset($this->kernel->getDataPath()) . '/' . $dataFile;
		}
		error_log(__METHOD__ . ' data file: ' . $file);

// 		file_put_contents($file, json_encode($data));

		return $data;
	}

	protected function getCodeCoverage() {
		$cfg = $this->get('config', array());
		$jenkinsClient = new Jenkins($cfg['url']);
		$codeCoverage = array(
			'modules' => array(),
			'elements' => array(),
			'build' => ''
		);

		// get builds
		$data = $jenkinsClient->get(array(
			'builds' => array(
				'building',
				'number',
				'result',
				'url',
			)
		));

		// get builds and set some sensible limit
		$builds = $data['builds'];
		$buildsLimit = min(empty($cfg['limit']) ? 10 : $cfg['limit'], count($builds));

		// copy current build number
		$codeCoverage['build'] = $builds[0]['number'];

		for ($i = 0; $i < $buildsLimit; $i++) {
			$build = $builds[$i];

			// get data from Jenkins/Hudson
			$buildInfo = $jenkinsClient->get(array(
				'results' => array(
					'children' => array(
						'children',
						'name',
						'elements' => array('name','ratio')
					),
					'elements' => array('name','ratio')
				)
			), $build['number'] . '/cobertura');

			$coberturaData = $this->processCoberturaData($buildInfo);

			$codeCoverage['elements'][] = array(
				'build' => $build['number'],
				'elements' => $coberturaData['elements']
			);

			// if that's the first build, let's set up initial structure
			if (empty($codeCoverage['modules'])) {
				$codeCoverage['modules'] = $coberturaData['modules'];
				foreach ($codeCoverage['modules'] as &$mod) {
					foreach ($mod['elements'] as &$el) {
						$el['ratios'] = array(
							array('build' => $build['number'], 'ratio' => $el['ratio'])
						);
					} unset($el);
				} unset($mod);
			}
			// if that's next build, let's just update ratios history
			else {
				foreach ($codeCoverage['modules'] as &$mod) {
					// if unrecognized module - skip and log error
					if (empty($coberturaData['modules'][$mod['name']])) {
						error_log(__METHOD__ . ' Cobertura data missing for module ' . $mod['name']);
						continue;
					}
					// update 'ratios' array for all recognized modules
					foreach ($mod['elements'] as &$el) {
						$el['ratios'][] = array(
							'build' => $build['number'],
							'ratio' => $coberturaData['modules'][$mod['name']]['elements'][$el['name']]['ratio']
						);
					} unset($el);
				} unset($mod);
			}
		}

		return $codeCoverage;
	}

	protected function processCoberturaData(array $coberturaData) {
		$return = array(
			'modules' => array(),
			'elements' => $coberturaData['results']['elements'],
		);

		foreach ($coberturaData['results']['children'] as $mod) {
			$modData = array(
				'name' => $mod['name'],
				'elements' => array(),
			);

			foreach ($mod['elements'] as $el) {
				$elData = array(
					'name' => $el['name'],
					'ratio' => round($el['ratio'])
				);
				$modData['elements'][$el['name']] = $elData;
			}
			$return['modules'][$mod['name']] = $modData;
		}

		return $return;
	}
}
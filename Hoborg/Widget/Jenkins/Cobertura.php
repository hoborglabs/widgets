<?php
namespace Hoborg\Widget\Jenkins;

use Hoborg\Dashboard\Client\Jenkins;

class Cobertura extends \Hoborg\Dashboard\Widget  {

	public function bootstrap() {
		$this->data['data'] = $this->getCodeCoverage();
		$this->data['template'] = file_get_contents(__DIR__ . '/Cobertura/default.tache');
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
		$buildsLimit = min($cfg['limit']?:3, count($builds));

		// copy current build number
		$codeCoverage['build'] = $builds[0]['number'];

		for ($i = 0; $i < $buildsLimit; $i++) {
			$build = $builds[$i];

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

			// if that's the first build, let's set up initial structure
			if (empty($codeCoverage['modules'])) {
				$codeCoverage['modules'] = $coberturaData;
				foreach ($codeCoverage['modules'] as &$mod) {
					foreach ($mod['elements'] as &$el) {
						$el['ratios'] = array(
							array('build' => $build['number'], 'ratio' => $el['ratio'])
						);
					}
				}
			}
			// if that's next build, let's just update ratios history
			else {
				foreach ($codeCoverage['modules'] as &$mod) {
					// if unrecognized module - skip and log error
					if (empty($coberturaData[$mod['name']])) {
						error_log(__METHOD__ . ' Cobertura data missing for module ' . $mod['name']);
						continue;
					}
					// update 'ratios' array for all recognized modules
					foreach ($mod['elements'] as &$el) {
						$el['ratios'][] = array(
							'build' => $build['number'],
							'ratio' => $coberturaData[$mod['name']]['elements'][$el['name']]['ratio']
						);
					}
				}
			}
		}

		return $codeCoverage;
	}

	protected function processCoberturaData(array $coberturaData) {
		$return = array();

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
			$return[$mod['name']] = $modData;
		}

		return $return;
	}
}
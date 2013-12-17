<?php
namespace Hoborg\Widget\Jenkins;

use Hoborg\Dashboard\Client\Jenkins;

class Cobertura extends \Hoborg\Dashboard\Widget  {

	public function bootstrap() {
		$cfg = $this->get('config', array());
		$view = empty($cfg['view']) ? 'default' : $cfg['view'];

		// get code coverage data
		$codeCoverage = $this->getCodeCoverage();

		switch ($view) {
			case 'shame':
				$shame = $this->getShameData($codeCoverage);
				$this->data['data'] = array(
					'jobs' => $codeCoverage['jobs'],
					'shame' => $shame['jobs'],
				);
				break;

			default:
				$this->data['data'] = $codeCoverage;
		}

		$this->data['template'] = file_get_contents(__DIR__ . "/Cobertura/{$view}.tache");
	}

	protected function getShameData(array $codeCoverage) {
		$cfg = $this->get('config', array());
		$shameLimit = empty($cfg['shameLimit']) ? array(85, 95) : $cfg['shameLimit'];
		$shameBuilds = array(
			'jobs' => array(),
		);

		// sort jobs by code coverage
		usort($codeCoverage['jobs'], function($a, $b) { return round(($a['coverage'] - $b['coverage']) * 100); });

		// ... and copy shame jobs
		foreach ($codeCoverage['jobs'] as $job) {
			if ($job['coverage'] < $shameLimit[0]) {
				$job['class'] = 'text-danger';
				$shameBuilds['jobs'][] = $job;
			} else if ($job['coverage'] < $shameLimit[1]) {
				$job['class'] = 'text-warning';
				$shameBuilds['jobs'][] = $job;
			}
		}

		return $shameBuilds;
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
		$jobs = empty($cfg['jobs']) ? array() : $cfg['jobs'];
		$jenkinsClient = new Jenkins($cfg['url']);
		$codeCoverage = array(
			'jobs' => array(),
		);


		foreach ($jobs as $job) {
			$data = $jenkinsClient->get(array(
				'builds' => array(
					'building',
					'number',
					'result',
					'url',
				)
			), 'job/'.$job['name']);

			$build = array_shift($data['builds']);
			if ($build['building']) {
				$codeCoverage['jobs'][] = array(
					'build' => $build['number'],
					'text' => $job['text'],
					'coverageText' => 'building...',
					'coverage' => 0,
				);
				continue;
			}

			// get data from Jenkins/Hudson
			$coberturaData = $jenkinsClient->get(array(
				'results' => array(
					'elements' => array('name','ratio')
				)
			), "job/{$job['name']}/{$build['number']}/cobertura");

			// no code coverage, lets skip this job
			if (empty($coberturaData['results']['elements'])) {
				continue;
			}

			$avgCoverage = array_reduce(
				$coberturaData['results']['elements'],
				function ($res, $el) { return $res + $el['ratio']; },
				0
			) / count($coberturaData['results']['elements']);

			// save code coverage
			$codeCoverage['jobs'][] = array(
				'build' => $build['number'],
				'text' => $job['text'],
				'coverageText' => round($avgCoverage) . '%',
				'coverage' => $avgCoverage,
				'url' => "{$build['url']}/cobertura",
			);
		}

		return $codeCoverage;
	}

}
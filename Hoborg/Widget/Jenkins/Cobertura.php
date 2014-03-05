<?php
namespace Hoborg\Widget\Jenkins;

use Hoborg\Dashboard\Client\Jenkins;

class Cobertura extends \Hoborg\Dashboard\Widget  {

	public function bootstrap() {
		$cfg = $this->get('config', array());
		$view = empty($cfg['view']) ? 'default' : $cfg['view'];
		$this->jenkinsClient = new Jenkins($cfg['url']);

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
				$this->data['data'] = array(
					'jobs' => $this->getJobsData($codeCoverage),
				);
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

		$limit = empty($cfg['jobsLimit']) ? 10 : $cfg['jobsLimit'];
		array_splice($shameBuilds['jobs'], $limit);

		return $shameBuilds;
	}

	public function getJobsData(array $codeCoverage) {
		$cfg = $this->get('config', array());
		$shameLimit = empty($cfg['shameLimit']) ? array(85, 95) : $cfg['shameLimit'];
		$jobs = array();

		// sort jobs by code coverage
		usort($codeCoverage['jobs'], function($a, $b) { return round(($a['coverage'] - $b['coverage']) * 100); });

		// ... and copy jobs
		foreach ($codeCoverage['jobs'] as $job) {
			if ($job['coverage'] < $shameLimit[0]) {
				$job['class'] = 'text-danger';
				$jobs[] = $job;
			} else if ($job['coverage'] < $shameLimit[1]) {
				$job['class'] = 'text-warning';
				$jobs[] = $job;
			} else {
				$job['class'] = 'text-success';
				$jobs[] = $job;
			}
		}

		$limit = empty($cfg['jobsLimit']) ? 10 : $cfg['jobsLimit'];
		array_splice($jobs, $limit);

		return $jobs;
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
		$codeCoverage = array(
			'jobs' => array(),
		);


		foreach ($jobs as $job) {
			$data = $this->getJob($job['name']);
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

			$coberturaData = $this->getCoberturaData($job['name'], $build['number']);

			// no code coverage, lets skip this job
			if (empty($coberturaData['results']['elements'])) {
				if ($cfg['includeEmpty']) {
					$codeCoverage['jobs'][] = array(
						'build' => $build['number'],
						'text' => $job['text'],
						'coverageText' => '0%',
						'coverage' => 0,
						'url' => "{$build['url']}/cobertura",
					);
				}
				continue;
			}

			$metrics = array('classes' => 1, 'conditionals' => 1, 'files' => 1, 'lines' => 1, 'methods' => 1,
					'packages' => 1);
			if (!empty($cfg['metrics'])) {
				$metrics = $cfg['metrics'];
			}

			$avgCoverage = array_reduce(
				$coberturaData['results']['elements'],
				function ($res, $el) use($metrics) {
					if (!empty($metrics[strtolower($el['name'])])) {
						return $res + $el['ratio'];
					}
					return $res;
				},
				0
			) / count($metrics);

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

	protected function getJob($jobName) {
		$job = $this->jenkinsClient->get(array(
				'builds' => array(
						'building',
						'number',
						'result',
						'url',
				)
		), 'job/' . $jobName);

		return $job;
	}

	protected function getCoberturaData($jobName, $buildNumber) {
		$coberturaData = $this->jenkinsClient->get(array(
			'results' => array(
				'elements' => array('name','ratio')
			)
		), "job/{$jobName}/{$buildNumber}/cobertura");

		return $coberturaData;
	}

}
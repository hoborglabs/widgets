<?php
namespace Hoborg\Widget\Jenkins;

use Hoborg\Dashboard\Client\Jenkins;

class Jobs extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$config = $this->get('config', array());
		$view = empty($cfg['view']) ? 'list' : $cfg['view'];

		$jobs = $this->getJobs($config);
		$this->data['data'] = $jobs;
		$this->data['template'] = file_get_contents(__DIR__ . "/Jobs/{$view}.tache");
	}

	public function getJobs(array $cfg) {
		// get jobs array
		$cfg['jobs'] = empty($cfg['jobs']) ? array() : $cfg['jobs'];
		$jenkinsClient = new Jenkins($cfg['url']);
		$jobs = array(
			'all' => array(),
			'warning' => array(),
		);

		foreach ($cfg['jobs'] as $job) {
			// get build data
			$build = $jenkinsClient->get(array(
				'lastBuild' => array(
					'building',
					'number',
					'result',
					'url',
				),
				'lastCompletedBuild' => array('result'),
			), "job/{$job['name']}");

			$buildInfo = $build['lastBuild'];
			$buildInfo['text'] = $job['text'];

			error_log(__METHOD__ . ' JOB ' . $job['text'] . " status {$buildInfo['result']}");

			if ($buildInfo['building']) {
				if ('FAILURE' == $build['lastCompletedBuild']['result']) {
					$buildInfo['result'] = $build['lastCompletedBuild']['result'];
					$buildInfo['extras'] = 'building';
				}
			}

			if ('FAILURE' == strtoupper($buildInfo['result'])) {
				error_log(__METHOD__ . ' WARNING, job ' . $job['text']);
				$buildInfo['class'] = "text-danger";
				$jobs['warning'][] = $buildInfo;
			}

			$jobs['all'] = $buildInfo;
		}

		$jobs['hasWarning'] = !empty($jobs['warning']);
		error_log(__METHOD__ . ' WARNINGS ' . count($jobs['warning']));

		return $jobs;
	}
}
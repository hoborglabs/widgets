<?php
namespace Hoborg\Widget\Sonetype\Nexus;

use Hoborg\Dashboard\Client\Jenkins;

class LatestVersions extends \Hoborg\Dashboard\Widget  {

	public function bootstrap() {

		$cfg = $this->get('config', array());
		$view = empty($cfg['view']) ? 'default' : $cfg['view'];

		switch ($view) {
			default:
				$this->data['data'] = array(
					'repositories' => $this->getLatestVersions(),
				);
		}

		$this->data['template'] = file_get_contents(__DIR__ . "/LatestVersions/{$view}.tache");
	}

	protected function getLatestVersions() {
		$cfg = $this->get('config', array());
		$repos = array(
			'today' => array(),
			'lastWeek' => array(),
		);

		$baseUrl = $cfg['url'] . '/service/local/repositories/';
		$now = time();

		$today = time() - strtotime('today');
		$lastWeek = time() - strtotime('-1 week');

		foreach ($cfg['repositories'] as $repo) {
			$data = $this->jsonRequest($baseUrl.$repo);

			foreach ($data['data'] as $repository) {

				$lastModified = strtotime($repository['lastModified']);
				if ($now - $lastModified < $lastWeek) {
					// get versions info
					$repoData = $this->jsonRequest($repository['resourceURI']);
					$versions = array_filter($repoData['data'], function($a) { return !$a['leaf']; });
					usort($versions, function($a, $b) {
						return strtotime($b['lastModified']) -  strtotime($a['lastModified']);
					});

					// versions is an array of arrays = splice will give you array with one array
					$repository['version'] = array_splice($versions, 0, 1);
					$repository['version'] = $repository['version'][0];

					// save all other versions
					$repository['oldVersions'] = array_splice($versions, 1, 10);

					if ($now - $lastModified < $today) {
						$repos['today'][] = $repository;
					} else {
						$repos['lastWeek'][] = $repository;
					}
				}
			}
		}

		$repos['noToday'] = empty($repos['today']);
		$repos['noLastWeek'] = empty($repos['lastWeek']);

		return $repos;
	}

	protected function jsonRequest($url) {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
		$result = curl_exec($curl);

		return json_decode($result, true);
	}
}
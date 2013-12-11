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
		$repos = array();

		$baseUrl = $cfg['url'] . '/service/local/repositories/';
		$now = time();

		foreach ($cfg['repositories'] as $repo) {
			$data = $this->jsonRequest($baseUrl.$repo);
			error_log(__METHOD__ . ' url ' .$baseUrl.$repo .' data ' . var_export($data, true));

			foreach ($data['data'] as $repository) {
				$lastModified = strtotime($repository['lastModified']);
				error_log(__METHOD__ . ' mod ' . $repository['text'] . ' diff' . ($now - $lastModified));
				if ($now - $lastModified < 3600) {
					$repos[] = $repository;
				}
			}
		}

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
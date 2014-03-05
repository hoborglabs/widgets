<?php
namespace Hoborg\Widget\Github;

use Hoborg\Dashboard\Client\Github;

class Participation extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$this->setupTemplate();
		$this->data['data'] = $this->getData();
	}

	public function getData() {
		$github = $this->getGithubClient();
		$cfg = $this->get('config', array());
		$repositories = $this->getOrganizationRepositories($github, $cfg['organization']);
		$data = array('repos' => array());

		foreach ($repositories as $repo) {
			$data['repos'][] = array(
				'participation' => $github->get("/repos/{$repo['full_name']}/stats/participation"),
				'name' => $repo['name']
			);
		}

		return $data;
	}

	protected function getGithubClient() {
		$cfg = $this->get('config', array());
		$github = new Github($cfg['url']);

		if (!empty($cfg['accessToken'])) {
			$github->setAccessToken($cfg['accessToken']);
		}

		return $github;
	}


	public function getViewFile() {
		$cfg = $this->get('config', array());
		return __DIR__ . '/Participation/views/' . (empty($cfg['view']) ? 'default' : $cfg['view'] ) . '.html';
	}

	protected function setupTemplate() {
		$this->data['template'] = file_get_contents($this->getViewFile());
	}

	protected function getOrganizationRepositories($github, $organization) {
		$repos = $github->get("/orgs/{$organization}/repos");
		usort($repos, function($a, $b) { return strtotime($b['updated_at']) - strtotime($a['updated_at']); });

		return $repos;
	}
}
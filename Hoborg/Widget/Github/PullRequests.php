<?php
namespace Hoborg\Widget\Github;

use Hoborg\Dashboard\Client\Github;

class PullRequests extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$this->setupTemplate();
		$this->data['data'] = $this->getData();
	}

	public function getData() {
		$github = $this->getGithubClient();
		$cfg = $this->get('config', array());
		$pulls = $this->getPullRequests($github, $cfg['repository']);

		$data = array(
			'pulls' => $this->decoratePulls($pulls)
		);

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
		return __DIR__ . '/PullRequests/views/' . (empty($cfg['view']) ? 'default' : $cfg['view'] ) . '.html';
	}

	protected function setupTemplate() {
		$this->data['template'] = file_get_contents($this->getViewFile());
	}

	protected function getPullRequests($github, $repository) {
		$pulls = $github->get("/repos/{$repository}/pulls");
		$detailedPulls = [];

		foreach ($pulls as $pull) {
			$detailedPulls[] = $github->get("/repos/{$repository}/pulls/{$pull['number']}");
		}

		return $detailedPulls;
	}

	protected function decoratePulls($pulls) {
		$decorated = [];

		foreach ($pulls as $pull) {
			$messages = [];
			$isok = true;

			if (!$pull['mergeable']) {
				$isok = false;
				$messages[] = "PR Not Mergeable";
			}

			$decorated[] = array(
				'cssStyle' => $isok ? 'panel--success' : 'panel--danger',
				'messages' => $messages,
				'user' => $pull['user'],
				'title' => $pull['title'],
				'head' => $pull['head'],
				'base' => $pull['base'],
			);
		}

		return $decorated;
	}
}

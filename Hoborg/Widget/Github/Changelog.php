<?php
namespace Hoborg\Widget\Github;

use Hoborg\Dashboard\Client\Github;

class Changelog extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$this->setupTemplate();
		$this->data['data'] = $this->getData();
	}

	public function getData() {
		$cfg = $this->get('config', array());
		$key = md5($cfg['repository']);

		if (extension_loaded('apc')) {
			$data = apc_fetch($key);
			if ($data) {
				return $data;
			}
		}

		$github = $this->getGithubClient();
		$data = [ 'changelog' => [] ];

		foreach ($cfg['changelogs'] as $item) {
			$log = $this->getChangelog($github, $cfg['repository'], $item['current'], $item['new']);
			$log['label'] = "{$item['new']} >> {$item['current']}";
			$data['changelog'][] = $log;
		}

		if (extension_loaded('apc')) {
			apc_store($key, $data, 180);
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
		return __DIR__ . '/Changelog/views/' . (empty($cfg['view']) ? 'default' : $cfg['view'] ) . '.html';
	}

	protected function setupTemplate() {
		$this->data['template'] = file_get_contents($this->getViewFile());
	}

	protected function getChangelog($github, $repo, $from, $to) {

		$fromTag = $github->get("/repos/{$repo}/git/refs/tags/${from}");
		$fromSha = $this->getCommitFromTag($github, $repo, $fromTag['object']['sha']);

		$toTag = $github->get("/repos/{$repo}/git/refs/tags/${to}");
		$toSha = $this->getCommitFromTag($github, $repo, $toTag['object']['sha']);

		$repos = $github->get("/repos/{$repo}/compare/{$fromSha}...{$toSha}");
		$changelog = [ 'commits' => [], 'files' => $repos['files'] ];

		foreach ($repos['commits'] as $commit) {
			if (count($commit['parents']) == 1) {
				$commit['commit']['short_message'] = strtok($commit['commit']['message'], "\n");

				$jiraId = preg_replace('/\[(.*?)\].*/', '$1', $commit['commit']['short_message']);
				$commit['jira_url'] = "https://sainsburys.atlassian.net/browse/" . $jiraId;

				$changelog['commits'][] = $commit;
			}
		}

		return $changelog;
	}

	protected function getCommitFromTag($github, $repo, $tagSha) {
		$response = $github->get("/repos/{$repo}/git/tags/{$tagSha}");

		if ('commit' == $response['object']['type']) {
			return $response['object']['sha'];
		}

		if ('tag' == $response['object']['type']) {
			return $this->getCommitFromTag($github, $repo, $response['object']['sha']);
		}
	}
}

<?php
namespace Hoborg\Dashboard\Widget\Commiters;

class GithubCollector {

	public function execute($params) {
		$defaults = array(
			'user' => null,
			'repo' => null,
			'out' => null,
			'branch' => 'master',
			'count' => 100,
		);
		$options = $params + $defaults;
		if (empty($options['user']) || empty($options['repo']) || empty($options['out'])) {
			$this->printHelp();
		}
		$url = "https://api.github.com/repos/{$options['user']}/{$options['repo']}/commits?sha={$options['branch']}";
		$logs = file_get_contents($url);

		if (empty($logs)) {
			exit('No logs returned');
		}

		$commits = json_decode($logs, true);
		$commits = array_slice($commits, 0, $options['count']);

		$commits = $this->parseCommits($commits);
		$commiters = $this->getCommiters($commits);

		file_put_contents($options['out'], json_encode($commiters));
	}

	private function parseCommits(array $githubCommits) {
		$commits = array();

		foreach ($githubCommits as $commit) {
			$commits[$commit['sha']] = array(
				'name' => $commit['commit']['author']['name'],
				'email' => $commit['commit']['author']['email'],
				'commit' => $commit['sha'],
			);
		}

		return $commits;
	}

	private function getCommiters(array $commits) {
		$commiters = array();

		foreach ($commits as $commit) {
			if (!isset($commiters[$commit['email']])) {
				$commiters[$commit['email']] = array(
					'email' => $commit['email'],
					'name' => $commit['name'],
					'count' => 0,
				);
			}

			$commiters[$commit['email']]['count']++;
		}

		uasort($commiters, function($a, $b) {return $b['count'] - $a['count']; });

		return $commiters;
	}

	private function printHelp() {
		$help = "
please specify options for Github collector:
  user    github user
  repo    github repository name
  out     output file name
  branch  (optional) branch name
  count   (optional) number of commits to count

example usage
./bin/cmd -c widget.hoborg.commiters.github-collector -d user=hoborglabs -d repo=Dashboard -d out=commits.json
";
		exit($help);
	}
}
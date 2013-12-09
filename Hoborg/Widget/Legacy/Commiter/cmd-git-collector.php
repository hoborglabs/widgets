<?php
namespace Hoborg\Dashboard\Widget\Commiters;

class GitCollector {

	public function execute($params) {
		$defaults = array(
			'git' => 'git',
			'branch' => 'master',
			'count' => 100,
			'wd' => null,
			'out' => null,
		);
		$options = $params + $defaults;
		if (empty($options['wd']) || empty($options['out'])) {
			exit("please specify Working Directory `wd` and Output Direcotry `out`\n");
		}

		$cmd = "{$options['git']} log -{$options['count']} --format=\"%h,%ae,%an\" origin/{$options['branch']}";
		$logs = array();

		chdir($options['wd']);
		exec($cmd, $logs);

		$commits = $this->parse_log($logs);
		$commiters = $this->get_commiters($commits);

		file_put_contents($options['out'], json_encode($commiters));
	}

	private function parse_log(array $logLines) {
		$commits = array();

		foreach ($logLines as $line) {
			list($commitHash, $email, $name) = explode(',', $line, 3);
			$commits[$commitHash] = array(
				'name' => $name,
				'email' => $email,
				'commit' => $commitHash,
			);
		}

		return $commits;
	}

	private function get_commiters(array $commits) {
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
}
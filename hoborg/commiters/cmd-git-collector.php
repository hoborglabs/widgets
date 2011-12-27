<?php

$git = 'git';
$dir = '/Users/oledzkiw/Workspace/hoborglabs-dashboard';
$dataDir = $dir; //__DIR__ . '/../data/';
$branch = 'master';
$cmd = "{$git} log -100 --format=\"%h,%ae,%an\" origin/{$branch}";
$logs = array();

chdir($dir);
exec($cmd, $logs);

$commits = parse_log($logs);
$commiters = get_commiters($commits);

file_put_contents($dataDir . '/git-logs.js', json_encode($commiters));


function parse_log(array $logLines) {
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

function get_commiters(array $commits) {
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
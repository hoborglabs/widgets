<?php
if (empty($widget['conf']['data'])) {
	$widget['body'] = '';
	$widget['error'] = 'Missing or empty data configuration `widget.conf.data`';
	return $widget;
}

$data = json_decode(file_get_contents(__DIR__ . '/../../data/' . $widget['conf']['dataFile']), true);

$authors = array();

foreach ($data as & $commit) {
	$addons = array(
		'img' => 'http://www.gravatar.com/avatar/' . md5($commit['commit']['committer']['email']),
	);
	if ($widget['conf']['mustachify']) {
		$id = rand(0, 2);
		$addons['img'] = 'http://mustachify.me/' . $id . '?src=' . $addons['img'];
	}
	$authors[$commit['commit']['committer']['email']] = $commit['commit']['committer'] + $addons;
}
unset($commit);

ob_start();
include __DIR__ . '/commiters.phtml';
$widget['body'] = ob_get_clean();


return $widget;

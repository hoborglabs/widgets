<?php
// main check for data file.
if (empty($widget['conf']['data'])) {
	$widget['body'] = '';
	$widget['error'] = 'Missing or empty data configuration `widget.conf.data`';
	return $widget;
}

$dataFile = $path = $this->kernel->findFileOnPath(
	$widget['conf']['data'],
	$this->kernel->getDataPath()
);


$jobs = json_decode(file_get_contents($dataFile), true);

$jobs = $jobs['jobs'];

ob_start();
include __DIR__ . '/view.phtml';
$widget['body'] = ob_get_clean();

return $widget;
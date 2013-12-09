<?php

class CommitersWidget extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$widget = $this->data;

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

		if (!$dataFile) {
			$widget['body'] = '';
			$widget['error'] = "Data file is not readable `{$widget['conf']['data']}`";
			return $widget;
		}

		$this->addDefaults(array(
			'conf' => array(
				'cache' => false,
				'mustachify' => 0,
				'minHeight' => 60,
				'maxHeight' => 120,
			),
		));
		$conf = $this->getData('conf');

		// do we want to cache images
		$cacheDir = false;
		if ($conf['cache']) {
			$cacheDir = $this->kernel->getParam('publicPrefix', '') . $conf['cache'];
			if (!is_writable($cacheDir)) {
				$cacheDir = false;
			}
		}

		$authors = json_decode(file_get_contents($dataFile), true);
		$last = end($authors);
		$first = reset($authors);

		$minHeight = $conf['minHeight'];
		$maxHeight = $conf['maxHeight'];

		$resizeFactor = ($maxHeight - $minHeight) / ($first['count'] - $last['count']);
		$delta = $minHeight - ($last['count'] * $resizeFactor);

		foreach ($authors as & $author) {

			$md5 = md5($author['email']);
			$addons = array(
				'img' => 'http://www.gravatar.com/avatar/' . $md5 . '?s=' . $maxHeight,
				'size' => $author['count'] * $resizeFactor + $delta,
			);
			if ($conf['mustachify']) {
				$addons['img'] = 'http://mustachify.me/?src=' . urldecode($addons['img']);
			}

			if ($cacheDir) {
				$fileName = $md5 . '.jpg';
				if ($conf['mustachify']) {
					$fileName = 'mustache-' . $fileName;
				}
				if (!is_readable(H_D_ROOT . '/htdocs/' . $cacheDir . '/' . $fileName)) {
					$imgData = file_get_contents($addons['img']);
					if (!empty($imgData)) {
						file_put_contents($cacheDir . '/' . $fileName, $imgData);
						$addons['img'] = $cacheDir . '/' . $fileName;
					}
				}
				$addons['img'] = $cacheDir . '/' . $fileName;
				if (!file_exists(H_D_ROOT . '/htdocs/' . $cacheDir . '/' . $fileName)) {
					$addons['img'] = "/dev-avatars/default-02.png";
				}
			}

			$author = $author + $addons;
		}
		unset($author);

		ob_start();
		include __DIR__ . '/view.phtml';
		$widget['body'] = ob_get_clean();

		$this->data = $widget;
	}
}

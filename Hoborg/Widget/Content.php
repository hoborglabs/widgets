<?php
namespace Hoborg\Widget;

use Hoborg\Dashboard\Widget;

class Contnet extends Widget {

	public function bootstrap() {
		$cfg = $this->get('config', array());

		if (empty($cfg['template'])) {
			$this->data['template'] = '{{{body}}}';
			$this->data['body'] = $this->getContent();
		} else {
			$this->data['template'] = $this->getTemplate();
		}
	}

	protected function getContent() {
		$cfg = $this->get('config', array());

		if (!empty($cfg['file'])) {
			$filePath = $this->kernel->findFileOnPath(
				$cfg['file'],
				$this->kernel->getDataPath()
			);

			return file_get_contents($filePath);
		}

		return 'No `config.url` nor `config.file` configuration found';
	}

	protected function getTemplate() {
		$cfg = $this->get('config', array());

		$filePath = $this->kernel->findFileOnPath(
			$cfg['template'],
			$this->kernel->getWidgetsPath()
		);

		return file_get_contents($filePath);
	}
}

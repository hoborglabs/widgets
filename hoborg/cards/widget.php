<?php
namespace Hoborg\Dashboard\Widget\Cards;

class CardsWidget extends \Hoborg\Dashboard\Widget {

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

		$allCards = json_decode(file_get_contents($dataFile), true);
		$cards = array();

		$filter = $widget['conf']['filter'];

		if (!empty($filter)) {
			foreach ($allCards as $card) {
				foreach ($filter as $key => $allowedValues)
				if (in_array($card[$key], $allowedValues)) {
					$cards[] = $card;
				}
			}
		} else {
			$cards = $allCards;
		}
		foreach ($cards as & $card) {
			$card['id'] = substr($card['id'], -6);
		}
		unset($card);

		$widget['name'] =  $widget['name_core'] . ' (count: ' . count($cards) . ')';

		ob_start();
		include __DIR__ . '/view.phtml';
		$widget['body'] = ob_get_clean();

		$this->data = $widget;
	}
}

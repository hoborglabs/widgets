<?php
namespace Hoborg\Widget\Healthcheck;

use Hoborg\Dashboard\Client\Http;

class HttpCheck extends \Hoborg\Dashboard\Widget {

	protected $httpClient;

	public function describeConfiguration() {
		return array(
			'view' => array(
				'description' => 'Name of the widget template to use',
				'type' => 'String',
			),
			'checks' => array(
				'description' => 'Name of the widget template to use',
				'type' => 'Array',
			),
		);
	}

	public function bootstrap() {
		$this->httpClient = new \GuzzleHttp\Client();
		$this->setupTemplate();
		$this->data['data'] = $this->getData();
	}

	public function getData() {
		$data = [ 'checks' => [] ];
		$cfg = $this->get('config', array());

		foreach ($cfg['checks'] as $check) {
			$data['checks'][] = $this->checkEndpoint($check);
		}

		return $data;
	}

	public function checkEndpoint($check) {
		$data = [
			'name' => $check['name'],
			'status' => 'down',
			'status_style' => 'btn--danger',
		];

		try {
			$res = $this->httpClient->get($check['url']);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$res = $e->getResponse();
		} catch (\Exception $e) {
			// by default endpoint is assumed down.
			return $data;
		}

		if ($check['code'] == $res->getStatusCode()) {
			$data = [
				'name' => $check['name'],
				'status' => 'up',
				'status_style' => 'btn--success',
			];
		}

		return $data;
	}

	public function getViewFile() {
		$cfg = $this->get('config', array());
		return __DIR__ . '/HttpCheck/views/' . (empty($cfg['view']) ? 'default' : $cfg['view'] ) . '.html';
	}

	protected function setupTemplate() {
		$this->data['template'] = file_get_contents($this->getViewFile());
	}
}

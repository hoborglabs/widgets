<?php 
namespace Hoborg\Widget\Opsview;

use Hoborg\Dashboard\Widget;

class Opsview extends Widget {
	
	protected $opsviewUrl = null;
	
	/**
	 * Logged-in user access data 
	 * @var array
	 */
	protected $accessData = array();
	
	protected function getOpsviewData($endpoint, array $params = array(), $method = 'GET', $type = 'json') {

		if (empty($this->opsviewUrl)) {
			// throw error
			return null;
		}

		$access = $this->getAccessData();
		if (empty($this->accessData)) {
			return null;
		}

		$getQuery = array();
		foreach ($params as $k => $v) {
			if (is_array($v)) {
				foreach ($v as $val) {
					$getQuery[] = urlencode($k) . '=' . urlencode($val);
				}
			} else {
				$getQuery[] = urlencode($k) . '=' . urlencode($v);
			}
		}

		// set content type
		$contentType = 'application/json';
// 		if ('perl' == $type) {
// 			$contentType = 'text/x-data-dumper';
// 		} else if ('xml' == $type) {
// 			$contentType = 'text/xml';
// 		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->opsviewUrl . $endpoint . '?' . implode('&', $getQuery));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-type: {$contentType}",
			"X-Opsview-Username: {$access['username']}",
			"X-Opsview-Token: {$access['token']}",
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($ch);
		curl_close($ch);

		//close connection
		return json_decode($result, true);
	}
	
	protected function getAccessData() {
		if (empty($this->accessData)) {
			$this->login();
		}

		return $this->accessData;
	}
	
	protected function login() {
		$config = $this->getData('config', array());
		$post = array(
			'username' => $config['username'],
			'password' => $config['password'],
		);

		$postString = array();
		foreach ($post as $k => $v) {
			$postString[] = urlencode($k) . '=' . urlencode($v);
		}
		$postString = implode('&', $postString);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->opsviewUrl . '/rest/login');
		curl_setopt($curl, CURLOPT_POST, count($post));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postString);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		//execute post
		$result = curl_exec($curl);
		curl_close($curl);
		$accessData = json_decode($result, true);

		if (empty($accessData)) {
			// throw new exception
			return false;
		}

		$accessData['username'] = $config['username'];
		$this->accessData = $accessData;
		return true;
	}
}
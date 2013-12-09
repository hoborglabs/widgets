<?php
namespace Hoborg\Dashboard\Widget\Cards;

class TrelloCollector {

	public function execute($params) {
		$defaults = array(
			'key' => null,
			'url' => null,
			'out' => null
		);
		$options = $params + $defaults;

		$url = "{$options['url']}/cards?key={$options['key']}";
		$jsonCards = file_get_contents($url);
		$storeFile = $options['out'];

		$issuesData = $this->getCards($jsonCards);

		// collect user ids
		$users = array();
		foreach($issuesData as $card) {
			if (!empty($card['assigned_to_id'])) {
				$users[$card['assigned_to_id']] = array();
			}
		}
		$userUrl = "http://redmine.skybet.net/users/";
		foreach ($users as $key => & $user) {
			$data = file_get_contents($userUrl . $key . '.xml?key=' . $options['key']);
			$d = new SimpleXMLElement($data);
			$user = array(
				'id' => (string) $d->id,
				'email' => (string) $d->mail,
				'first_name' => (string) $d->firstname,
				'last_name' => (string) $d->lastname,
			);
		}
		unset($user);

		foreach($issuesData as & $card) {
			if (!empty($card['assigned_to_id']) && !empty($users[$card['assigned_to_id']])) {
				$card['assignee_details'] = $users[$card['assigned_to_id']];
			}
		}

		file_put_contents($storeFile, json_encode($issuesData));
	}

	protected function getCards($trelloJson) {
		$data = json_decode($trelloJson);
		$issuesData = array();
		$rename = array(
			'name' => 'subject',
		);

		if (empty($data)) {
			return $issuesData;
		}

		// save issues data
		foreach($data as $issue) {
			$issueCopy = array();

			foreach ($issue as $key => $value) {
				$key = isset($rename[$key]) ? $rename[$key] : $key;
				$issueCopy[$key] = $value;
			}

			$issuesData[] = $issueCopy;
		}

		return $issuesData;
	}
}
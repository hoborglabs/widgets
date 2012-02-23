<?php
namespace Hoborg\Dashboard\Widget\Commiters;

class GitCollector {

	public function execute($params) {
		$defaults = array(
			'key' => null,
			'url' => null,
			'query_id' => null,
			'project_id' => null,
			'out' => null
		);
		$options = $params + $defaults;

		$url = "{$options['url']}/issues.xml?key={$options['key']}&query_id={$options['query_id']}".
				"&project_id={$options['project_id']}";
		$xml = file_get_contents($url);
		$storeFile = $options['out'];

		$issuesData = $this->callApi($url,
			array(
				'id' => true,
				'subject' => true,
				'done_ratio' => true,
				'tracker' => array('name', 'id'),
				'priority' => array('name', 'id'),
				'status' => array('name', 'id'),
				'assigned_to' => array('name', 'id'),
			),
			array(
				17 => 'story_points',
			),
			array(
				'assigned_to_name' => 'assignee',
			)
		);

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
			$d = new \SimpleXMLElement($data);
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

	protected function callApi($url, array $save, array $customFields, array $rename = array()) {
		$data = file_get_contents($url);
		$issuesData = array();

		if (empty($data)) {
			outputError('No data returned from: ' . $redmineUrl);
			outputError('Full request: ' . $url);
			return $issuesData;
		}

		// save issues data
		$d = new \SimpleXMLElement($data);
		foreach($d->issue as $issue) {
			$issueCopy = array();

			foreach ($save as $key => $opt) {
				if (is_array($opt)) {
					if (isset($issue->$key)) {
						foreach ($issue->$key->attributes() as $attr => $val) {
							if (in_array($attr, $opt)) {
								$fieldName = !empty($rename[$key.'_'.$attr]) ?
								$rename[$key.'_'.$attr] : $key.'_'.$attr;
								$issueCopy[$fieldName] = (string) $val;
							}
						}
					}
				}
				else {
					$fieldName = !empty($rename[$key]) ?
					$rename[$key] : $key;
					$issueCopy[$fieldName] = (string) $issue->$key;
				}
			}

			foreach ($issue->custom_fields->custom_field as $customField) {
				foreach($customField->attributes() as $attr => $val) {
					if ('id' === $attr) {
						$val = (string) $val;
						if (isset($customFields[$val])) {
							$issueCopy[$customFields[$val]] = (string)$customField->value;
						}
					}
				}
			}

			$issuesData[] = $issueCopy;
		}

		$t = (int) $d['total_count'];
		$o = (int) $d['offset'];
		$l = (int) $d['limit'];
		if ($t > $o + $l) {
			$url .= '&offset=' . ($o + $l);
			$issuesData = array_merge($issuesData, $this->callApi($url, $save, $customFields, $rename));
		}

		return $issuesData;
	}
}
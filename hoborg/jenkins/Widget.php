<?php
namespace Hoborg\Widget\Jenkins;

class Widget extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$widget = $this->data;

		// main check for data file.
		if (empty($widget['conf']['jenkins-url'])) {
			$widget['body'] = '';
			$widget['error'] = 'Missing or empty jenkins-url configuration `widget.conf.jenkins-url`';
			return $widget;
		}
		$jenkinsUrl = $widget['conf']['jenkins-url'];
		$excludedJobs = isset($widget['conf']['exclude']) ? $widget['conf']['exclude'] : array();

		$jobs = $this->execute(array(
			'url' => $jenkinsUrl,
		));
		$jobs = $jobs['jobs'];

		$jobs = $this->filter($jobs, $excludedJobs);

		ob_start();
		include __DIR__ . '/view.phtml';
		$widget['body'] = ob_get_clean();

		$this->data = $widget;
	}

        public function execute($params) {
                $defaults = array(
                        'url' => null,
                );
                $options = $params + $defaults;

                $tree = array(
                        'jobs' => array(
                                'name',
                                'id',
                                'color',
                                'inQueue',
                                'lastBuild' => array(
                                        'number',
                                        'timestamp',
                                        'result',
                                        'url',
                                        'building',
                                ),
                                'healthReport' => array('score', 'description'),
                        )
                );
                $url = $options['url'] . '/api/json?tree=';
                $url .= urlencode($this->get_tree_value($tree));

                // get data from url
                $data = file_get_contents($url);
                if (empty($data)) {
                        die('no Data returned from ' . $url);
                }

                return json_decode($data, true);
        }

        public function get_tree_value(array $tree) {
                $value = '';

                foreach ($tree as $key => $val) {
                        if (is_array($val)) {
                                $value .= ','.$key.'['. $this->get_tree_value($val) . ']';
                        } else {
                                $value .= ','.$val;
                        }
                }

                return substr($value, 1);
        }

	protected function filter(array $jobs, array $exclude) {
		foreach ($jobs as $i => $job) {
			if (in_array($job['name'], $exclude)) {
				unset($jobs[$i]);
			}
		}
		return $jobs;
	}
}

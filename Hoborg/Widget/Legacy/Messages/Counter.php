<?php
namespace Hoborg\Widget\Messages;

class Counter extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$widget = $this->data;
		$data = array();
		$conf = $widget['conf'];

		// main check for data file.
		
		$errors = $this->checkRequiredConfig($conf, array('event', 'year', 'month', 'day'));
		if (!empty($errors)) {
			$data['errors'] = $errors;
		} else {
			$today = new \DateTime();
			$eventDate = new \DateTime("{$conf['year']}-{$conf['month']}-{$conf['day']} {$conf['hour']}:00:00");
			$interval = $eventDate->diff($today);
			$data['days'] = $interval->d;
			$data['hours'] = $interval->h;
			$s = round($interval->i * 0.166);
			$data['hours'] .= ".{$s}";

			$data['event'] = $widget['conf']['event'];
		}

        ob_start();
        include __DIR__ . '/counter.mustache';
        $widget['template'] = ob_get_clean();
        $widget['data'] = $data;

        $this->data = $widget;
    }

    protected function checkRequiredConfig(array $configuration, $required) {
        $errors = array();
        foreach ($required as $key) {
            if (empty($configuration[$key])) {
                $errors[] = "Missing or empty `{$key}` configuration (`widget.conf.{$key}`).";
            }
        }

        return $errors;
    }

}


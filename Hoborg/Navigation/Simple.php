<?php
namespace Hoborg\Navigation;

/**
 * conf.template: "valid-tpl-name"
 * conf.title: "optional menu title"
 * conf.links: [
 *   {text: "", href: "", active: false}
 *   {text: "", href: "", active: true}
 *   {text: "", href: "", active: false}
 * ]
 */
class Simple extends \Hoborg\Dashboard\Widget {

	public function bootstrap() {
		$widget = $this->data;
		$conf = $this->getData('conf', array());
		$tplName = empty($conf['template']) ? 'simple' : $conf['template'];
		$data = array();

		// main check for data file.
		if (empty($conf['links'])) {
			$data['error'] = 'missing links array';
			$conf['links'] = array();
		}
		if (empty($conf['title'])) {
			$conf['title'] = '';
		}

		$data = array(
			'links' => $conf['links'],
			'title' => $conf['title'],
		);

		$widget['template'] = file_get_contents(__DIR__ . "/{$tplName}.mustache");
		$widget['data'] = $data;

		$this->data = $widget;
	}

}

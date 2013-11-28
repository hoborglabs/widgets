<?php 
namespace Hoborg\Widget;

use Hoborg\Dashboard\Widget;

class ResponsiveDesign extends Widget {

	public function bootstrap() {
		
		$this->data['template'] = file_get_contents(__DIR__ . '/responsive-design.mustache');
	}
}
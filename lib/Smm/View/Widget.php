<?php
namespace Smm\View;

use \Z\View;
use \Z\Util\Url;

abstract class Widget {
	public function __toString() {
		return $this->render();
	}
	
	public abstract function render();
}

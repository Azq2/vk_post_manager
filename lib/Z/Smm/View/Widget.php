<?php
namespace Z\Smm\View;

use \Z\Core\View;
use \Z\Core\Util\Url;

abstract class Widget {
	public function __toString() {
		return $this->render();
	}
	
	public abstract function render();
}

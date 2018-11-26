<?php
namespace Z\Core\Task;

class Runner {
	use \Z\Core\Traits\Singleton;
	
	protected function __construct() {
		ob_end_clean();
	}
	
	public function run($argv) {
		$bin = "index.php";
		if ($argv)
			$bin = array_shift($argv);
		
		if ($argv) {
			$task_name = array_shift($argv);
			$task_argv = $argv;
			return self::runTask($task_name, $task_argv);
		} else {
			echo "$bin <task_name> [args]\n";
		}
		
		return 0;
	}
	
	public static function parseArgs($task, $default, $args) {
		while ($args) {
			$tmp = array_shift($args);
			
			if ($tmp[0] === "-" && $tmp[1] === "-") {
				$name = substr($tmp, 2);
				$value = true;
				
				if (strpos($name, "=") > 0)
					list ($name, $value) = explode("=", $name, 2);
				
				$default[$name] = $value;
			}
		}
		
		return $default;
	}
	
	public static function runTask($name, $args) {
		$class = str_replace("/", "\\", $name);
		
		if (!class_exists($class)) {
			echo "Task $name not found.\n";
			return 1;
		}
		
		$task = new $class();
		
		if (!($task instanceof \Z\Core\Task)) {
			echo "Task $name not valid.\n";
			return 1;
		}
		
		$options = self::parseArgs($name, $task->options(), $args);
		if (!is_array($options) || array_key_exists('help', $options))
			return $task->help($_SERVER['argv'][0]." ".$name);
		
		return $task->run($options);
	}
}

<?php
namespace Z\Util;

class Inotify {
	public static function watch($dir, $callback) {
		if (!trim(shell_exec("which inotifywait")))
			throw new Exception("Please, install inotify-tools");
		
		$descriptorspec = [
			0 => ["pipe", "r"],
			1 => ["pipe", "w"],
			2 => ["pipe", "w"],
		];
		$pipes = NULL;
		
		$fh = proc_open("inotifywait -r -e modify -e create ".escapeshellarg($dir)." -m", $descriptorspec, $pipes, $dir, []);
		if (!is_resource($fh))
			throw new \Exception("Can't run inotifywait process :(");
		
		foreach ($pipes as $pipe)
			stream_set_blocking($pipe, false);
		
		$callback();
		
		while (true) {
			$read 	= [$pipes[1]];
			$write	= NULL;
			$except	= NULL;
			
			$changed = stream_select($read, $write, $except, NULL);
			
			if ($changed) {
				stream_get_contents($pipes[1]);
				$callback();
			} elseif ($changed === false) {
				break;
			}
		}
	}
}

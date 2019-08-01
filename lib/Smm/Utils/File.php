<?php
namespace Smm\Utils;

class File {
	public static function mime($file) {
		return trim(shell_exec("file -b --mime-type ".escapeshellarg($file)));
	}
}

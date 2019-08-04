<?php
namespace Smm\Utils;

class File {
	public static function mime($file) {
		return trim(shell_exec("file -b --mime-type ".escapeshellarg($file)));
	}
	
	public static function getDuration($file) {
		$raw = @json_decode(shell_exec("ffprobe ".escapeshellarg($file)." -print_format json -show_format 2>/dev/null"));
		return $raw->format->duration ?? false;
	}
}

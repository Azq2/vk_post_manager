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
	
	public static function getVolume($file) {
		$raw = shell_exec("ffmpeg -i ".escapeshellarg($file)." -af volumedetect -f null /dev/null 2>&1");
		
		$ret = [
			'max'		=> 0, 
			'mean'		=> 0
		];
		
		if (preg_match("/mean_volume:\s*([\d.-]+)/si", $raw, $m))
			$ret['mean'] = $m[1];
		if (preg_match("/max_volume:\s*([\d.-]+)/si", $raw, $m))
			$ret['max'] = $m[1];
		
		return $ret;
	}
}

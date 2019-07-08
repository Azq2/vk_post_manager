<?php
namespace Smm\Utils;

class GD {
	public static function imageCreateFromFile($file) {
		$size = getimagesize($file);
		
		$mime2func = [
			'image/png'		=> "imagecreatefrompng", 
			'image/jpg'		=> "imagecreatefromjpeg", 
			'image/jpeg'	=> "imagecreatefromjpeg", 
			'image/pjpeg'	=> "imagecreatefromjpeg", 
			'image/bmp'		=> "imagecreatefrombmp", 
			'image/webp'	=> "imagecreatefromwebp", 
			'image/gif'		=> "imagecreatefromgif"
		];
		
		// Пытаемся открыть встроенными средствами GD
		if (isset($mime2func[$size['mime']]) && function_exists($mime2func[$size['mime']]))
			return $mime2func[$size['mime']]($file);
		
		$image = false;
		
		// Пытаемся открыть средствами ImageMagick
		if (function_exists('imagecreatefrompng')) {
			$tmp_file = APP.'tmp/convert_'.md5(uniqid()).'.png';
			if (system("convert ".escapeshellarg($file)." ".escapeshellarg($tmp_file)) == 0 && file_exists($tmp_file))
				$image = imagecreatefrompng($tmp_file);
			if (file_exists($tmp_file))
				unlink($tmp_file);
		}
		
		return $image;
	}
	
	public static function stripMetadata($file) {
		$size = getimagesize($file);
		
		if ($size['mime'] == 'image/pjpeg' || $size['mime'] == 'image/jpeg') {
			$tmp_file = APP.'tmp/convert_'.md5(uniqid()).'.jpg';
			$ret = system("jpegoptim -s --all-progressive ".escapeshellarg($file)." --stdout > ".escapeshellarg($tmp_file));
			if ($ret != 0 || !imagecreatefromjpeg($tmp_file)) {
				if (file_exists($tmp_file))
					unlink($tmp_file);
				return false;
			}
			
			if (!rename($tmp_file, $file)) {
				if (file_exists($tmp_file))
					unlink($tmp_file);
				return false;
			}
			
			return true;
		}
		
		return false;
	}
}

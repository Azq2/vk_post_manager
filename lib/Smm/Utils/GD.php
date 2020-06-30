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
	
	public static function fakeExif($file) {
		$size = getimagesize($file);
		
		if ($size['mime'] == 'image/pjpeg' || $size['mime'] == 'image/jpeg') {
			$base_time = mt_rand(time() - 3600 * 24 * 365, time());
			$exif_date = mt_rand(strtotime(date("Y-m-d 08:00:00", $base_time)), strtotime(date("Y-m-d 17:00:00", $base_time)));
			
			$params = [
				'ExifImageWidth'		=> $size[0], 
				'ExifImageHeight'		=> $size[1], 
				'CreateDate'			=> date("Y-m-d H:i:s", $exif_date), 
				'DateTimeOriginal'		=> date("Y-m-d H:i:s", $exif_date), 
				'ModifyDate'			=> date("Y-m-d H:i:s", $exif_date), 
			];

			$tmp = [];
			foreach ($params as $k => $v)
				$tmp[] = "-$k=".escapeshellarg($v);
			
			$tmp_file = APP.'tmp/convert_'.md5(uniqid()).'.jpg';
			
			if (!copy($file, "$file.jpg"))
				return false;
			
			$ret = 1;
			$stdout = [];
			exec("exiftool -m ".escapeshellarg("$file.jpg")." -tagsFromFile ".escapeshellarg(APP."www/images/exif-template.jpg")." ".implode(" ", $tmp)." -o ".escapeshellarg($tmp_file), $stdout, $ret);
			if ($ret != 0 || !file_exists($tmp_file) || !filesize($tmp_file) || !imagecreatefromjpeg($tmp_file)) {
				if (file_exists($tmp_file))
					unlink($tmp_file);
				if (file_exists("$file.jpg"))
					unlink("$file.jpg");
				return false;
			}
			
			unlink("$file.jpg");
			
			if (!rename($tmp_file, $file)) {
				if (file_exists($tmp_file))
					unlink($tmp_file);
				return false;
			}
			
			return true;
		}
		
		return false;
	}
	
	public static function stripMetadata($file) {
		$size = getimagesize($file);
		
		if ($size['mime'] == 'image/pjpeg' || $size['mime'] == 'image/jpeg') {
			$tmp_file = APP.'tmp/convert_'.md5(uniqid()).'.jpg';
			$ret = 1;
			$stdout = [];
			exec("jpegoptim -f -s --all-progressive ".escapeshellarg($file)." --stdout > ".escapeshellarg($tmp_file), $stdout, $ret);
			if ($ret != 0 || !file_exists($tmp_file) || !filesize($tmp_file) || !imagecreatefromjpeg($tmp_file)) {
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

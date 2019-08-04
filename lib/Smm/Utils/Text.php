<?php
namespace Smm\Utils;

class Text {
	public static function prepareMacroses($text, $args = []) {
		$args = $args;
		
		// {var_name}
		$text = preg_replace_callback("/{([\w\d+_-]+)}/is", function ($m) use ($args) {
			if (isset($args[$m[1]]))
				return $args[$m[1]];
			return $m[0];
		}, $text);
		
		// {for_NAME}
		$text = preg_replace_callback("/\{for_([\w\d_]+)\}(.*?)\{\/for_\1\}/is", function ($m) use ($args) {
			if (isset($args["is_".$m[1]]) && $args["is_".$m[1]])
				return $m[2];
			return "";
		}, $text);
		
		// sex
		$text = preg_replace_callback("/{([\w\d+_-]+_)?sex\|\|(.*?)\|\|(.*?)}/is", function ($m) use ($args) {
			$var_name = $m[1]."sex";
			if (isset($args[$var_name])) {
				$values = [$m[2], $m[3]];
				return $values[$args[$var_name] ? 1 : 0];
			}
			return $m[0];
		}, $text);
		
		// conan
		$text = preg_replace_callback("/{([\w\d+_-]+)\|\|(.*?)\|\|(.*?)\|\|(.*?)}/is", function ($m) use ($args) {
			if (isset($args[$m[1]])) {
				$num = (int) preg_replace("/\D/", "", $args[$m[1]]);
				$titles = [$m[2], $m[3], $m[4]];
				$cases = [2, 0, 1, 1, 1, 2];
				
				$text = $titles[($num % 100 > 4 && $num % 100 < 20 ? 2 : $cases[min($num % 10, 5)])];
				$text = str_replace('$n', $args[$m[1]], $text);
				
				return $text;
			}
			return $m[0];
		}, $text);
		
		return $text;
	}
}

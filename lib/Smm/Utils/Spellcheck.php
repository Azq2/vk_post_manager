<?php
namespace Smm\Utils;

class Spellcheck {
	protected static $pspell = [];
	
	public static function check($text) {
		$errors = [];
		if (function_exists('pspell_new')) {
			preg_match_all("/([a-zа-яё][a-zа-яё'-]+[a-zа-яё]|[a-zа-яё]+)/siu", $text, $m, PREG_OFFSET_CAPTURE);
			foreach ($m[1] as $tmp) {
				list ($word, $offset) = $tmp;
				
				$offset = mb_strlen(substr($text, 0, $offset));
				
				$lang = "en";
				if (preg_match("/[а-яё]/iu", $word))
					$lang = "ru";
				
				if ($lang != "ru")
					continue;
				
				if (!isset(self::$pspell[$lang]))
					self::$pspell[$lang] = pspell_new($lang);
				
				if (!pspell_check(self::$pspell[$lang], $word)) {
					$errors[] = [
						$offset, mb_strlen($word), 
						pspell_suggest(self::$pspell[$lang], $word)
					];
				}
			}
		}
		return $errors;
	}
}

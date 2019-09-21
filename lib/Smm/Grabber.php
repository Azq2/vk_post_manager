<?php
namespace Smm;

class Grabber {
	const SOURCE_VK			= 0;
	const SOURCE_OK			= 1;
	const SOURCE_INSTAGRAM	= 2;
	const SOURCE_PINTEREST	= 3;
	
	public static $type2name = [
		self::SOURCE_VK			=> 'VK', 
		self::SOURCE_OK			=> 'OK', 
		self::SOURCE_INSTAGRAM	=> 'INSTAGRAM', 
		self::SOURCE_PINTEREST	=> 'PINTEREST', 
	];
	
	public static $name2type = [
		'VK'			=> self::SOURCE_VK, 
		'OK'			=> self::SOURCE_OK, 
		'INSTAGRAM'		=> self::SOURCE_INSTAGRAM, 
		'PINTEREST'		=> self::SOURCE_PINTEREST
	];
}

<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\AMQP;

class CatificatorVolumeTester extends \Z\Task {
	public function run($args) {
		$tracks_query = DB::select()
			->from('catificator_tracks')
			->where('volume_mean', '=', 0);
		
		foreach ($tracks_query->execute() as $track) {
			$volume = \Smm\Utils\File::getVolume(APP.'www/files/catificator/'.$track['md5'].'.ogg');
			echo "#".$track['id']." - ".json_encode($volume)."\n";
			DB::update('catificator_tracks')
				->set([
					'volume_mean'		=> $volume['mean'], 
					'volume_max'		=> $volume['max'], 
				])
				->where('id', '=', $track['id'])
				->execute();
		}
	}
}

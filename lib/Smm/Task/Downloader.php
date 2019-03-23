<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;
use \Z\Net\VkApi;

use \Smm\VK\Captcha;

class Downloader extends \Z\Task {
	const QUEUE_DIR = APP.'tmp/download_queue';
	
	protected $queue = [];
	protected $download_queue = [];
	protected $curl_multi;
	protected $file_id = 0;
	protected $api;
	
	protected $tmp_files = [];
	protected $download_ids = [];
	protected $attachments = [], $attachments_ids = [];
	
	public function options() {
		return [
			'anticaptcha' => 0
		];
	}
	
	public function run($args) {
		umask(0);
		
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		Captcha::setMode($args['anticaptcha'] ? 'anticaptcha' : 'cli');
		
		$this->api = new \Z\Net\VkApi(\Smm\Oauth::getAccessToken('VK'));
		
		\Z\Util\Inotify::watch(self::QUEUE_DIR, [$this, 'processQueue']);
		/*$this->download("https://pp.userapi.com/.ht", "/tmp/mixer0.jpg", function ($filename) {
			echo "done - $filename\n";
		}, function ($error) {
			echo "error - $error\n";
		}, function ($a, $b, $c) {
			echo "progress - $a, $b, $c\n";
		});
		
		$vk = new VkApi(\Smm\Oauth::getAccessToken('VK'));
		
		while ($this->processDownload());*/
	}
	
	public function processQueue() {
		echo date("Y-m-d H:i:s")."\n";
		
		$n = 0;
		$dir = opendir(self::QUEUE_DIR);
		do {
			clearstatcache();
			
			while (($id = readdir($dir))) {
				$file = self::QUEUE_DIR."/$id";
				
				if (!is_file($file))
					continue;
				
				if (!filesize($file))
					usleep(100000);
				
				$fp = fopen($file, "r");
				flock($fp, LOCK_EX);
				$raw = "";
				while (!feof($fp))
					$raw .= fread($fp, 4096);
				$queue = json_decode($raw);
				flock($fp, LOCK_UN);
				fclose($fp);
				
				if (!$queue) {
					echo "$id: delete invalid task: $raw\n";
					unlink($file);
				} else if (time() - filectime($file) > 600) {
					echo "$id: delete expired task (".date("Y-m-d H:i:s", filectime($file)).")\n";
					unlink($file);
					unset($this->queue[$id]);
				} elseif ($queue->done ?? false) {
					echo "$id: already done.\n";
				} else if (!isset($this->queue[$id])) {
					$queue->id = $id;
					$this->queue[$id] = $queue;
					$this->queueDownloadFiles($id);
					$this->processDownload();
				}
			}
		} while ($this->processDownload());
	}
	
	public function queueDownloadFiles($id) {
		$queue = $this->queue[$id];
		
		echo $queue->id.": start download files\n";
		
		$queue->total = count($queue->images) + count($queue->documents) + count($queue->files);
		$queue->downloaded = 0;
		$queue->uploaded = 0;
		
		$files = [];
		foreach ($queue->images as $file)
			$files[] = ['url' => $file, 'type' => 'photo', 'out' => APP."/tmp/download/".md5($id.$file).".bin"];
		foreach ($queue->documents as $file)
			$files[] = ['url' => $file, 'type' => 'doc', 'out' => APP."/tmp/download/".md5($id.$file).".bin"];
		foreach ($queue->files as $file)
			$files[] = ['url' => $file, 'type' => 'file', 'out' => APP."/tmp/download/".md5($id.$file).".bin"];
		
		$progress_offsets = [];
		$progress_sizes = [];
		
		$cover = NULL;
		
		if ($queue->cover) {
			$cover = \Smm\Utils\GD::imageCreateFromFile($queue->cover);
			if (!$cover) {
				$this->queueError($queue->id, 'GD не смог прочитать обложку.');
				return;
			}
		}
		
		foreach ($files as $file) {
			echo "  ".$file['url']."\n";
			
			$this->tmp_files[$queue->id][] = $file['out'];
			$this->download_ids[$queue->id][] = $this->download($file['url'], $file['out'], function ($path) use ($file, $queue, $cover) {
				++$queue->downloaded;
				$this->syncQueueState($queue->id);
				
				if ($file['type'] == 'file') {
					$image_types = ['image/png', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/webp'];
					$mime = strtolower(mime_content_type($file['out']));
					
					if (in_array($mime, $image_types)) {
						$file['type'] = 'photo';
					} elseif ($mime == 'image/gif') {
						$file['type'] = 'doc';
					} else {
						$this->queueError($queue->id, $file['url'].' - неизвестный тип файла ('.$mime.')');
						return;
					}
				}
				
				if ($cover) {
					$error = $this->imageWatermark($file['out'], $cover, $queue->offset);
					if ($error) {
						$this->queueError($queue->id, $queue->error);
						return;
					}
				}
				
				$upload = [];
				if ($file['type'] == 'photo') {
					$upload[] = [
						'path'		=> $file['out'], 
						'caption'	=> ''
					];
				} elseif ($file['type'] == 'doc') {
					$url = DB::select('url')
						->from('vk_grabber_data_owners')
						->where('id', '=', \Smm\Grabber::SOURCE_VK.'_-'.$queue->gid)
						->execute()
						->get('url', false);
					
					$upload[] = [
						'path'		=> $file['out'], 
						'caption'	=> '', 
						'title'		=> $url ? "vk.com$url" : "image.gif", 
						'document'	=> true
					];
				}
				
				$tries = 5;
				$result = NULL;
				
				while ($tries) {
					$result = \Smm\VK\Posts::uploadPics($this->api, $queue->gid, $upload);
					if ($result->success) {
						foreach ($result->attachments as $key => $attachment) {
							if (strpos($key, "doc") === 0) {
								$this->attachments_ids[$queue->id][] = $key;
								$this->attachments[$queue->id][] = (object) [
									'type'	=> 'doc', 
									'doc'	=> $attachment
								];
							} else {
								$this->attachments_ids[$queue->id][] = $key;
								$this->attachments[$queue->id][] = (object) [
									'type'	=> 'photo', 
									'photo'	=> $attachment
								];
							}
						}
						
						++$queue->uploaded;
						$this->syncQueueState($queue->id);
						
						break;
					}
					--$tries;
				}
				
				if (!$tries) {
					$this->queueError($queue->id, $file['url'].' - ошибка загрузки файла ('.$result->error.')');
					return;
				} elseif ($queue->uploaded >= $queue->total) {
					$this->queueDone($queue->id);
				}
			}, function ($error) use ($file, $queue) {
				$this->queueError($queue->id, $error);
			}, function ($offset, $total) use ($file, $queue, &$progress_offsets, &$progress_sizes) {
				if ($total > 0) {
					$progress_offsets[$file['url']] = $offset;
					$progress_sizes[$file['url']] = $total;
					
					$queue->download_offset = \array_sum($progress_offsets);
					$queue->download_size = \array_sum($progress_sizes);
					
					$this->syncQueueState($queue->id);
				}
			});
		}
	}
	
	public function queueDone($id) {
		$queue = $this->queue[$id];
		
		echo $queue->id.": done\n";
		
		$queue->done = true;
		$queue->attaches_data = \Smm\VK\Posts::normalizeAttaches((object) [
			'attachments' => $this->attachments[$id]
		]);
		$queue->attaches = $this->attachments_ids[$id];
		
		$this->queueCleanup($queue->id);
		$this->syncQueueState($queue->id);
	}
	
	public function queueCleanup($id) {
		$queue = $this->queue[$id];
		if (isset($this->tmp_files[$queue->id])) {
			foreach ($this->tmp_files[$queue->id] as $file) {
				if (file_exists($file))
					unlink($file);
			}
		}
		
		if ($queue->cover)
			unlink($queue->cover);
	}
	
	public function queueError($id, $error) {
		$queue = $this->queue[$id];
		
		$this->queueCleanup($queue->id);
		
		$queue->done = true;
		$queue->error = $error;
		$this->syncQueueState($queue->id);
		
		if (isset($this->download_ids[$queue->id])) {
			foreach ($this->download_ids[$queue->id] as $id)
				$this->cancelDownload($id);
		}
		
		echo $queue->id.": $error\n";
	}
	
	public function queueUploadFiles($id) {
		$queue = $this->queue[$id];
		
		echo $queue->id.": start upload files\n";
	}
	
	public function syncQueueState($id) {
		file_put_contents(self::QUEUE_DIR."/$id", json_encode($this->queue[$id]), LOCK_EX);
	}
	
	public function cancelDownload($id) {
		if (isset($this->download_queue[$id])) {
			curl_multi_remove_handle($this->curl_multi, $this->download_queue[$id]['handle']);
			unset($this->download_queue[$id]);
		}
	}
	
	public function download($url, $output, $onsuccess, $onerror, $onprogress) {
		if (!$this->curl_multi) {
			$this->curl_multi = curl_multi_init();
			curl_multi_setopt($this->curl_multi, CURLMOPT_PIPELINING, CURLPIPE_HTTP1 | CURLPIPE_MULTIPLEX);
		}
		
		$fp = fopen($output, "w+");
		if (!$fp) {
			$onerror("Can't open file: $output");
			return;
		}
		
		if (!flock($fp, LOCK_EX)) {
			$onerror("Can't lock file: $output");
			return;
		}
		
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_FILE				=> $fp, 
			CURLOPT_FOLLOWLOCATION		=> true, 
			CURLOPT_URL					=> $url
		]);
		
		curl_multi_add_handle($this->curl_multi, $curl);
		
		$id = $this->file_id++;
		
		$this->download_queue[$id] = (object) [
			'fp'			=> $fp, 
			'url'			=> $url, 
			'curl'			=> $curl, 
			'file'			=> $output, 
			'onsuccess'		=> $onsuccess, 
			'onerror'		=> $onerror, 
			'onprogress'	=> $onprogress, 
			'tries'			=> 10
		];
		
		return $id;
	}
	
	public function processDownload() {
		if (!$this->curl_multi || !$this->download_queue)
			return false;
		
		curl_multi_select($this->curl_multi);
		curl_multi_exec($this->curl_multi, $still_alive);
		
		while (($item = curl_multi_info_read($this->curl_multi))) {
			$queue = NULL;
			$queue_id = NULL;
			foreach ($this->download_queue as $index => $data) {
				if ($data->curl === $item['handle']) {
					$queue = $data;
					$queue_id = $index;
					break;
				}
			}
			
			$http_code = curl_getinfo($queue->curl, CURLINFO_HTTP_CODE);
			
			$error = false;
			if ($http_code != 200) {
				$error = 'HTTP error code: '.$http_code;
			} elseif (!filesize($queue->file)) {
				$error = 'Empty response.';
			}
			
			curl_multi_remove_handle($this->curl_multi, $item['handle']);
			
			if ($error) {
				if ($queue->tries > 0) {
					--$queue->tries;
					ftruncate($queue->fp, 0);
					rewind($queue->fp);
					curl_multi_add_handle($this->curl_multi, $item['handle']);
				} else {
					unset($this->download_queue[$queue_id]);
					$queue->onerror && ($queue->onerror)($error);
					flock($queue->fp, LOCK_UN);
					fclose($queue->fp);
				}
			} else {
				unset($this->download_queue[$queue_id]);
				flock($queue->fp, LOCK_UN);
				fclose($queue->fp);
				$queue->onsuccess && ($queue->onsuccess)($queue->file);
			}
		}
		
		foreach ($this->download_queue as $queue) {
			if ($queue->onprogress) {
				$info = curl_getinfo($queue->curl);
				($queue->onprogress)($info['size_download'], $info['download_content_length']);
			}
		}
		
		return count($this->download_queue) > 0;
	}
	
	public function imageWatermark($tmp_file, $cover, $offset) {
		$image = \Smm\Utils\GD::imageCreateFromFile($tmp_file);
		if (!$image)
			return 'GD не смог откыть '.$img;
		
		if (imagesy($image) < imagesy($cover)) {
			$new_image = imagecreatetruecolor(imagesx($cover), imagesy($cover));
			imagecopyresampled($new_image, $image, 0, $offset, 0, 0, imagesx($image), imagesy($image), imagesx($image), imagesy($image));
			imagecopyresampled($new_image, $cover, 0, 0, 0, 0, imagesx($new_image), imagesy($new_image), imagesx($cover), imagesy($cover));
			$image = $new_image;
		} else {
			imagecopyresampled($image, $cover, 0, 0, 0, 0, imagesx($image), imagesy($image), imagesx($cover), imagesy($cover));
		}
		
		if (!imagepng($image, $tmp_file))
			return 'GD не смог сохранить '.$img;
		
		return false;
	}
}

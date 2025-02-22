<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;

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
		
		if (!\Smm\Utils\Lock::lock(__CLASS__))
			return;
		
		Captcha::setMode($args['anticaptcha'] ? 'anticaptcha' : 'cli');
		
		$this->api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK'));
		$this->processQueue();
	}
	
	public function processQueue() {
		echo date("Y-m-d H:i:s")."\n";
		
		$amqp = \Z\Net\AMQP::instance();
		$amqp->queue_declare('download_queue', false, true);
		
		$n = 0;
		while (1) {
			do {
				while (($amqp_msg = $amqp->basic_get('download_queue', false))) {
					$id = $amqp_msg->body;
					
					$queue = \Z\Cache::instance()->get("download_queue:$id");
					if (!$queue || !is_object($queue)) {
						echo "$id: delete invalid task.\n";
						$amqp->basic_ack($amqp_msg->delivery_info['delivery_tag']);
						continue;
					}
					
					$queue->delivery_tag = $amqp_msg->delivery_info['delivery_tag'];
					
					if (time() - $queue->ctime > 600) {
						echo "$id: delete expired task (".date("Y-m-d H:i:s", $queue->ctime).")\n";
						unset($this->queue[$id]);
					} elseif ($queue->done ?? false) {
						echo "$id: already done.\n";
						$amqp->basic_ack($queue->delivery_tag);
					} else if (!isset($this->queue[$id])) {
						$queue->id = $id;
						$this->queue[$id] = $queue;
						$this->queueDownloadFiles($id);
						$this->processDownload();
					}
					
					usleep(5000);
				}
			} while ($this->processDownload());
			
			usleep(300000);
		}
	}
	
	public function queueDownloadFiles($id) {
		$queue = $this->queue[$id];
		
		echo $queue->id.": start download files\n";
		
		$queue->downloaded = 0;
		$queue->uploaded = 0;
		
		$type2urls = [
			'photo'		=> $queue->images,
			'doc'		=> $queue->documents,
			'video'		=> $queue->videos,
			'file'		=> $queue->files,
		];
		
		$files = [];
		foreach ($type2urls as $type => $urls) {
			foreach ($urls as $url) {
				if (preg_match("/^\/img-proxy\//", $url)) {
					$url_query = [];
					parse_str(parse_url($url, PHP_URL_QUERY), $url_query);
					if (isset($url_query['url']))
						$url = $url_query['url'];
				}
				
				$file_index = count($files);
				$file_id = md5($id.$url.$file_index);
				$files[] = [
					'url'		=> $url,
					'type'		=> $type,
					'out'		=> APP."/tmp/download/$file_id.bin",
					'index'		=> $file_index
				];
			}
		}
		
		$queue->total = count($files);
		
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
					} elseif (substr($mime, 0, 5) == 'video') {
						$file['type'] = 'video';
					} else {
						$this->queueError($queue->id, $file['url'].' - неизвестный тип файла ('.$mime.')');
						return;
					}
				}
				
				if ($file['type'] != 'video') {
					if ($cover) {
						$error = $this->imageWatermark($file['out'], $cover, $queue->offset);
						if ($error) {
							$this->queueError($queue->id, $queue->error);
							return;
						}
					}
					
					if (\Smm\Utils\GD::stripMetadata($file['out'])) {
						echo "  => strip metadata OK\n";
					//	if (\Smm\Utils\GD::fakeExif($file['out']))
					//		echo "  => fake exif OK\n";
					}
				}
				
				$upload = [];
				if ($file['type'] == 'photo') {
					$upload[] = [
						'path'		=> $file['out'], 
						'caption'	=> ''
					];
				} elseif ($file['type'] == 'doc') {
					$url = $this->getVkGroupLink($queue->gid);
					
					$upload[] = [
						'path'		=> $file['out'], 
						'caption'	=> '', 
						'title'		=> $url ? "vk.com$url" : "image.gif", 
						'document'	=> true
					];
				} elseif ($file['type'] == 'video') {
					$url = $this->getVkGroupLink($queue->gid);
					
					$upload[] = [
						'path'		=> $file['out'], 
						'caption'	=> '', 
						'title'		=> $url ? "vk.com$url" : "video.mp4", 
						'video'		=> true
					];
				}
				
				$tries = 5;
				$result = NULL;
				
				while ($tries) {
					$result = \Smm\VK\Posts::uploadPics($this->api, $queue->gid, $upload);
					if ($result->success) {
						foreach ($result->attachments as $key => $attachment) {
							if (strpos($key, "doc") === 0) {
								$this->attachments_ids[$queue->id][$file['index']] = $key;
								$this->attachments[$queue->id][$file['index']] = (object) [
									'type'	=> 'doc', 
									'doc'	=> $attachment
								];
							} else {
								$this->attachments_ids[$queue->id][$file['index']] = $key;
								$this->attachments[$queue->id][$file['index']] = (object) [
									'type'	=> 'photo', 
									'photo'	=> $attachment
								];
							}
						}
						
						++$queue->uploaded;
						$this->syncQueueState($queue->id);
						
						break;
					}
					sleep(1);
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
	
	public function getVkGroupLink($gid) {
		$cache = \Z\Cache::instance();
		
		$url = $cache->get("vk_group_addr:$gid");
		
		if (!$url) {
			$url = "/public$gid";
			
			for ($i = 0; $i < 5; ++$i) {
				$res = $this->api->exec("groups.getById", [
					'group_ids'		=> $gid
				]);
				
				if ($res->success()) {
					if (isset($res->response[0]->screen_name))
						$url = "/".$res->response[0]->screen_name;
					$cache->set("vk_group_addr:$gid", $url, 3600);
					break;
				}
				
				echo "ERROR: Can't get link for #$gid: ".$res->error()."\n";
				
				sleep(1);
			}
		}
		
		return $url;
	}
	
	public function queueDone($id) {
		$queue = $this->queue[$id];
		
		echo $queue->id.": done\n";
		
		$amqp = \Z\Net\AMQP::instance();
		$amqp->basic_ack($queue->delivery_tag);
		
		ksort($this->attachments[$id]);
		ksort($this->attachments_ids[$id]);
		
		$queue->done = true;
		$queue->attaches_data = \Smm\VK\Posts::normalizeAttaches((object) [
			'attachments' => array_values($this->attachments[$id])
		]);
		$queue->attaches = array_values($this->attachments_ids[$id]);
		
		$this->queueCleanup($queue->id);
		$this->syncQueueState($queue->id);
		
		unset($this->queue[$id]);
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
		
		$amqp = \Z\Net\AMQP::instance();
		$amqp->basic_ack($queue->delivery_tag);
		
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
		\Z\Cache::instance()->set("download_queue:$id", $this->queue[$id], 3600);
	}
	
	public function cancelDownload($id) {
		if (isset($this->download_queue[$id])) {
			curl_multi_remove_handle($this->curl_multi, $this->download_queue[$id]->curl);
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
			CURLOPT_URL					=> $url, 
			CURLOPT_VERBOSE				=> false, 
			CURLOPT_USERAGENT			=> "Mozilla/5.0 (Linux; Android 6.0.1; SM-G532G Build/MMB29T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.83 Mobile Safari/537.36", 
			CURLOPT_IPRESOLVE			=> CURL_IPRESOLVE_V4
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

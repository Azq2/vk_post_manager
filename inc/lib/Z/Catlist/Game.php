<?php
namespace Z\Catlist;

use \Mysql;
use \Http;

class Game extends \Z\Core\App {
	private $users_cache;
	private $app;
	
	public function __construct($group_id) {
		$this->start_time = microtime(true);
		$this->app = Mysql::query("SELECT * FROM `vkapp` WHERE `group_id` = ?", $group_id)->fetchObject();
		
		$this->messages = [];
		foreach (Mysql::query("SELECT * FROM `vkapp_catlist_messages`")->fetchAll() as $m)
			$this->messages[$m['id']] = $m['message'];
		
		$this->vk = new \Z\Core\Net\VK();
		$this->vk->setCommToken($this->app->token);
		
		$this->settings = Game\Settings::instance();
	}
	
	public function setUser($user_id) {
		$this->user = $this->getUser($user_id);
		
		// Глобальные параметры сообщения
		$this->global_message_args = [
			'first_name'	=> $this->user->first_name, 
			'last_name'		=> $this->user->last_name, 
			'money'			=> $this->user->money, 
			'bonus'			=> round($this->user->bonus), 
			'toilet'		=> round($this->user->toilet)."%", 
			'food'			=> round($this->user->food)."%", 
		];
		$this->global_message_args['menu'] = $this->L("menu");
	}
	
	public function handleMessage($msg = false) {
		$state = $this->user->getState();
		
		$badwords = "хуй|пизда|мудак|пидор|нахуй|пнх|ебать|блять|блядь|бля|сука|заебала|нах|блядина|мразь|скотина|дура|дурак|хуйня|шлюха|шкура|саси|".
			"сасай|пидр|лох|уёба|уёбок|шмара|отсоси";
		
		if ($msg) {
			if ($this->matchWords($msg->body, $badwords)) {
				$this->sendMessage($this->user->user_id, $this->L($this->user->sex == 2 ? "censored_man" : "censored_woman"));
				return;
			}
			
			if ($this->matchWords($msg->body, "дайбабло")) {
				$this->user->moneyIncr(1024, 'Чит "дайбабло"')->save();
				$this->sendMessage($this->user->user_id, "Бабло начислено");
				return;
			}
			
			if ($this->matchWords($msg->body, "жумхуй")) {
				Mysql::query("DELETE FROM `vkapp_catlist_users` WHERE `user_id` = ?", $this->user->user_id);
				Mysql::query("DELETE FROM `vkapp_catlist_user_cats` WHERE `user_id` = ?", $this->user->user_id);
				Mysql::query("DELETE FROM `vkapp_catlist_money_history` WHERE `user_id` = ?", $this->user->user_id);
				$this->sendMessage($this->user->user_id, "Очищено");
				return;
			}
		}
		
		switch ($this->user->action) {
			case "landing":
				if ($msg && $this->matchWords($msg->body, "играть|го|go")) {
					$this->user->setAction('menu')->save();
					return $this->handleMessage();
				} else {
					$this->sendMessage($this->user->user_id, $this->L("landing"));
				}
			break;
			
			case "yard":
				
			break;
			
			case "hall":
				$menu = [
					"меню"					=> "menu", 
					"0|назад|дом"			=> "home", 
					"1|магазин|купить"		=> "shop"
				];
				if ($this->menuRouter($msg, $menu))
					return;
				$this->sendMessage($this->user->user_id, $this->L("hall_empty"));
			break;
			
			case "wash_cat":
				if ($state && $state->washed) {
					$cat = Mysql::query("SELECT * FROM `vkapp_catlist_user_cats` WHERE `id` = ?", 
						$state->cat_id)->fetchObject();
					
					$menu = [
						"0|меню"				=> "menu", 
						"1|ванна|ванная|назад"	=> "bathroom"
					];
					if ($this->menuRouter($msg, $menu))
						return;
					$this->sendMessage($this->user->user_id, $this->L("wash_done", [
						'cat_name'	=> $cat->name, 
						'cat_sex'	=> $cat->sex
					]));
				} else {
					$menu = [
						"меню"			=> "menu", 
						"0|дом|назад"	=> "home"
					];
					if ($this->menuRouter($msg, $menu))
						return;
					$i = 0;
					$list = [];
					$req = Mysql::query("SELECT * FROM `vkapp_catlist_user_cats` WHERE `user_id` = ?", $this->user->user_id);
					while ($cat = $req->fetchObject()) {
						$list[] = $this->L("wash_list_item", [
							'cat_name'	=> $cat->name, 
							'cat_sex'	=> $cat->sex, 
							'n'			=> $i + 1
						]);
						
						if ($this->matchWords($msg->body, ($i + 1)."|".$cat->name)) {
							$this->user->setAction("wash_cat", ['washed' => true, 'cat_id' => $cat->id])->save();
							return $this->handleMessage();
						}
						++$i;
					}
					
					$this->sendMessage($this->user->user_id, $this->L("wash_list", [
						'list' => $list ? implode("\n", $list) : $this->L("wash_list_empty")
					]));
				}
			break;
			
			case "bathroom":
				$menu = [
					"0|меню|назад"						=> "menu", 
					"1|лоток|туалет"					=> "toilet", 
					"2|купать|покупать|искупать|купать"	=> "wash_cat", 
				];
				if ($this->menuRouter($msg, $menu))
					return;
				
				$this->sendMessage($this->user->user_id, $this->L("bathroom_menu"));
			break;
			
			case "home":
				$menu = [
					"0|меню|назад"				=> "menu", 
					"1|миска|мыска|еда"			=> "food", 
					"2|ванная|ванна"			=> "bathroom", 
					"3|двор|дворик"				=> "yard", 
				];
				if ($this->menuRouter($msg, $menu))
					return;
				
				$this->sendMessage($this->user->user_id, $this->L("home_menu"));
			break;
			
			case "notifications":
				if ($msg) {
					$n = $this->matchWords($msg->body, "1|2|3|4|5|6", true);
					if ($n) {
						$this->user->notify = $n - 1;
						$this->user->save();
						$this->sendMessage($this->user->user_id, $this->L("notifications_saved"));
						return $this->user->setAction("menu")->save();
					}
				}
				$this->sendMessage($this->user->user_id, $this->L("notifications_menu"));
			break;
			
			case "shop":
				$menu = [
					"0|меню|назад"				=> "menu", 
					"1|породистые|коты"			=> "catshop", 
					"2|корм|еда"				=> "food_shop", 
					"3|мебель"					=> "furniture_shop", 
					"4|игрушки"					=> "toys_shop", 
				];
				if ($this->menuRouter($msg, $menu))
					return;
				
				$this->sendMessage($this->user->user_id, $this->L("shop_menu"));
			break;
			
			case "food_shop":
			case "furniture_shop":
			case "toys_shop":
				$type = str_replace("_shop", "", $this->user->action);
				$products = \Z\Catlist\Game\Shop\Product::findAll(['deleted' => 0, 'type' => $type]);
				
				// Рандомная сортировка с сохранением порядка при перелистывании
				$list_changed = false;
				if ($state) {
					$new_products = [];
					foreach ($state->ids as $id)
						$new_products[$id] = NULL;
					foreach ($products as $n => $product)
						$new_products[$product->id] = $product;
					$new_products = array_values($new_products);
					
					foreach ($new_products as $product) {
						if (is_null($product)) {
							$list_changed = true;
							break;
						}
					}
					
					if (!$list_changed)
						$products = $new_products;
				}
				
				if (!$state) {
					$state = (object) ['ids' => [], 'offset' => 0];
					foreach ($products as $n => $product)
						$state->ids[] = $product->id;
					$this->user->setAction($this->user->action, $state)->save();
				}
				
				if ($msg) {
					if ($this->matchWords($msg->body, "ещё")) {
						$state->offset += $this->settings->on_page;
						$this->user->setAction($this->user->action, $state)->save();
					} elseif ($this->matchWords($msg->body, "начало")) {
						$state->offset = 0;
						$this->user->setAction($this->user->action, $state)->save();
					}
				}
				
				$images = [];
				$list = [];
				$j = 0; $i = 0;
				foreach ($products as $product) {
					if ($i >= $state->offset) {
						$list[] = $this->L("shop_{$type}_list_item", [
							'n'				=> $i + 1, 
							'title'			=> $product->title, 
							'description'	=> $product->description, 
							'amount'		=> $product->amount, 
							'price'			=> $product->price, 
						]);
						
						if (!$list_changed && $msg && $this->matchWords($msg->body, ($i + 1)."|".$cat->title)) {
							$this->user->setAction($this->user->action, [
								'product_id'	=> $product->id, 
								'action'		=> 'buy_product'
							])->save();
							return $this->handleMessage();
						}
						
						$images[] = H.'../files/catlist/shop/'.$product->photo.'.jpg';
						++$j;
						
						if ($j >= $this->settings->on_page)
							break;
					}
					++$i;
				}
				
				$pagination = "";
				if (count($products) - ($state->offset + $this->settings->on_page) > 0) {
					$pagination = $this->L("shop_more");
				} elseif (count($products) > $this->settings->on_page) {
					$pagination = $this->L("shop_rewind");
				}
				
				if ($msg) {
					if ($this->matchWords($msg->body, "0|назад|меню")) {
						$this->user->setAction('menu')->save();
						return $this->handleMessage();
					}
				}
				
				$this->sendMessage($this->user->user_id, $this->L("shop_{$type}_list", [
					'list'			=> implode("\n", $list), 
					'pagination'	=> $pagination
				]), $images);
			break;
			
			case "catshop":
			case "shelter":
				$prefix = $this->user->action;
				
				$limit = $prefix == 'catshop' ? $this->settings->max_cats : $this->settings->max_free_cats;
				
				if ($this->user->cats >= $limit) {
					$this->sendMessage($this->user->user_id, $this->L("{$prefix}_limit", ['cnt' => $this->settings->max_cats]));
					$this->user->setAction("menu")->save();
					return;
				}
				
				if ($state && isset($state->action)) {
					$cat = Mysql::query("SELECT * FROM `vkapp_catlist_cats` WHERE `id` = ?", $state->cat_id)
						->fetchObject();
					if (!$cat) {
						$this->user->setAction($prefix)->save();
						return $this->handleMessage();
					}
					
					$this->setMsgGlobals([
						'cat_name'	=> isset($state->name) ? $state->name : $cat->name, 
						'cat_price'	=> $cat->price, 
						'cat_sex'	=> $cat->sex
					]);
					
					if ($state->action == 'done') {
						// Котик выбран, сохраняем
						if ($this->user->money >= $cat->price) {
							Mysql::query(
								"
									INSERT INTO `vkapp_catlist_user_cats` SET
										`user_id`	= ?, 
										`ctime`		= ?, 
										`name`		= ?, 
										`photo`		= ?, 
										`width`		= ?, 
										`height`	= ?, 
										`sex`		= ?
								", 
								$this->user->user_id, 
								time(), 
								$state->name, 
								$cat->photo, 
								$cat->width, 
								$cat->height, 
								$cat->sex
							);
							
							$this->user->incr('cats');
							$this->user->moneyDecr($cat->price, 'Покупка кота');
							
							if ($this->user->cats == 1)
								$this->user->food = 100;
							
							$this->user->setAction('menu')->save();
							$this->sendMessage($this->user->user_id, $this->L("{$prefix}_done"), [H.'../files/catlist/cats/'.$cat->photo.'.jpg']);
						} else {
							// Денег нет :(
							$state->action = 'confirm_cat';
							$this->user->setAction($prefix, $state)->save();
						}
					} elseif ($state->action == 'confirm_name') {
						// Подтверждаем выбранного кота
						$menu = [
							"меню"									=> "menu", 
							"0|назад|приют|магазин|нет|каталог"		=> function () use ($prefix) {
								$this->user->setAction($prefix)->save();
								$this->handleMessage();
								return true;
							}, 
							"1|да|согласна|согласен|подтверждаю|го"	=> function () use ($prefix, $state) {
								$state->action = 'done';
								$this->user->setAction($prefix, $state)->save();
								$this->handleMessage();
								return true;
							}, 
							"*"										=> function () use ($prefix, $state, $msg) {
								$state->action = 'name';
								$this->user->setAction($prefix, $state)->save();
								$this->handleMessage($msg);
								return true;
							}
						];
						if ($this->menuRouter($msg, $menu))
							return;
						
						// Подтверждаем имя выбранному коту
						$this->sendMessage($this->user->user_id, $this->L("{$prefix}_cat_name_confirm"));
					} elseif ($state->action == 'name') {
						// Вводим имя выбранному коту
						$menu = [
							"0|меню"									=> "menu", 
							"1|назад|приют|магазин|вернуться|каталог"	=> function () use ($prefix) {
								$this->user->setAction($prefix)->save();
								$this->handleMessage();
								return true;
							}
						];
						if ($this->menuRouter($msg, $menu))
							return;
						
						if ($msg) {
							$error = false;
							$min_name = 2;
							$max_name = 16;
							
							$name = mb_strtolower(trim($msg->body));
							if (preg_match("/^([а-яё-]+)$/iu", $name, $m)) {
								$name = mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
								if (preg_match("/^(да|согласна|согласен|подтверждаю|го|магазин|каталог|приют|нет)$/ui", $name))
									$error = $this->L("{$prefix}_cat_name_error_reserved_word", ['cat_sex' => $cat->sex]);
								elseif (mb_strlen($name) < $min_name)
									$error = $this->L("{$prefix}_cat_name_error_short", ['length' => $min_name]);
								elseif (mb_strlen($name) > $max_name)
									$error = $this->L("{$prefix}_cat_name_error_long", ['length' => $max_name]);
							} else {
								$error = $this->L("{$prefix}_cat_name_error_chars");
							}
							
							if ($error) {
								$this->sendMessage($this->user->user_id, $error);
							} else {
								$state->name = $name;
								$state->action = 'confirm_name';
								$this->user->setAction($prefix, $state)->save();
								return $this->handleMessage();
							}
						} else {
							$this->sendMessage($this->user->user_id, $this->L("{$prefix}_cat_name"));
						}
					} elseif ($state->action == 'confirm_cat') {
						if ($this->user->money >= $cat->price) {
							// Подтверждаем выбранного кота
							$menu = [
								"меню"									=> "menu", 
								"0|назад|приют|магазин|нет|каталог"		=> $prefix, 
								"1|да|согласна|согласен|подтверждаю|го"	=> function () use ($prefix, $state) {
									$state->action = 'name';
									$this->user->setAction($prefix, $state)->save();
									$this->handleMessage();
									return true;
								}, 
							];
							if ($this->menuRouter($msg, $menu))
								return;
							
							$text = $this->L("{$prefix}_confirm");
							$this->sendMessage($this->user->user_id, $text, [H.'../files/catlist/cats/'.$cat->photo.'.jpg']);
						} else {
							$menu = [
								"0|меню"							=> "menu", 
								"1|назад|приют|магазин|каталог"		=> $prefix, 
								"2|заработать"						=> ["help", ['section' => 'money']]
							];
							if ($this->menuRouter($msg, $menu))
								return;
							
							$text = $this->L("{$prefix}_no_money", [
								'need_money'	=> $cat->price - $this->user->money, 
							]);
							
							$this->sendMessage($this->user->user_id, $text, [H.'../files/catlist/cats/'.$cat->photo.'.jpg']);
						}
					} else {
						$this->user->setAction($prefix)->save();
						return $this->handleMessage();
					}
				} else {
					$cats = Mysql::query("SELECT * FROM `vkapp_catlist_cats` WHERE `price` ".($prefix == 'shelter' ? '=' : '>')." 0 ORDER BY RAND()")
						->fetchAll();
					
					$list_changed = false;
					if ($state) {
						$new_cats = [];
						foreach ($state->ids as $id)
							$new_cats[$id] = NULL;
						foreach ($cats as $n => $cat)
							$new_cats[$cat['id']] = $cat;
						$new_cats = array_values($new_cats);
						
						foreach ($new_cats as $cat) {
							if (is_null($cat)) {
								$list_changed = true;
								break;
							}
						}
						
						if (!$list_changed)
							$cats = $new_cats;
					}
					
					if (!$state) {
						$state = (object) ['ids' => [], 'offset' => 0];
						foreach ($cats as $n => $cat)
							$state->ids[] = $cat['id'];
						$this->user->setAction($prefix, $state)->save();
					}
					
					if ($msg) {
						if ($this->matchWords($msg->body, "ещё")) {
							$state->offset += $this->settings->on_page;
							$this->user->setAction($prefix, $state)->save();
						} elseif ($this->matchWords($msg->body, "начало")) {
							$state->offset = 0;
							$this->user->setAction($prefix, $state)->save();
						}
					}
					
					$images = [];
					$list = [];
					$j = 0; $i = 0;
					foreach ($cats as $n => $cat) {
						if ($i >= $state->offset) {
							$list[] = $this->L("{$prefix}_list_item", [
								'n'			=> $n + 1, 
								'cat_sex'	=> $cat['sex'], 
								'cat_name'	=> $cat['name'], 
								'cat_price'	=> $cat['price'], 
								'cat_descr'	=> $cat['descr'], 
							]);
							
							if (!$list_changed && $msg && $this->matchWords($msg->body, ($n + 1)."|".$cat['name'])) {
								$this->user->setAction($prefix, [
									'cat_id'	=> $cat['id'], 
									'action'	=> 'confirm_cat'
								])->save();
								return $this->handleMessage();
							}
							
							$images[] = H.'../files/catlist/cats/'.$cat['photo'].'.jpg';
							++$j;
							
							if ($j >= $this->settings->on_page)
								break;
						}
						++$i;
					}
					
					$pagination = "";
					if (count($cats) - ($state->offset + $this->settings->on_page) > 0) {
						$pagination = $this->L("{$prefix}_more");
					} elseif (count($cats) > $this->settings->on_page) {
						$pagination = $this->L("{$prefix}_rewind");
					}
					
					if ($msg) {
						if ($this->matchWords($msg->body, "0|назад|меню")) {
							$this->user->setAction('menu')->save();
							return $this->handleMessage();
						}
					}
					
					$this->sendMessage($this->user->user_id, $this->L("{$prefix}_list", [
						'list'			=> implode("\n", $list), 
						'pagination'	=> $pagination
					]), $images);
				}
			break;
			
			case "menu":
				$menu = [
					"1|дом"				=> "home", 
					"2|магазин"			=> "shop", 
					"3|приют"			=> "shelter", 
					"4|оповещения"		=> "notifications", 
					"5|рейтинг"			=> "rating", 
					"6|бонусы|услуги"	=> "services", 
				];
				
				if ($this->menuRouter($msg, $menu))
					return;
				$this->sendMessage($this->user->user_id, 
					$this->user->cats ? $this->L("start_has_cats") : $this->L("start_no_cats"));
			break;
			
			default:
				$this->log("Unknown state (#%d): %s", $this->user->user_id, $this->user->action);
				$this->user->setAction('menu')->save();
				$this->handleMessage();
			break;
		}
	}
	
	public function cronVkLikes() {
		$res = $this->vk->exec("wall.get", ['owner_id' => -33414947/*-$this->app->group_id*/]);
		if (isset($res->response)) {
			var_dump($res->response->items[0]->likes->count);
		}
	}
	
	public function setMsgGlobals($args) {
		$this->global_message_args = array_merge($this->global_message_args, $args);
	}
	
	public function menuRouter($msg, $menu) {
		if ($msg) {
			foreach ($menu as $match => $action) {
				if ($this->matchWords($msg->body, $match)) {
					if (!is_string($action) && is_callable($action)) {
						return $action();
					} elseif (is_array($action)) {
						$this->user->setAction($action[0], $action[1])->save();
						$this->handleMessage();
						return true;
					} else {
						$this->user->setAction($action)->save();
						$this->handleMessage();
						return true;
					}
				}
			}
		}
		return $msg && isset($menu['*']) ? $menu['*']($msg) : false;
	}
	
	public function matchWords($text, $words_list, $ret_word = false) {
		$words = [];
		foreach (explode("|", $words_list) as $w)
			$words[str_replace("ё", "е", mb_strtolower($w))] = 1;
		
		preg_match_all("/([a-zа-яё'-]+|[\d]+)/ui", $text, $m);
		foreach ($m[1] as $w) {
			$w = str_replace("ё", "е", mb_strtolower($w));
			if (isset($words[$w]))
				return $ret_word ? $w : true;
		}
		
		return false;
	}
	
	public function L($text, $args = []) {
		if (!isset($this->messages[$text])) {
			$out = "Неизвестное сообщение! (id=$text)\n";
			foreach ($args as $k => $v)
				$out .= "{$k} => '$v'\n";
			return $out;
		}
		
		$args = $args + $this->global_message_args;
		
		$text = $this->messages[$text];
		
		// {var_name}
		$text = preg_replace_callback("/{([\w\d+_-]+)}/is", function ($m) use ($args) {
			if (isset($args[$m[1]]))
				return $args[$m[1]];
			return $m[0];
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
	
	public function sendMessage($user_id, $text, $images = []) {
		$md5sums = [];
		$md5_to_file = [];
		$vk_attaches = [];
		foreach ($images as $file) {
			$md5 = md5_file($file);
			$md5sums[] = $md5;
			$md5_to_file[$md5] = $file;
			$vk_attaches[$md5] = NULL;
		}
		
		if ($md5sums) {
			$req = Mysql::query("SELECT * FROM `vkapp_catlist_files` WHERE `md5` IN (?) AND `time` > ?", 
				$md5sums, time() - 24 * 3600 * 365 * 10);
			foreach ($req->fetchAll() as $f)
				$vk_attaches[$f['md5']] = $f['attach_id'];
			
			$i = 0;
			$files_to_upload = [];
			foreach ($md5_to_file as $md5 => $file) {
				if (!isset($vk_attaches[$md5])) {
					$files_to_upload[] = [
						'key'	=> 'file'.($i % 7), 
						'path'	=> $file, 
						'name'	=> 'file.jpg', 
						'md5'	=> $md5
					];
				}
				++$i;
			}
			
			if ($files_to_upload) {
				$start = microtime(true);
				$this->log("upload %d files", count($files_to_upload));
				foreach (array_chunk($files_to_upload, 7) as $chunk) {
					// Выгружаем фотки
					$tries = 10;
					$api_data = false;
					$errors = [];
					while (--$tries) {
						$server = $this->vk->execComm('photos.getMessagesUploadServer');
						if (isset($server->response)) {
							$res = $this->vk->upload($server->response->upload_url, $chunk);
							if ($res->code == 200) {
								if ($json = json_decode($res->body)) {
									if (!isset($json->photo) || !isset($json->server) || !isset($json->hash)) {
										$error = "unknown server answer: ".$res->body;
									} elseif ($json->photo == "[]") {
										$error = "upload error,  photo=[]";
									} else {
										$api_data = [
											'server'	=> $json->server, 
											'hash'		=> $json->hash, 
											'photo'		=> stripslashes($json->photo)
										];
										break;
									}
								} else {
									$error = "JSON parse error: ".$res->body;
								}
							} else {
								$error = "http error: ".$res->code;
							}
						} else {
							$error = $this->vk->error($server)->error;
						}
						$this->log("upload error: $error");
						$errors[] = $error;
						sleep(1);
					}
					
					if (!$api_data)
						throw new \Exception(implode("\n", $errors));
					
					// Сохраняем фотки
					$tries = 10;
					$errors = [];
					$success = false;
					while (--$tries) {
						$res = $this->vk->execComm("photos.saveMessagesPhoto", $api_data);
						if ($res && isset($res->response)) {
							foreach ($res->response as $i => $p) {
								$md5 = $chunk[$i]['md5'];
								$vk_attaches[$md5] = 'photo'.$p->owner_id.'_'.$p->id;
								
								Mysql::query("
									INSERT INTO `vkapp_catlist_files` SET
										`md5`		= ?, 
										`time`		= ?, 
										`attach_id`	= ?
									ON DUPLICATE KEY UPDATE
										`time`		= VALUES(`time`), 
										`attach_id`	= VALUES(`attach_id`)
								", $md5, time(), $vk_attaches[$md5]);
							}
							$success = true;
							break;
						} else {
							$error = $this->vk->error($res)->error;
						}
						$this->log("save error: $error");
						$errors[] = $error;
						sleep(1);
					}
					
					if (!$success)
						throw new \Exception(implode("\n", $errors));
				}
				
				$this->log("upload done (%.04f)", microtime(true) - $start);
				
				foreach ($vk_attaches as $md5 => $p) {
					if (is_null($p))
						throw new \Exception('Not all files uploaded! '.json_encode($vk_attaches));
				}
			}
		}
		
		$res = $this->vk->execComm("messages.send", [
			'message'		=> $text.' // '.round(microtime(true) - $this->start_time, 4).' s', 
			'user_id'		=> $user_id, 
			'attachment'	=> implode(",", array_values($vk_attaches))
		]);
		
		$error = $this->vk->error($res);
		if ($error)
			throw new \Exception($error);
	}
	
	public function handle($data) {
		switch ($data->type) {
			case "message_new":
				$this->setUser($data->object->user_id);
				$this->handleMessage($data->object);
			break;
			
			case "message_allow":
			case "message_deny":
				$user = Game\User::createModel($data->object->from_id);
				if ($user) {
					$user->deny = $data->type == 'message_deny' ? 1 : 0;
					$user->save();
				}
			break;
		}
	}
	
	public function getUser($id) {
		$user = Game\User::createModel($id);
		if (!$user) {
			$user = Game\User::createNew();
			$res = $this->vk->exec("users.get", [
				'user_ids'	=> $id, 
				'fields'	=> 'first_name,last_name,sex'
			]);
			if (isset($res->response)) {
				$user->user_id		= $res->response[0]->id;
				$user->first_name	= $res->response[0]->first_name;
				$user->last_name	= $res->response[0]->last_name;
				$user->sex			= $res->response[0]->sex;
				$user->ctime		= time();
				$user->mtime		= time();
				
				$user->save();
				return $user;
			}
			throw new \Exception("$id - get vk user error: ".json_encode($res));
		}
		return $user;
	}
	
	public function log() {
		$text = call_user_func_array("sprintf", func_get_args());
		file_put_contents("handler.log", "[".date("d-m-Y H:i:s")."] $text\n", FILE_APPEND | LOCK_EX);
	}
}

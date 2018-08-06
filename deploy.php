<?php
(new Deployer)->run();

class Deployer {
	private static $custom_chmod = [
		"files"				=> 0777, 
		"tmp"				=> 0777, 
		"tmp/download"		=> 0777, 
		"tmp/post_queue"	=> 0777, 
		"files/catlist"		=> 0777, 
	];
	
	private static $skip = [
		".git", ".svn", "README.md"
	];
	
	private static $preserve = [
		"files", 
		"tmp", 
		"logs", 
	];
	
	public function __construct() {
		
	}
	
	public function run() {
		$options = getopt("d", [
			"src::", 
			"dst::", 
			"override::", 
			"force", 
			"test", 
			"help", 
			"watch"
		]);
		
		// Что за долбоёб писал враппер для getopt?
		foreach ($options as $k => $v) {
			if ($v === false)
				$options[$k] = true;
		}
		
		$this->options = (object) array_merge([
			'src'		=> __DIR__, 
			'dst'		=> getenv("HOME").'/apps/xujxuj', 
			'override'	=> getenv("HOME").'/apps/xujxuj-override', 
			'd'			=> false, 
			'force'		=> false, 
			'test'		=> false, 
			'help'		=> false, 
			'watch'		=> false
		], $options);
		
		if ($this->options->help) {
			echo implode("\n", [
				"--watch       режим демона автодеплоя с наблюдением за изменением файлов", 
				"--src         папка с исходниками из GIT", 
				"--dst         путь, куда деплоить приложение", 
				"--override    путь к папке с файлами, которые нужно переопределить", 
				"              (например, конфиги продакшена)", 
				"--test        тестовый режим, без изменения файлов", 
				"--force       форсированный деплой для режима copy", 
				"              режим copy при force=0 проверяет mtime, а при force=1 содержимое файла"
			])."\n";
			return;
		}
		
		$src = realpath($this->options->src);
		$dst = realpath($this->options->dst);
		$override = $this->options->override ? realpath($this->options->override) : false;
		
		$user = fileowner("$src/cron/crontab");
		if ($user != getmyuid())
			throw new Exception("Expected user $user, but now ".getmyuid());
		
		if (!is_dir($src))
			throw new Exception("Invalid source dir: ".$this->options->src);
		
		if (!is_dir($dst))
			throw new Exception("Invalid destination dir: ".$this->options->dst);
		
		if ($this->options->override && !is_dir($override))
			throw new Exception("Invalid override dir: ".$this->options->override);
		
		$lock_file = "/tmp/deploy.".md5(__FILE__.":$src:$dst");
		$do_lock = function () use ($lock_file) {
			$lock_fp = fopen($lock_file, "w+");
			if (!$lock_fp || !flock($lock_fp, LOCK_EX | LOCK_NB)) {
				echo "Already run!\n";
				return false;
			}
			return $lock_fp;
		};
		
		if ($this->options->d) {
			if ($this->options->watch && !($lock_fp = $do_lock()))
				return;
			
			$cmd = [PHP_BINARY];
			foreach ($GLOBALS['argv'] as $v) {
				if ($v != "-d")
					$cmd[] = escapeshellarg($v);
			}
			
			flock($lock_fp, LOCK_UN);
			fclose($lock_fp);
			
			system("daemon -D ".escapeshellarg(getcwd())."  -- ".implode(" ", $cmd));
			return;
		}
		
		$do_deploy = function () use ($src, $dst, $override) {
			// Копируем или линкуем всё содержимое дистрибутива
			$this->rsync($src, $dst, $override, !$this->options->test);
			
			// Обновляем crontab
			$this->cron();
		};
		
		$do_deploy();
		
		if ($this->options->watch) {
			$descriptorspec = [
				0 => ["pipe", "r"],
				1 => ["pipe", "w"],
				2 => ["pipe", "w"],
			];
			$pipes = NULL;
			
			$fh = proc_open("inotifywait -r -e modify -e create ".escapeshellarg(__DIR__)." -m", $descriptorspec, $pipes, __DIR__, []);
			if (!is_resource($fh))
				throw new Exception("Can't run inotifywait process :(");
			
			foreach ($pipes as $pipe)
				stream_set_blocking($pipe, false);
			
			if (!($lock_fp = $do_lock()))
				return;
			
			while (true) {
				$read 	= [$pipes[1]];
				$write	= NULL;
				$except	= NULL;
				
				$changed = stream_select($read, $write, $except, NULL);
				
				if ($changed) {
					echo stream_get_contents($pipes[1]);
					$do_deploy();
				} elseif ($changed === false) {
					break;
				}
			}
		}
	}
	
	public function cron() {
		// Получаем текущий кронтаб
		$cur_crontab = shell_exec("crontab -l");
		
		$crontab = 
			"# <xujxuj-smm>\n".
			"# Crontab : ".$this->options->dst."/cron/crontab\n".
			trim(file_get_contents($this->options->dst."/cron/crontab"))."\n".
			"# </xujxuj-smm>\n";
		
		$crontab = strtr($crontab, [
			'{project}'		=> $this->options->dst
		]);
		
		// Если есть уже в crontab записи для этого проекта, то обнолвяем их
		$cron_re = '/#\s?<xujxuj-smm>.*?<\/xujxuj-smm>\n?/si';
		if (preg_match($cron_re, $cur_crontab)) {
			$cur_crontab = preg_replace($cron_re, $crontab, $cur_crontab);
		}
		// Иначе добавляем в конец
		else {
			$cur_crontab .= "\n".$crontab;
		}
		
		$cron_tmp = "/tmp/".md5(__FILE__.rand().time());
		if (file_put_contents($cron_tmp, $cur_crontab))
			system("crontab < ".escapeshellarg($cron_tmp));
		
		if (file_exists($cron_tmp))
			unlink($cron_tmp);
	}
	
	public function rsync($src, $dst, $override, $commit) {
		// Получаем все файлы src и dst
		list ($src_dirs, $src_files) = $this->readDir($src, self::$skip);
		list ($dst_dirs, $dst_files) = $this->readDir($dst);
		
		$override_dirs = array();
		$override_files = array();
		
		if ($this->options->override) {
			list ($override_dirs, $override_files) = $this->readDir($override);
			
			foreach ($override_dirs as $f) {
				if (!in_array($f, $src_dirs))
					$src_dirs[] = $f;
			}
			
			foreach ($override_files as $f) {
				if (!in_array($f, $src_files))
					$src_files[] = $f;
			}
		}
		
		// Удаляем лишние файлы
		foreach ($dst_files as $f) {
			if (!in_array($f, $src_files) && $this->canDelete($f)) {
				echo "D    $f\n";
				if ($commit && !unlink("$dst/$f"))
					throw new Exception("Can't delete: $dst/$f");
			}
		}
		
		// Удаляем лишние папки
		foreach (array_reverse($dst_dirs) as $f) {
			if (!in_array($f, $src_dirs) && $this->canDelete($f)) {
				echo "D    $f\n";
				if ($commit && !rmdir("$dst/$f"))
					throw new Exception("Can't delete: $dst/$f");
			}
		}
		
		// Создаём нужные папки
		foreach ($src_dirs as $f) {
			if (file_exists("$dst/$f") && !is_dir("$dst/$f")) {
				echo "D    $f\n";
				if ($commit && !unlink("$dst/$f"))
					throw new Exception("Can't delete: $dst/$f");
			}
			
			$chmod = isset(self::$custom_chmod[$f]) ? self::$custom_chmod[$f] : 0755;
			if (!file_exists("$dst/$f")) {
				echo "A    $f\n";
				if ($commit && !mkdir("$dst/$f", $chmod, true))
					throw new Exception("Can't mkdir: $dst/$f");
			}
			
			if ($commit && !chmod("$dst/$f", $chmod))
				throw new Exception("Can't chmod: $dst/$f");
			
			if ($commit && (!chgrp("$dst/$f", getmygid()) || !chown("$dst/$f", getmyuid())))
				throw new Exception("Can't chown: $dst/$f");
		}
		
		// Линкуем нужные файлы
		foreach ($src_files as $f) {
			$real_src = in_array($f, $override_files) ? $override : $src;
			
			$src_mtime = filemtime("$src/$f");
			$dst_exists = file_exists("$dst/$f");
			
			$is_changed = !$dst_exists || $src_mtime != filemtime("$dst/$f");
			
			if (!$is_changed)
				$is_changed = is_link("$dst/$f") != is_link("$src/$f");
			
			if (!$is_changed && is_link("$src/$f") && is_link("$dst/$f"))
				$is_changed = readlink("$dst/$f") != readlink("$src/$f");
			
			if (!$is_changed && $this->options->force)
				$is_changed = md5_file("$dst/$f") != md5_file("$src/$f");
			
			if ($is_changed) {
				echo $dst_exists ? "M    $f\n" : "A    $f\n";
				if ($commit) {
					if ($dst_exists)
						$this->rmrf("$dst/$f");
					
					if (is_link("$src/$f")) {
						if (!symlink(readlink("$src/$f"), "$dst/$f"))
							throw new Exception("Can't copy: $symlink/$f");
					} else {
						if (!copy("$src/$f", "$dst/$f"))
							throw new Exception("Can't copy: $dst/$f");
						
						if (!touch("$dst/$f", $src_mtime))
							throw new Exception("Can't touch: $dst/$f");
					}
				}
			}
		}
	}
	
	public function rmrf($path) {
		if (!in_array($path, ["/", "./", ""]))
			system("rm -rf ".escapeshellarg($path));
	}
	
	public function canDelete($file) {
		foreach (self::$preserve as $f) {
			if ($f === $file || strpos($file, "$f/") === 0)
				return false;
		}
		return true;
	}
	
	public function _readDir($dir, $path, &$files, &$dirs, $skip) {
		$fh = opendir($dir);
		while ($name = readdir($fh)) {
			if ($name == "." || $name == ".." || in_array($name, $skip))
				continue;
			if (is_dir("$dir/$name")) {
				$dirs[] = "$path$name";
				$this->_readDir("$dir/$name", "$path$name/", $files, $dirs, $skip);
			} else {
				$files[] = "$path$name";
			}
		}
		closedir($fh);
	}
	
	public function readDir($dir, $skip = []) {
		$dirs = [];
		$files = [];
		
		if (is_dir($dir))
			$this->_readDir($dir, "", $files, $dirs, $skip);
		
		return [$dirs, $files];
	}
}

<?php
namespace Z\Core\DB\Provider;

class MySQLi extends \Z\Core\DB\Provider {
	protected $config;
	protected $handle;
	
	public function __construct($config) {
		$this->config = array_merge([
			'host'			=> NULL, 
			'user'			=> NULL, 
			'password'		=> NULL, 
			'database'		=> NULL, 
			'charset'		=> 'utf8mb4', 
			'persistent'	=> false
		], $config);
	}
	
	private function connection() {
		if (!$this->handle) {
			if (strpos($this->config['host'], "unix:") === 0) {
				$host = ($this->config['persistent'] ? 'p:' : '').substr($this->config['host'], 5);
				$this->handle = new \mysqli(NULL, $this->config['user'], $this->config['password'], $this->config['database'], 0, $host);
			} else {
				$tmp_host = explode(":", $this->config['host']);
				$host = ($this->config['persistent'] ? 'p:' : '').$tmp_host[0];
				$port = isset($tmp_host[1]) ? $tmp_host[1] : 3306;
				$this->handle = new \mysqli($host, $this->config['user'], $this->config['password'], $this->config['database'], $port, NULL);
			}
			
			if ($this->handle->connect_error)
				throw new \Z\Core\DB\Exception("MySQLi connect error (".$this->handle->connect_errno."): ".$this->handle->connect_error);
			
			if (!$this->handle->select_db($this->config['database']))
				throw new \Z\Core\DB\Exception("Select db ".$this->config['database'].": #".$this->handle->errno." ".$this->handle->error);
			
			if (!$this->handle->set_charset($this->config['charset']))
				throw new \Z\Core\DB\Exception("Set charset: ".$this->config['charset']." #".$this->handle->errno." ".$this->handle->error);
		}
		
		return $this->handle;
	}
	
	public function query($query) {
		$result = $this->connection()->query($query);
		if ($result === false)
			throw new \Z\Core\DB\Exception("#".$this->handle->errno." ".$this->handle->error." [ $query ]");
		return new MySQLi\Result($this->handle, $result);
	}
	
	public function begin() {
		return $this->connection()->begin_transaction();
	}
	
	public function commit() {
		return $this->connection()->commit();
	}
	
	public function rollback() {
		return $this->connection()->rollback();
	}
	
	public function quote($v) {
		return "'".$this->connection()->escape_string($v)."'";
	}
	
	public function quoteTable($v) {
		return "`".strtr($v, [
			"`"		=> "``", 
			"\0"	=> ""
		])."`";
	}
	
	public function quoteColumn($v) {
		if ($v === "*")
			return $v;
		return "`".strtr($v, [
			"`"		=> "``", 
			"\0"	=> ""
		])."`";
	}
	
	public function escape($v) {
		return $this->connection()->escape_string($v);
	}
}

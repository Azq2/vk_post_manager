<?php
namespace Z\Core\DB;

abstract class Provider {
	public abstract function query($query);
	
	public abstract function begin();
	
	public abstract function commit();
	
	public abstract function rollback();
	
	public abstract function quote($v);
	
	public abstract function quoteTable($v);
	
	public abstract function quoteColumn($v);
	
	public abstract function escape($v);
}

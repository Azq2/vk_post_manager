<?php
namespace Z\DB;

abstract class Result implements \Countable, \SeekableIterator, \ArrayAccess {
	const FETCH_ARRAY		= 0;
	const FETCH_ASSOC		= 1;
	const FETCH_OBJECT		= 2;
	
	protected $fetch_mode	= self::FETCH_ASSOC;
	protected $fetch_class	= 'stdClass';
	protected $total_rows	= 0;
	protected $cursor		= 0;
	
	public abstract function affected();
	public abstract function insertId();
	protected abstract function fetchAll();
	
	public function get($field, $default = NULL) {
		$current = $this->current();
		if ($this->fetch_mode == self::FETCH_OBJECT) {
			return isset($current->{$field}) ? $current->{$field} : $default;
		} else {
			return isset($current[$field]) ? $current[$field] : $default;
		}
	}
	
	public function setFetchMode($mode, $class = 'stdClass') {
		$this->fetch_mode = $mode;
		$this->fetch_class = $class;
		return $this;
	}
	
	/*
	 * asArray():					[row1, row2, row3]
	 * asArray(NULL, 'field'):		[row1['field'], row2['field'], row3['field']]
	 * asArray('field'):			[row1['field'] => row1, row2['field'] => row2, row3['field'] => row3]
	 * asArray('key', 'value'):		[row1['key'] => row1['value'], row2['key'] => row2['value'], row3['key'] => row3['value']]
	 * */
	public function asArray($key = NULL, $value = NULL) {
		if ($key === NULL && $value === NULL) {
			// Driver has more efficient way
			return $this->fetchAll();
		} else if ($key === NULL) {
			$offset = $this->cursor;
			$result = [];
			if ($this->fetch_mode == self::FETCH_OBJECT) {
				foreach ($this as $row)
					$result[] = $row->{$value};
			} else {
				foreach ($this as $row)
					$result[] = $row[$value];
			}
			$this->cursor = $offset;
			return $result;
		} else if ($value === NULL) {
			$offset = $this->cursor;
			$result = [];
			if ($this->fetch_mode == self::FETCH_OBJECT) {
				foreach ($this as $row)
					$result[$row->{$key}] = $row;
			} else {
				foreach ($this as $row)
					$result[$row[$key]] = $row;
			}
			$this->cursor = $offset;
			return $result;
		} else {
			$offset = $this->cursor;
			$result = [];
			if ($this->fetch_mode == self::FETCH_OBJECT) {
				foreach ($this as $row)
					$result[$row->{$key}] = $row->{$value};
			} else {
				foreach ($this as $row)
					$result[$row[$key]] = $row[$value];
			}
			$this->cursor = $offset;
			return $result;
		}
	}
	
	/* Countable */
	public function count() {
		return $this->total_rows;
	}
	
	/* ArrayAccess */
	public function offsetExists($offset) {
		return $offset >= 0 && $offset < $this->total_rows;
	}
	
	public function offsetGet($offset) {
		if ($this->seek($offset))
			return $this->current();
		return NULL;
	}
	
	public function offsetSet($offset, $value) {
		throw new \Z\DB\Exception(__CLASS__.' is readonly');
	}
	
	public function offsetUnset($offset) {
		throw new \Z\DB\Exception(__CLASS__.' is readonly');
	}
	
	/* SeekableIterator */
	public function seek($offset) {
		$this->cursor = $offset;
	}
	
	public abstract function current();
	
	public function key() {
		return $this->cursor;
	}
	
	public function next() {
		++$this->cursor;
	}
	
	public function prev() {
		--$this->cursor;
	}
	
	public function rewind() {
		$this->cursor = 0;
	}
	
	public function valid() {
		return $this->cursor >= 0 && $this->cursor < $this->total_rows;
	}
}

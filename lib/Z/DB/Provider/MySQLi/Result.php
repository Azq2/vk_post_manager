<?php
namespace Z\DB\Provider\MySQLi;

class Result extends \Z\DB\Result {
	protected $result;
	protected $affected_rows;
	protected $insert_id;
	protected $current_item = 0;
	
	public function __construct($handle, $result) {
		$this->insert_id = $handle->insert_id;
		$this->affected_rows = $handle->affected_rows;
		
		if (is_object($result)) {
			$this->result = $result;
			$this->total_rows = $result->num_rows;
		}
	}
	
	public function affected() {
		return $this->affected_rows;
	}
	
	public function insertId() {
		return $this->insert_id;
	}
	
	protected function fetchAll() {
		$this->result->data_seek(0);
		
		$result = [];
		switch ($this->fetch_mode) {
			case self::FETCH_OBJECT:
				while ($row = $this->result->fetch_object($this->fetch_class))
					$result[] = $row;
			break;
			
			case self::FETCH_ARRAY:
				while ($row = $this->result->fetch_row())
					$result[] = $row;
			break;
			
			default:
				while ($row = $this->result->fetch_assoc())
					$result[] = $row;
			break;
		}
		
		return $result;
	}
	
	public function current() {
		if ($this->valid()) {
			if (!$this->result->data_seek($this->cursor))
				throw new \Z\DB\Exception("Can't seek result to ".$this->cursor." item, but total rows is ".$this->total_rows);
			
			switch ($this->fetch_mode) {
				case self::FETCH_OBJECT:
					return $this->result->fetch_object($this->fetch_class);
				
				case self::FETCH_ARRAY:
					return $this->result->fetch_row();
				
				default:
					return $this->result->fetch_assoc();
			}
		}
		return NULL;
	}
}

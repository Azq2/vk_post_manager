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
		if (!$this->total_rows)
			return [];
		
		if (!$this->result->data_seek(0))
			throw new \Z\DB\Exception("Can't seek result to 0");
		
		switch ($this->fetch_mode) {
			case self::FETCH_OBJECT:
				$result = [];
				while ($row = $this->result->fetch_object($this->fetch_class, $this->fetch_params))
					$result[] = $row;
				return $result;
			break;
			
			case self::FETCH_ARRAY:
				return $this->result->fetch_all(MYSQLI_NUM);
			break;
			
			default:
				return $this->result->fetch_all(MYSQLI_ASSOC);
			break;
		}
	}
	
	public function current() {
		if ($this->valid()) {
			if (!$this->result->data_seek($this->cursor))
				throw new \Z\DB\Exception("Can't seek result to ".$this->cursor." item, but total rows is ".$this->total_rows);
			
			switch ($this->fetch_mode) {
				case self::FETCH_OBJECT:
					return $this->result->fetch_object($this->fetch_class, $this->fetch_params);
				
				case self::FETCH_ARRAY:
					return $this->result->fetch_row();
				
				default:
					return $this->result->fetch_assoc();
			}
		}
		return NULL;
	}
}

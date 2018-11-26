<?php
namespace Z\Core\DB;

class Builder extends \Z\Core\DB\Query {
	protected function compileSet($db, $set, $set_expr) {
		$result = [];
		
		foreach ($set as $k => $v)
			$result[] = $db->quoteColumn($k)." = ".$db->quote($v);
		
		foreach ($set_expr as $k => $v)
			$result[] = $db->quoteColumn($k)." = ".$db->quoteColumn($k)." ".$v[0]." ".$db->quote($v[1]);
		
		return implode(", ", $result);
	}
	
	protected function compileOnDuplicate($db, $set) {
		$result = [];
		
		foreach ($set as $k => $v) {
			if ($v[0] === "VALUES") {
				$result[] = $db->quoteColumn($k)." = VALUES(".$db->quoteColumn($k).")";
			} elseif ($v[0] === "=") {
				$result[] = $db->quoteColumn($k)." = ".$db->quote($v[1]);
			} else {
				$result[] = $db->quoteColumn($k)." = ".$db->quoteColumn($k)." ".$v[0]." ".$db->quote($v[1]);
			}
		}
		
		return implode(", ", $result);
	}
	
	protected function compileJoinPredicate($db, $predicate) {
		$result = [];
		$first_item = true;
		
		foreach ($predicate as $item) {
			if (is_array($item[1])) {
				if (!$first_item)
					$result[] = " ".$item[0]." ";
				$first_item = false;
				
				$result[] = $db->quoteColumn($item[1][0])." ".$item[1][1]." ".$db->quoteColumn($item[1][2]);
			} else {
				if (!$first_item && $item[1] != ")")
					$result[] = " ".$item[0]." ";
				$first_item = ($item[1] == "(");
				$result[] = $item[1];
			}
		}
		
		return implode("", $result);
	}
	
	protected function compilePredicate($db, $predicate) {
		$result = [];
		$first_item = true;
		
		foreach ($predicate as $item) {
			if (is_array($item[1])) {
				if (!$first_item)
					$result[] = " ".$item[0]." ";
				$first_item = false;
				
				switch (strtoupper($item[1][1])) {
					case "BETWEEN":
						$result[] = $db->quoteColumn($item[1][0])." BETWEEN ".$db->quote($item[1][2][0])." AND ".$db->quote($item[1][2][1]);
					break;
					
					default:
						if ($item[1][2] === NULL && $item[1][1] === "=") {
							$result[] = $db->quoteColumn($item[1][0])." IS NULL";
						} elseif ($item[1][2] === NULL && ($item[1][1] === "!=" || $item[1][1] === "<>")) {
							$result[] = $db->quoteColumn($item[1][0])." IS NOT NULL";
						} elseif (is_array($item[1][2])) {
							$val = array_map([$db, 'quote'], $item[1][2]);
							$result[] = $db->quoteColumn($item[1][0])." ".$item[1][1]." (".implode(", ", $val).")";
						} else {
							$result[] = $db->quoteColumn($item[1][0])." ".$item[1][1]." ".$db->quote($item[1][2]);
						}
					break;
				}
			} else {
				if (!$first_item && $item[1] != ")")
					$result[] = " ".$item[0]." ";
				$first_item = ($item[1] == "(");
				$result[] = $item[1];
			}
		}
		
		return implode("", $result);
	}
}

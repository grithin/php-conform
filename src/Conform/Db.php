<?
namespace Grithin\Conform;

class Db{

	function field_get_validaters($fullName,$info){
		$field = array_pop(explode('.',$fullName));
		$validaters = [];
		//speical handling for 'created' and 'updated'
		if(
			($field == 'record_created' || $field == 'record_updated') &&
			($info['type'] == 'datetime' || $info['type'] == 'date')
		){
			return [];
		}
		$validaters[] = 'f.string';
		if(empty($info['nullable']) && empty($info['autoIncrement'])){
			if($info['default'] === null){
				//column must be present
				$validaters[] = '!v.filled';
			}else{
				//there's a default, when missing, set to default
				$validaters[] = ['?!v.filled'];
			}
		}else{
			//for nullable columns, empty inputs (0 character strings) are null
			$validaters[] = array('f.to_default',null);
			//column may not be present.  Only validate if present
			$validaters[] = '?!v.filled';
		}
		switch($info['type']){
			case 'datetime':
			case 'timestamp':
				$validaters[] = '!v.date';
				$validaters[] = 'f.datetime';
			break;
			case 'date':
				$validaters[] = '!v.date';
				$validaters[] = 'f.date';
			break;
			case 'text':
				if(!empty($info['limit'])){
					$validaters[] = '!v.length_range|0;'.$info['limit'];
				}
			break;
			case 'int':
				if($info['limit'] == 1){//boolean value
					$validaters[] = 'f.bool';
					$validaters[] = 'f.int';
				}else{
					$validaters[] = 'f.trim';
					$validaters[] = '!v.int';
				}
			break;
			case 'decimal':
			case 'float':
				$validaters[] = 'f.trim';
				$validaters[] = '!v.float';
			break;
		}
		return $validaters;
	}
	function table_get_validaters($table, $db=null){
		if(!$db){
			$db = \Grithin\Db::primary();
		}
		$table_info = $db->tableInfo($table);
		$validaters = [];
		foreach($table_info['columns'] as $key=>$column_info){
			$validaters[$key] = self::field_get_validaters($key, $column_info);
		}
		return $validaters;
	}
}
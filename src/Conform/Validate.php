<?
namespace Grithin\Conform;

use \Grithin\Debug;
use \Grithin\Time;
use \Grithin\Arrays;
use \Grithin\Tool;

class Validate{
	function __construct($options=[]){
		$this->options = Arrays::merge(['input_timezone'=>'UTC','target_timezone'=>'UTC'], $options);
	}

	/// true or false return instead of exception.  Calling statically won't work on methods requiring $this (date, time)
	function test($method, $value){
		try{
			if($this){
				call_user_func_array(array($this, $method), array_slice(func_get_args(),1));
			}else{
				call_user_func_array(array(self, $method), array_slice(func_get_args(),1));
			}

			return true;
		}catch(\Exception $e){
			return false;
		}
	}
	static function error($details=''){
		if(!is_scalar($details)){
			Debug::toss($details);
		}else{
			throw new \Exception($details);
		}
	}

//+	basic validators{

	function blank($v){
		if($v !== ''){
			self::error();
		}
		return $v;
	}
	function not_blank($v){
		if($v === ''){
			self::error();
		}
		return $v;
	}
	/// comparison using `!=`
	function loose_value($v, $x){
		if($v != $x){
			self::error();
		}
		return $v;
	}
	function value($v, $x){
		if($v !== $x){
			self::error();
		}
		return $v;
	}
	function true($v){
		if(!(bool)$v){
			self::error();
		}
		return $v;
	}
	function false($v){
		if((bool)$v){
			self::error();
		}
		return $v;
	}
	function filled($v){
		if($v === '' || $v === null){
			self::error();
		}
		return $v;
	}
	function int($v){
		if(!Tool::isInt($v)){
			self::error();
		}
		return $v;
	}
	function float($v){
		if(filter_var($v, FILTER_VALIDATE_FLOAT) === false){
			self::error();
		}
		return $v;
	}

	function regex($v,$regex){
		if(!preg_match($regex, $v)){
			self::error();
		}
		return $v;
	}
	/// $v is a key in $hash
	function key_in($v, $hash){
		if(!isset($hash[$value])){
			self::error();
		}
		return $v;
	}

	/// $v is in $a
	function in($v, $a){
		if(!in_array($v,$a)){
			self::error();
		}
		return $v;
	}
	function email($v){
		if(!filter_var($v, FILTER_VALIDATE_EMAIL)){
			self::error();
		}
		return $v;
	}
	// Ex: joe johnson <joe@bob.com>
	function emailLine($v){
		$v = trim($v);
		if(!self::test('email', $v)){
			if(preg_match('@^[^<]*?<[^>]+>$@', $v)){ # it matches the form of a name + email line
				$email = Filter::email($v);
				if(!self::test('email', $email)){ # ensure the address part is conforming
					self::error();
				}
				return $v;
			}else{
				self::error();
			}
		}
		return $v;
	}
	function url($v){
		if(!filter_var($v, FILTER_VALIDATE_URL)){
			self::error();
		}
		# the native filter doesn't even check if there is at least one dot (tld detection)
		if(strpos($v,'.') == false){
			self::error();
		}
		return $v;
	}

#++ Numbers {

	function range($v,$min,$max){
		self::min($v, $min);
		self::max($v, $max);
		return $v;
	}
	function min($v, $min){
		if((float)$v < (float)$min){
			self::error();
		}
		return $v;
	}
	function gte($v, $min){
		return self::min($v,$min);
	}
	function gt($v, $min){
		if((float)$v < (float)$min){
			self::error();
		}
		return $v;
	}
	function max($v, $max){
		if((float)$v > (float)$max){
			self::error();
		}
		return $v;
	}
	function lte($v, $max){
		return self::max($v, $max);
	}
	function lt($v, $max){
		if((float)$v >= (float)$max){
			self::error();
		}
		return $v;
	}

#++ }

	function length($v, $length){
		if(strlen($v) != $length){
			self::error();
		}
		return $v;
	}
	function length_min($v, $min){
		if(strlen($v) < $min){
			self::error();
		}
		return $v;
	}
	function length_gte($v, $min){
		return self::length_min($v, $min);
	}
	function length_gt($v, $min){
		if(strlen($v) <= $min){
			self::error();
		}
		return $v;
	}
	function length_max($v, $max){
		if(strlen($v) > $max){
			self::error();
		}
		return $v;
	}
	function length_lte($v, $max){
		return self::length_gte($v, $max);
	}
	function length_lt($v, $max){
		if(strlen($v) >= $max){
			self::error();
		}
		return $v;
	}
	function length_range($v, $min, $max){
		self::length_min($v, $min);
		self::length_max($v, $max);
		return $v;
	}
	function timezone($v){
		try{
			return new \DateTimeZone($v);
		}catch(\Exception $e){
			self::error();
		}
	}
	function time($v){
		try{
			return new Time($v);
		}catch(\Exception $e){
			self::error();
		}
	}
	# validate that a string of form 'YYYY-mm-dd' is an actual date
	function date($v){
		list($y,$m,$d) = explode('-', $v);
		if(!Time::validate($y,$m,$d)){
			self::error();
		}
		return $x;
	}
	# alias
	function datetime(){
		return call_user_func_array([$this,'time'], func_get_args());
	}
	function time_max($v, $max){
		if($v > new Time($max)){
			self::error(['details'=>$max]);
		}
		return $v;
	}
	function time_min($v, $min){
		if($v > new Time($min)){
			self::error();
		}
		return $v;
	}

	/**
	@param	mimes	array of either whole mimes "part/part", or the last part of the mime "part"

	@note To get mime: $mime = \Grithin\File::mime($_FILES[$name]['tmp_name']);
	*/
	function mime($v,$mimes){
		$mimes = Arrays::toArray($mimes);
		foreach($mimes as $matchMime){
			if(preg_match('@'.preg_quote($matchMime).'$@', $v)){
				return $v;
			}
		}
		self::error();
	}
	/// inverter function.  May want to use `~` instead
	function not_mime($v,$mimes){
		if(self::test('mime', $v, $mimes)){
			self::error();
		}
		return $v;
	}

//+	}

	function callback($v,$callback){
		return $callback($v);
	}
	function title($v){
		$v = Filter::regex_remove($v,'@[^a-z0-9_\- \']@i');
		return self::length_min($v,2);
	}
	function ip4($v){
		return self::regex($v,'@[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}@');
	}

	function age_max($v, $max){
		self::max(Filter::init()->age($v), $max);
		return $v;
	}
	function age_min($v, $min){
		self::min(Filter::init()->age($v), $min);
		return $v;
	}
	function password($v){
		return self::length_range($v,3,50);
	}
	function name($v){
		if(!preg_match('@^[a-z \']{2,}$@i',$v)){
			self::error();
		}
		return $v;
	}
	/// 5 or 5+4 zip
	function zip($v){
		if(!preg_match("/^([0-9]{5})(-[0-9]{4})?$/i",$v)) {
			self::error();
		}
		return $v;
	}
	function phone($v){
		$v = Filter::digits($v);
		self::filled($v);

		if(strlen($v) == 11 && substr($v,0,1) == 1){
			$v = substr($v,1);
		}
		if(strlen($v) == 7){
			self::error(['type'=>'phone_area_code']);
		}
		if(strlen($v) != 10){
			self::error();
		}
		return $v;
	}
	function international_phone($v){
		$v = Filter::digits($v);
		self::filled($v);

		if(strlen($v) < 11){
			self::error();
		}
		if(strlen($v) > 14){
			self::error();
		}
		return $v;
	}


	/// checks that html tags have tag integrity
	/// @note haven't used since 2007, no idea if it works
	function htmlTagContextIntegrity($value){
		self::$tagHierarchy = [];
		preg_replace_callback('@(</?)([^>]+)(>|$)@',array(self,'htmlTagContextIntegrityCallback'),$value);
		//tag hierarchy not empty, something wasn't closed
		if(self::$tagHierarchy){
			self::error();
		}
		return $value;
	}
	static $tagHierarchy = [];
	function htmlTagContextIntegrityCallback($match){
		preg_match('@^[a-z]+@i',$match[2],$tagMatch);
		$tagName = $tagMatch[0];

		if($match[1] == '<'){
			//don't count self contained tags
			if(substr($match[2],-1) != '/'){
				self::$tagHierarchy[] = $tagName;
			}
		}else{
			$lastTagName = array_pop(self::$tagHierarchy);
			if($tagName != $lastTagName){
				self::error();
			}
		}
	}

	function db_in_table_field($v,$table,$field, $db){
		if(!$db->check($table, [$field=>$v])){
			self::error();
		}
		return $v;
	}

	//++ relies on something like Grithin/Db {
	function db_in_table($v,$table, $db){
		return self::db_in_table_field($v, $table, 'id', $db);
	}
	function db_not_in_table(&$v,$table,$field='id'){
		if(self::test('db_in_table_field', $v, $table, 'id', $db)){
			self::error();
		}
		return $v;
	}
	//++ }
}

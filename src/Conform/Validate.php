<?php
namespace Grithin\Conform;

use \Grithin\Debug;
use \Grithin\Time;
use \Grithin\Arrays;
use \Grithin\Tool;
use \Grithin\Strings;

class Validate{

	use \Grithin\Traits\SingletonDefault;

	function __construct($options=[]){
		$this->options = Arrays::merge(['input_timezone'=>'UTC','target_timezone'=>'UTC'], $options);
		$this->filter = new Filter($this->options);
	}

	/** true or false return instead of exception.  Calling statically won't work on methods requiring $this (date, time) */
	public function test($method, $value){
		$method_arguments = array_slice(func_get_args(),1);
		$pseudo_conform = new ValidateTestConform;;
		$method_arguments[] = $pseudo_conform;
		call_user_func_array(array($this, $method), $method_arguments);
		if($pseudo_conform->error){
			return false;
		}
		return true;
	}
	static function error($details=''){
		if(!is_scalar($details)){
			Debug::toss($details);
		}else{
			throw new \Exception($details);
		}
	}


//+	basic validators{

	function blank($v, $Conform){
		if($v !== ''){
			$Conform->error();
		}
		return $v;
	}
	function not_blank($v, $Conform){
		if($v === ''){
			$Conform->error();
		}
		return $v;
	}
	/** comparison using `!=` */
	function loose_value($v, $x, $Conform){
		if($v != $x){
			$Conform->error();
		}
		return $v;
	}
	function value($v, $x, $Conform){
		if($v !== $x){
			$Conform->error();
		}
		return $v;
	}
	function true($v, $Conform){
		if(!(bool)$v){
			$Conform->error();
		}
		return $v;
	}
	function false($v, $Conform){
		if((bool)$v){
			$Conform->error();
		}
		return $v;
	}
	/** check if the field exists in either the current input or the curernt output (since a rule can generate a new field) */
	function exists($v, $Conform){
		if(!array_key_exists($Conform->field, $Conform->input) and !array_key_exists($Conform->field, $Conform->output)){
			$Conform->error();
		}
		return $v;
	}
	function filled($v, $Conform){
		if($v === '' || $v === null){
			$Conform->error();
		}
		return $v;
	}
	function int($v, $Conform){
		if(!Tool::is_int($v)){
			$Conform->error();
		}
		return $v;
	}
	function absolute($v, $Conform){
		if(abs($v) != $v){
			$Conform->error();
		}
		return $v;
	}
	/** combinatory validator: is int? is absolute value? is not zero? */
	function id($v, $Conform){
		self::int($v);
		self::absolute($v);
		self::true($v);
		return $v;
	}
	function float($v, $Conform){
		if(filter_var($v, FILTER_VALIDATE_FLOAT) === false){
			$Conform->error();
		}
		return $v;
	}
	function characters_acceptible($v, $regex, $Conform){
		preg_match_all('/[^'.Strings::preg_quote_delimiter($regex).']/', $v, $matches);
		if(!empty($matches[0])){
			throw new \Exception('Unacceptable characters: '.implode($matches[0]));
		}
		return  $v;
	}
	function regex($v,$regex, $Conform){
		if(!preg_match($regex, $v)){
			$Conform->error();
		}
		return $v;
	}
	/** $v is a key in $hash */
	function key_in($v, $hash, $Conform){
		if(!isset($hash[$value])){
			$Conform->error();
		}
		return $v;
	}

	/** $v is in $a */
	function in($v, $a, $Conform){
		if(!in_array($v,$a)){
			$Conform->error();
		}
		return $v;
	}
	function email($v, $Conform){
		if(!filter_var($v, FILTER_VALIDATE_EMAIL)){
			$Conform->error();
		}
		return $v;
	}
	/** Ex: joe johnson <joe@bob.com> */
	function emailLine($v, $Conform){
		$v = trim($v);
		if(!self::test('email', $v)){
			if(preg_match('@^[^<]*?<[^>]+>$@', $v)){ # it matches the form of a name + email line
				$email = $this->filter->email($v);
				if(!self::test('email', $email)){ # ensure the address part is conforming
					$Conform->error();
				}
				return $v;
			}else{
				$Conform->error();
			}
		}
		return $v;
	}
	function url($v, $Conform){
		if(!filter_var($v, FILTER_VALIDATE_URL)){
			$Conform->error();
		}
		# the native filter doesn't even check if there is at least one dot (tld detection)
		if(strpos($v,'.') == false){
			$Conform->error();
		}
		return $v;
	}

#++ Numbers {

	function range($v,$min,$max, $Conform){
		self::min($v, $min);
		self::max($v, $max);
		return $v;
	}
	function min($v, $min, $Conform){
		if((float)$v < (float)$min){
			$Conform->error();
		}
		return $v;
	}
	function gte($v, $min, $Conform){
		return self::min($v,$min);
	}
	function gt($v, $min, $Conform){
		if((float)$v < (float)$min){
			$Conform->error();
		}
		return $v;
	}
	function max($v, $max, $Conform){
		if((float)$v > (float)$max){
			$Conform->error();
		}
		return $v;
	}
	function lte($v, $max, $Conform){
		return self::max($v, $max);
	}
	function lt($v, $max, $Conform){
		if((float)$v >= (float)$max){
			$Conform->error();
		}
		return $v;
	}

#++ }

	function length($v, $length, $Conform){
		if(mb_strlen($v) != $length){
			$Conform->error();
		}
		return $v;
	}
	function length_min($v, $min, $Conform){
		if(mb_strlen($v) < $min){
			$Conform->error();
		}
		return $v;
	}
	function length_gte($v, $min, $Conform){
		return self::length_min($v, $min);
	}
	function length_gt($v, $min, $Conform){
		if(mb_strlen($v) <= $min){
			$Conform->error();
		}
		return $v;
	}
	function length_max($v, $max, $Conform){
		if(mb_strlen($v) > $max){
			$Conform->error();
		}
		return $v;
	}
	function length_lte($v, $max, $Conform){
		return self::length_gte($v, $max);
	}
	function length_lt($v, $max, $Conform){
		if(mb_strlen($v) >= $max){
			$Conform->error();
		}
		return $v;
	}
	function length_range($v, $min, $max, $Conform){
		self::length_min($v, $min);
		self::length_max($v, $max);
		return $v;
	}
	function timezone($v, $Conform){
		try{
			return new \DateTimeZone($v);
		}catch(\Exception $e){
			$Conform->error();
		}
	}
	function time($v, $Conform){
		try{
			return new Time($v);
		}catch(\Exception $e){
			$Conform->error();
		}
	}
	/** validate that a string of form 'YYYY-mm-dd' is an actual date */
	function date_exists($v, $Conform){
		list($y,$m,$d) = explode('-', $v);
		if(!Time::validate($y,$m,$d)){
			$Conform->error();
		}
		return $v;
	}
	/** alias for `time` */
	function datetime(){
		return call_user_func_array([$this,'time'], func_get_args());
	}
	/** return a Time object, with non-date parted set to 00:00:00 */
	function date(){
		$Time =  call_user_func_array([$this,'time'], func_get_args());
		return $Time->day_start();
	}
	function time_max($v, $max, $Conform){
		if($v > new Time($max)){
			$Conform->error(['details'=>$max]);
		}
		return $v;
	}
	function time_min($v, $min, $Conform){
		if($v > new Time($min)){
			$Conform->error();
		}
		return $v;
	}

	/**
	@param	mimes	array of either whole mimes "part/part", or the last part of the mime "part"

	@note To get mime: $mime = \Grithin\File::mime($_FILES[$name]['tmp_name']);
	*/
	function mime($v,$mimes, $Conform){
		$mimes = Arrays::toArray($mimes);
		foreach($mimes as $matchMime){
			if(preg_match('@'.preg_quote($matchMime).'$@', $v)){
				return $v;
			}
		}
		$Conform->error();
	}
	/** inverter function.  May want to use `~` instead */
	function not_mime($v,$mimes, $Conform){
		if(self::test('mime', $v, $mimes)){
			$Conform->error();
		}
		return $v;
	}

//+	}

	function callback($v,$callback, $Conform){
		return $callback($v);
	}
	function title($v, $Conform){
		$v = $this->filter->regex_remove($v,'@[^a-z0-9_\- \']@i');
		return self::length_min($v,2);
	}
	function ip4($v, $Conform){
		return self::regex($v,'@[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}@');
	}
	function ip($v, $Conform){
		if(@inet_pton($v) === false){
			$Conform->error();
		}
		return $v;
	}

	function age_max($v, $max, $Conform){
		self::max($this->filter->age($v), $max);
		return $v;
	}
	function age_min($v, $min, $Conform){
		self::min($this->filter->age($v), $min);
		return $v;
	}
	function password($v, $Conform){
		return self::length_range($v,3,50);
	}
	function name($v, $Conform){
		if(!preg_match('@^[a-z \']{2,}$@i',$v)){
			$Conform->error();
		}
		return $v;
	}
	/** 5 or 5+4 zip */
	function zip($v, $Conform){
		if(!preg_match("/^([0-9]{5})(-[0-9]{4})?$/i",$v)) {
			$Conform->error();
		}
		return $v;
	}

	/** USA phone */
	function phone($v, $Conform){
		$v = $this->filter->digits($v);
		self::filled($v);

		if(mb_strlen($v) == 11 && substr($v,0,1) == '1'){
			$v = substr($v,1);
		}
		if(mb_strlen($v) == 7){
			$Conform->error(['type'=>'phone_area_code']);
		}
		if(mb_strlen($v) != 10){
			$Conform->error();
		}
		return $v;
	}
	/** checks of the phone matches the international format - (converts spaces to "-") */
	function phone_international_format($v, $Conform){
		$v = trim($v);
		$v = preg_replace('@ +@', '-', $v);
		if(!preg_match('@^\+[0-9]+\-([0-9]+\-?)+$@', $v)){
			$Conform->error();
		}
		return $v;
	}
	/** Filter and ensure phone number.  Returns number including any non-numbers as spaces, condensed when in sequence */
	function international_phone($v, $Conform){
		$digits = $this->filter->digits($v);
		self::filled($digits);

		# Smallest international phone number: For Solomon Islands its 5 for fixed line phones. - Source (country code 677)
		if(mb_strlen($digits) < 8){
			$Conform->error();
		}
		# max https://www.wikiwand.com/en/E.164
		if(mb_strlen($digits) > 15){
			$Conform->error();
		}

		return $this->filter->phone($v);
	}
	/** either find a "+" in the string, or prefix with "1" (as in "+1" for USA) */
	function international_phone_plus_or_us($v, $Conform){
		if(strpos($v, '+') === false){
			$v = '1'.$v;
		}
		return self::international_phone($v);
	}
	function phone_possible($v, $Conform){
		$digits = $this->filter->digits($v);
		self::filled($digits);

		# Smallest international phone number: For Solomon Islands its 5 for fixed line phones. - Source (country code 677)
		if(mb_strlen($digits) < 5){
			$Conform->error();
		}
		# max https://www.wikiwand.com/en/E.164
		if(mb_strlen($digits) > 15){
			$Conform->error();
		}
		return $this->filter->phone($v);;
	}

	/** checks that html tags have tag integrity */
	/** @note haven't used since 2007, no idea if it works */
	function htmlTagContextIntegrity($value, $Conform){
		self::$tagHierarchy = [];
		preg_replace_callback('@(</?)([^>]+)(>|$)@',array(__CLASS__, 'htmlTagContextIntegrityCallback'),$value);
		//tag hierarchy not empty, something wasn't closed
		if(self::$tagHierarchy){
			$Conform->error();
		}
		return $value;
	}
	static $tagHierarchy = [];
	function htmlTagContextIntegrityCallback($match, $Conform){
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
				$Conform->error();
			}
		}
	}

	function db_in_table_field($v,$table,$field, $db, $Conform){
		if(!$db->exists($table, [$field=>$v])){
			$Conform->error();
		}
		return $v;
	}


	//++ relies on something like Grithin/Db {
	function db_in_table($v,$table, $db, $Conform){
		return self::db_in_table_field($v, $table, 'id', $db);
	}
	function db_not_in_table(&$v,$table,$field='id', $Conform){
		if(self::test('db_in_table_field', $v, $table, 'id', $db)){
			$Conform->error();
		}
		return $v;
	}
	//++ }

	//+ relies on something like Grithin/phpbase {
	function json($v, $Conform){
		return call_user_func_array([$this,'json_is'], func_get_args());
	}
	function json_is($v, $Conform){
		Tool::json_decode($v);
		return $v;
	}
	function json_parse($v, $Conform){
		return Tool::json_decode($v);
	}
	/** either input is data structure, which will be conformed to JSON, or input already exists as JSON */
	function json_ensure($x, $Conform){
		if(is_array($x)){
			return Tool::json_encode($x);
		}else{
			return call_user_func_array([$this,'json_is'], func_get_args());
		}
	}
	//+ }
	function is_array($v, $Conform){
		if(!is_array($v)){
			$Conform->error();
		}
		return $v;
	}
	function count_min($x, $min, $Conform){
		self::is_array($x);
		if(count($x) < $min){
			$Conform->error();
		}
		return $x;
	}
	function count_max($x, $max, $Conform){
		self::is_array($x);
		if(count($x) > $max){
			$Conform->error();
		}
		return $x;
	}
}

class ValidateTestConform{
	public $error = false;
	public function error($details, $Conform){
		$this->error = true;
	}
}

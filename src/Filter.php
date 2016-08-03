<?
namespace Grithin\Conform;

use \Grithin\Debug;
use \Grithin\Time;
use \Grithin\Arrays;

class Filter{
	use \Grithin\SingletonDefault;
	public $options;
	/*
	@param	options	[
			input_timezone: < converted from >
			target_timezone: < converted to >
		]
	*/
	function __construct($options=[]){
		$this->options = Arrays::merge(['input_timezone'=>'UTC','target_timezone'=>'UTC'], $options);
	}
	/// convert to a string.  If array, traverse down on first elements
	function string($v){
		while(is_array($v)){
			$v = array_shift($v);
		}
		return (string)$v;
	}
	function int($v){
		return (int)$v;
	}
	function float($v){
		return (float)$v;
	}
	# alias
	function decimal($v){
		return $this->float($v);
	}
	function bool($v){
		return (bool)$v;
	}
	/// absolute integer
	function abs($v){
		return abs($v);
	}
	/// filter all but digits
	function digits($v){
		return preg_replace('@[^0-9]@','',$v);
	}
	/// regex removal
	function regex_remove($v, $regex){
		return preg_replace($regex,'',$v);
	}
	/// regex replacement
	function regex($v, $regex, $replacement){
		return preg_replace($regex,$replacement,$v);
	}
	/// prefix with http:// if not already, and trim
	function url($v){
		$v = trim($v);
		if(substr($v,0,4) != 'http'){
			return 'http://'.$v;
		}
		return $v;
	}
	# a vestige, the preventage of using "array" as a function name
	function to_array($v){
		return (array)$v;	}
	/// diminish arbitrarily deep array into a flat array using "$this->string"
	function flat_array($v){
		$v = (array) $v;
		foreach($v as &$v2){
			if(is_array($v2)){
				$v2 = $this->string($v2);
			}
		}
		return $v;
	}
	/// format an english name
	function name($v){
		$v = $this->trim($v);
		$v = $this->regex($v,'@ +@',' ');
		# handle the case of name in the format `last_name, first_name`
		$v = preg_split('@, @', $v);
		array_reverse($v);
		$v = implode(' ',$v);
		$v = $this->regex_replace($v,'@[^a-z \']@i');
		return $v;
	}
	function trim($v){
		return trim($v);
	}
	function time($v){
		return (new Time($v, $this->options['input_timezone']))->setZone($this->options['target_timezone']);
	}
	function time_from_tz($v, $in_tz){
		return (new Time($v, $in_tz))->setZone($this->options['target_timezone']);
	}
	function time_to_tz($v, $tz){
		return $v->setZone($tz);
	}
	function time_to_date($v){
		return $v->date();
	}
	function time_to_datetime($v){
		return $v->datetime();
	}
	function time_age($v){
		return (new Time($v))->diff(new Time('now'));
	}
	function age($v){
		return $this->time_age($this->time($v));
	}
	function date($v){
		return $this->time($v)->date;
	}
	function date_from_tz($v, $in_tz){
		return $this->time_from_tz($v, $in_tz)->date;
	}
	function date_to_tz($v, $out_tz){
		return $this->time($v)->setZone($out_tz)->date;
	}
	function datetime($v){
		return $this->time($v)->datetime;
	}
	function datetime_from_tz($v, $in_tz){
		return $this->time_from_tz($v, $in_tz)->datetime;
	}
	function datetime_to_tz($v, $out_tz){
		return $this->time($v)->setZone($out_tz)->date;
	}
	/// if null or '', use default
	function to_default($v, $default){
		if($v === null || $v === ''){
			return $default;
		}
		return $v;
	}
	/// extract the email address out of a string like `bob bill <bob_bill@bob.com>`
	function email($v){
		preg_match('@<([^>]+)>@',$v,$match);
		if(!$match){
			return $v;
		}
		$email = $match[1];
		return $email;
	}
	function br_to_nl($v){
		return preg_replace('@<br */>@i',"\n",$value);
	}
	/// on fields which may contain html, if they contain certain html, don't do nl to br
	function conditional_br_to_nl($v){
		if(!preg_match('@<div|<p|<table@',$v)){
			return $this->br_to_nl($v);
		}
		return $v;
	}
	/// get the amount to 2 precision from an arbitrary string
	function currency($value){
		$value = preg_replace('@[^\-0-9.]@','',$value);
		$value = round((float)$value,2);
	}
	function null($v){
		return null;
	}
	/// change the value
	function value($v, $new){
		return $new;
	}
	function callback($v, $callback){
		return $callback($v);
	}

	//++ context-reliant functions  {

	/// place the value into a new input key
	function rekey($v, $key, $context){
		$context['instance']->input[$key] = $v;
	}
	/// blanks a field
	function blank($v){
		return '';
	}

	//++ }
}

<?
namespace Grithin;

use \Exception;

class Conform{
	public $input;///< input on construct
	public $output;///< output after rules

	public $conformers;
	/// add a conformer class
	function add_conformer($name, $instance){
		$this->conformers[$name] = $instance;
	}

	function __construct($input, $options=[]){
		$this->input = $input;
		$this->output = [];
	}
	static function standard_instance($input=null){
		if($input === null){
			$input = self::input();
		}
		$conform = new self($input);
		$conform->add_conformer('f',\Grithin\Conform\Filter::init());
		$conform->add_conformer('v',new \Grithin\Conform\Validate);
		return $conform;
	}

	/**
	With input, must consider that:
	-	error message order may be desired
	-	errors may be tied to field names
	-	an error may be tied to multiple fields

	As a consequence of these considerations, the following structure of the error data is used:

	[{message:<text message>, fields:[<field name>, ...]}, ...]

	Additional error attributes may be added as needed.
	Additionally, the text message part may have tokens for replacement or parsing
	*/
	public $errors = [];

	/// get $_GET with the addition of allowing a "_json" key to define a structured GET input
	static function get(){
		$get = $_GET;
		if($get['_json']){
			$get = \Grithin\Arrays::merge($get['_json'], json_decode((string)$get['_json'],true));
		}
		return $get;
	}
	/// get $_POST, also allowing for "content_type: json"
	static function post(){
		if(!$_POST && substr($_SERVER['CONTENT_TYPE'],0,16) == 'application/json'){
			$_POST = json_decode(file_get_contents('php://input'),true);
		}
		return $_POST;
	}
	/// a merge of self::get, and self::post, with preference to post
	static function input(){
		return array_merge(self::get(), self::post());
	}

	/// attributes spelled out as parameters
	function add_error($message, $type=null, $fields=[], $rule=null, $params=null){
		$error = ['message'=>$message];
		if($type){
			$error['type'] = $type;
		}
		$error['fields'] = (array)$fields;
		if($rule){
			$error['rule'] = $rule;
		}
		if($params){
			$error['params'] = $params;
		}
		$this->errors[] = $error;
	}
	/// add an error to instance errors array
	function error($details,$fields=[]){
		if(!$details){ # discard empty errors
			return;
		}

		$error = ['fields'=>(array)$fields];
		if(is_array($details)){
			$error = array_merge($error, $details);
		}else{
			$error['message'] = $details;	}

		$this->errors[] = $error;
	}

	/// getter
	function errors(){
		return $this->errors;
	}
	function remove_errors(){
		$this->errors = [];
	}
	/// clear errors and output
	function clear(){
		$this->errors = [];
		$this->output = [];
	}

	/// get all errors that include a field
	function field_errors($field){
		return $this->fields_errors([$field]);
	}
	/// get all errors that include any of "fields"
	function fields_errors($fields){
		if(!is_array($fields)){
			throw new Exception('$fields param must be an array of fields');
		}
		$found = [];

		foreach($this->errors as $error){
			if(array_intersect($fields,$error['fields'])){
				$found[] = $error;
			}
		}
		return $found;
	}

	/**
	@param	rules	string or array
		Ordered mapping of rules to field names

		Rules of one field name are separated in the form
		-	'rule rule rule', wherein the split will match `[\s,]+`
		-	[rule, rule, rule]

		Every rule consists of at most three parts
		-	prefix
		-	fn
		-	parameters

		Conforming to one of
		-	string only: `prefix + fn_path + '|' + param1 + ';' + param2`
		-	flexible parameters: `[prefix + fn_path`, param1, param2,...]`
		-	anonymous function: `[[prefix, fn], param1, param2,...]`

		Notice, as a string, parameters are separated with ";"

		The prefix can be some combination of the following
		-	"!" to break on error with no more rules for that field should be applied
		-	"!!" to break on error with no more rules for any field should be applied
		-	"?" to indicate the validation is optional, and not to throw an error (useful when combined with '!' => '?!v.filled,email')
		-	"~" to indicate if the validation does not fail, then there was an error
		-	"&" to indicate code should break if there were any previous errors on that field
		-	"&&" to indicate code should break if there were any previous errors on any field in the validate run

	*/
	/* Ex
	$v = Conform::compile_rules('!!?bob|sue;bill;jan !!?bill|joe');
	*/
	static function compile_rules($rules){
		$compiled_rules = [];
		if(is_string($rules)){
			$rules = preg_split('/[\s,]+/', $rules);
			$rules = Arrays::remove($rules); # remove empty rules, probably unintended by spacing after or before
		}
		foreach($rules as $rule){
			$compiled_rules[] = self::compile_rule($rule);
		}
		return $compiled_rules;
	}
	/* Ex
	$v = Conform::compile_rule('!!?bob|sue;bill;jan');
	$v = Conform::compile_rule(['!!?bob','sue','bill','jan']);
	$v = Conform::compile_rule([['!!?','bob'],'sue','bill','jan']);
	*/
	static function compile_rule($rule){
		if(is_string($rule)){
			$parsed_rule = self::parse_rule_text($rule);
			$rule_obj['flags'] = self::parse_flags($parsed_rule['flag_string']);
			$rule_obj['params'] = self::parse_params($parsed_rule['params_string']);
			$rule_obj['fn_path'] = $parsed_rule['fn_path'];
			return $rule_obj;
		}elseif(is_array($rule)){
			if(is_string($rule[0])){
				$parsed_rule = self::parse_rule_text($rule[0]);
				$rule_obj['flags'] = self::parse_flags($parsed_rule['flag_string']);
				$rule_obj['fn_path'] = $parsed_rule['fn_path'];
			}elseif(is_array($rule[0])){
				$rule_obj['flags'] = self::parse_flags($rule[0][0]);
				$rule_obj['fn_path'] = $rule[0][1];
			}else{
				Debug::toss(['message'=>'Non conforming rule', 'rule'=>$rule]);
			}
			$rule_obj['params'] = array_slice($rule,1);
			return $rule_obj;
		}
	}
	static function parse_rule_text($text){
		preg_match('/(^[^_a-z]+)?([^|]+)(\|(.*))?/i', $text, $match);
		if(!$match){
			throw new Exception('Rule text not conforming: "'.$text.'"');
		}
		return [
			'flag_string'=> $match[1],
			'fn_path'=> $match[2],
			'params_string'=> $match[4]
		];
	}
	static function parse_params($param_string){
		if($param_string === null){
			return [];
		}
		return preg_split('/;/', $param_string);
	}
	static function parse_flags($flag_string){
		if(!$flag_string){
			return [];
		}
		# handle 2 char flags
		$flags = [];
		if(preg_match('/\!\!/', $flag_string)){
			$flags['break_all'] = true;
			$flag_string = preg_replace('/\!\!/', '', $flag_string);
		}
		if(preg_match('/\&\&/', $flag_string)){
			$flags['full_continuity'] = true;
			$flag_string = preg_replace('/\&\&/', '', $flag_string);
		}
		$length = strlen($flag_string);
		for($i=0; $i < $length; $i++){
			switch($flag_string[$i]){
				case '?': $flags['optional'] = true; break;
				case '!': $flags['break'] = true; break;
				case '&': $flags['continuity'] = true; break;
				case '~': $flags['not'] = true; break;
			}
		}
		return $flags;
	}

	function resolve_fn($fn_path){
		try{
			$fn = Arrays::got($this->conformers, $fn_path);
		}catch(\Exception $e){}

		if(!$fn){
			try{
				$fn = Arrays::got($GLOBALS, $fn_path);
			}catch(\Exception $e){}
		}
		if(!is_callable($fn)){
			throw new Exception('fn_path is not a function path: '.$fn_path);
		}
		return $fn;
	}


	static function parse_exception($e){
		$error = json_decode($e->getMessage(), true);
		if(!$error){
			$error = $e->getMessage();;
		}
		if(is_scalar($error)){
			$error = ['message'=>$error];
		}
		return $error;
	}


	function fields_rules($field_map){
		if(!is_array($field_map)){
			throw new \Exception('field_map must be an array');
		}

		# attach `output` to `this` so subsequent field rulesets can access new formatted values
		$this->output = [];

		try{
			foreach($field_map as $field=>$rules){
				$rules = $this->compile_rules($rules);
				try{
					$output = $this->apply_rules($field, $rules);
					if(!$this->field_errors($field)){
						$this->output[$field] = $output;
					}
				}catch(\Exception $e){
					$error = self::parse_exception($e);
					if($error['type'] == 'break'){
						continue;
					}elseif($error['type'] == 'continuity'){
						continue;
					}
					throw $e;
				}
			}
		}catch(\Exception $e){
			$error = self::parse_exception($e);
			if($error['type'] == 'break_all'){
				return $this->output;
			}
			throw $e;
		}
		return $this->output;
	}

	function field_rules($field, $rules){
		$rules = self::compile_rules($rules);
		return $this->apply_rules($field, $rules);
	}

	function apply_rules($field, $rules){
		try{
			$value = Arrays::got($this->input, $field);
		}catch(\Exception $e){ # Field wasn't found
			$value = null;
		}
		foreach($rules as $rule){
			# handle continuity
			if($rule['flags']['continuity'] && $this->field_errors($field)){
				Debug::toss(['type'=>'continuity']);
			}elseif($rule['flags']['full_continuity'] && $this->errors){
				Debug::toss(['type'=>'continuity']);
			}

			# resolve and try function
			$fn = $this->resolve_fn($rule['fn_path']);
			$params = array_merge([$value], $rule['params']);
			# including `input` and `output` as referenced arrays doubles the time of a do-nothing callback
			$params[] = ['field'=>$field, 'instance'=>$this];

			try{
				$value = call_user_func_array($fn,$params);

				if($rule['flags']['not']){
					Debug::toss(['type'=>'not']);
				}
			}catch(\Exception $e){
				$error = self::parse_exception($e);
				if($rule['flags']['not'] && $error['type'] != 'not'){ # potentially, the not flag caused the Error
					continue;
				}
				if(!$rule['flags']['optional']){
					$error['rule'] = $rule;
					$this->error($error, $field);
				}
				if($rule['flags']['break']){
					$error['type'] = 'break';
					Debug::toss($error);
				}
				if($rule['flags']['break_all']){
					$error['type'] = 'break_all';
					Debug::toss($error);
				}
			}
		}
		return $value;
	}
	/// gets standardised errors
	function get_errors(){
		return $this->standardise_errors();
	}
	function standardise_errors($errors=false){
		$errors = $errors === false ? $this->errors : $errors;
		$std_errors = [];
		foreach($errors as $error){
			$std_error = $error;
			if(!$std_error['type']){
				$std_error['type'] = $error['rule']['fn_path'];
			}
			if($error['rule']['flags']['not']){
				$std_error['type'] = '~'.$std_error['type'];
			}
			if(!$error['message']){
				$std_error['message'] = $std_error['type'];
			}
			$std_error['params'] = $error['rule']['params'];

			$std_errors[] = $std_error;
		}

		return $std_errors;
	}

	/// false or true on error after fields_rules
	function valid($field_map){
		$output = $this->fields_rules($field_map);
		if($this->errors){
			return false;
		}
		return true;
	}

	/// @alias	fields_rules
	function output_from($field_map){
		return $this->fields_rules($field_map);
	}

	/// get std errors from fields_rules
	function errors_from($field_map){
		$this->fields_rules($field_map);
		return $this->standardise_errors();
	}
	/*

		0 : [
		'fields' : [
			0 : 'bob'
		]
		'message' : ''
		'rule' : [
			'flags' : []
			'params' : []
			'fn_path' : 'v.int'
		]
	]
	}
	*/
}


<?php
namespace Grithin;

use \Exception;

class Conform{

	use \Grithin\Traits\SingletonDefault;

	public $input;///< input on construct
	public $output = [];///< output after rules


	/**	params
	< input > < array of input > < if false, standard web input will be used >
	*/
	function __construct($input=false){
		$this->input = $input;
		if($input === false){
			$this->input = self::web_input();
		}
		$this->conformer_add('f',\Grithin\Conform\Filter::init());
		$this->conformer_add('v',\Grithin\Conform\Validate::init());
	}

	public $conformers;
	/** add a conformer class */
	public function conformer_add($name, $instance){
		$this->conformers[$name] = $instance;
	}

	/** get or set input */
	public function input($input=false){
		if($input===false){
			return $this->input;
		}else{
			$this->input = $input;
		}
	}


	/**
	For errors, consider that:
	-	error message order may be desired
	-	errors may be tied to field names
	-	an error may be tied to multiple fields

	As a consequence of these considerations, the following structure of the error data is used:

	[{message:<text message>, fields:[<field name>, ...]}, ...]

	Additional error attributes may be added as needed.
	Additionally, the text message part may have tokens for replacement or parsing
	*/
	public $errors = [];

	/** get $_GET with the addition of allowing a "_json" key to define a structured GET input */
	static function get(){
		$get = $_GET; # avoid replacing $_GET
		if(!empty($get['_json'])){
			# replace any overlapping keys with those found in `_json`
			# since other concerns (such as paging) may not be included in the `_json` structure, but within the $_GET keys apart from `_json`, merge the existing $_GET with `_json`
			$get = \Grithin\Arrays::replace($get, Tool::json_decode((string)$get['_json']));
		}
		return (array)$get;
	}
	/** get $_POST, also allowing for "content_type: json" */
	static function post(){
		$post = $_POST; # avoid replacing $_POST
		if(!$post && isset($_SERVER['CONTENT_TYPE']) && substr($_SERVER['CONTENT_TYPE'],0,16) == 'application/json'){
			$post = json_decode(file_get_contents('php://input'),true);
		}elseif(!empty($post['_json'])){ # allow `_json` to overwrite post contents
			$post = Tool::json_decode((string)$post['_json']);
		}
		return (array)$post;
	}
	/** a merge of self::get, and self::post, with preference to post */
	static function web_input(){
		return array_merge(self::get(), self::post());
	}

	/** attributes spelled out as parameters */
	function error_add($message, $type=null, $fields=[], $rule=null, $params=null){
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
	/** add an error to instance errors array */
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

	/** getter */
	function errors(){
		return $this->errors;
	}

	function errors_remove(){
		$this->errors = [];
	}
	/** deprecated: rename */
	function remove_errors(){
		$this->errors = [];
	}
	/** clear errors and output */
	function clear(){
		$this->errors = [];
		$this->output = [];
	}

	/** get all errors that include a field */
	function field_errors($field){
		return $this->fields_errors([$field]);
	}
	/** get all errors that include any of "fields" */
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

	/** params
	< field_map >
		< key > : < rule list >
		...
	*/
	function fields_rules($field_map){
		if(!is_array($field_map)){
			throw new \Exception('field_map must be an array');
		}

		$this->clear();


		try{
			foreach($field_map as $field=>$rules){
				$rules = $this->rules_compile($rules);
				try{
					$output = $this->field_rules_apply($field, $rules);
					if(!$this->field_errors($field) && $field){ # Don't add empty fields to output
						$this->output[$field] = $output;
					}
				}catch(\Exception $e){
					$error = self::parse_exception($e);
					if(isset($error['type'])){
						if($error['type'] == 'break'){
							# ! indicated to break out of current field
							continue;
						}elseif($error['type'] == 'continuity'){
							# & indicated to break out of current field
							continue;
						}
					}
					throw $e;
				}
			}
		}catch(\Exception $e){
			$error = self::parse_exception($e);
			if(isset($error['type']) && $error['type'] == 'break_all'){
				# a !! indicated to break out of the entire fields validation loop
				return $this->output;
			}
			throw $e;
		}
		return $this->output;
	}
	/** params
	< field > < key to match in input >
	< rules >
		< rule > < see .compile_rule >
		...
	*/
	function field_rules($field, $rules){
		$rules = self::rules_compile($rules);
		return $this->field_rules_apply($field, $rules);
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
		-	"~" to indicate if the validation does not fail, then there was an error.  Note, the original value (passed in to the function) will be pushed forward
		-	"&" to indicate code should break if there were any previous errors on that field
		-	"&&" to indicate code should break if there were any previous errors on any field in the validate run

	*/
	/** Ex
	$v = Conform::rules_compile('!!?bob|sue;bill;jan !!?bill|joe');
	*/
	static function rules_compile($rules){
		$compiled_rules = [];
		$rules = self::rules_format($rules);
		foreach($rules as $rule){
			$compiled_rules[] = self::rule_compile($rule);
		}
		return $compiled_rules;
	}


	static function rules_format($rules){
		if(Tool::is_scalar($rules)){
			$rules = preg_split('/[\s,]+/', (string)$rules);
			$rules = Arrays::remove($rules); # remove empty rules, probably unintended by spacing after or before
		}
		return $rules;
	}
	/** formats rules and appends to existing */
	static function conformers_append($new, $existing){
		return self::conformers_merge($new, $existing, function($new, $existing){ return Arrays::merge($existing, $new); });
	}
	/** formats rules and prepends to existing */
	static function conformers_prepend($new, $existing){
		return self::conformers_merge($new, $existing, ['Arrays', 'merge']);
	}
	static function conformers_merge($new, $existing, $merger){
		$fields = array_unique(array_merge(array_keys($existing), array_keys($new)));
		foreach($fields as $field){
			if(!empty($new[$field])){
				$new[$field] = self::rules_format($new[$field]);
				$existing[$field] = self::rules_format($existing[$field]);
				$existing[$field] = $merger($new[$field], $existing[$field]);
			}
		}
		return $existing;
	}

	/**	compile rule into standard format from multiple potential formats */

	/** params
	(
		< rule > < t: string > < "FUNCTION|PARAM;PARAM" >
		||
		< rule >
			< prefix and function string >
			(
			< param >
			...
			)
		||
		< rule >
			< >
				< prefixes > < t: string >
			(
			< param >
			...
			)
	*/
	/** examples

	.this('!!?fn|param1;param2');

	.this(['!!?fn','param1','param2']);

	.this([['!!?','fn'],'param1','param2']);
	*/

	static function rule_compile($rule){
		if(is_string($rule)){
			$parsed_rule = self::rule_parse_text($rule);
			$rule_obj['flags'] = self::rule_parse_flags($parsed_rule['flag_string']);
			$rule_obj['params'] = self::rule_parse_params($parsed_rule['params_string']);
			$rule_obj['fn_path'] = $parsed_rule['fn_path'];
			return $rule_obj;
		}elseif(is_array($rule)){
			if(is_string($rule[0])){
				$parsed_rule = self::rule_parse_text($rule[0]);
				$rule_obj['flags'] = self::rule_parse_flags($parsed_rule['flag_string']);
				$rule_obj['fn_path'] = $parsed_rule['fn_path'];
			}elseif(is_array($rule[0])){
				$rule_obj['flags'] = self::rule_parse_flags($rule[0][0]);
				$rule_obj['fn_path'] = $rule[0][1];
			}else{
				Debug::toss(['message'=>'Non conforming rule', 'rule'=>$rule]);
			}
			$rule_obj['params'] = array_slice($rule,1);
			return $rule_obj;
		}
	}


	/** About
	Parse the text of a single rule item

	prefix''function'|'params
	ex: ?!fn|param1
	*/
	static function rule_parse_text($text){
		preg_match('/(^[^_a-z]+)?([^|]+)(\|(.*))?/i', $text, $match);
		if(!$match){
			throw new Exception('Rule text not conforming: "'.$text.'"');
		}
		return [
			'flag_string'=> $match[1],
			'fn_path'=> $match[2],
			'params_string'=> isset($match[4]) ? $match[4] : ''
		];
	}
	/** Parse string paraams that are separated with ';' */
	static function rule_parse_params($param_string){
		if($param_string === null){
			return [];
		}
		return preg_split('/;/', $param_string);
	}
	/** parse the various ?, !, & flags in front of the function */
	static function rule_parse_flags($flag_string){
		$flags = [
			'optional' => false,
			'break' => false,
			'continuity' => false,
			'not' => false,
			'break_all' => false,
			'full_continuity' => false
		];
		if(!$flag_string){
			return $flags;
		}
		#+ handle 2 char flags first {
		if(preg_match('/\!\!/', $flag_string)){
			$flags['break_all'] = true;
			$flag_string = preg_replace('/\!\!/', '', $flag_string);
		}
		if(preg_match('/\&\&/', $flag_string)){
			$flags['full_continuity'] = true;
			$flag_string = preg_replace('/\&\&/', '', $flag_string);
		}
		#+ }
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
		$fn = null;
		Arrays::got($this->conformers, $fn_path);
		if(is_string($fn_path)){
			try{
				$fn = Arrays::got($this->conformers, $fn_path);
			}catch(\Exception $e){}

			if(!$fn){
				try{
					$fn = Arrays::got($GLOBALS, $fn_path);
				}catch(\Exception $e){}
			}
		}else{
			# handle actual functions passed in
			$fn = $fn_path;
			# save string representation for possible error presentation
			$fn_path = Tool::json_encode($fn_path);
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




	/** apply a set or rules to an object path specified field within input */
	/** params
	< field > < t:string > < object path for matching key in input >
	< rules >:
		< rule >
		...
	*/
	function field_rules_apply($field, $rules=[]){
		try{
			$value = Arrays::got($this->input, $field);
		}catch(\Exception $e){ # Field wasn't found
			$value = null;
		}
		if(!$rules){
			return  $value;
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
			$params = array_merge([$value], $rule['params']); # add passed parameters (in rule) into validation funciton
			$params[] = ['field'=>$field, 'instance'=>$this];

			try{
				$value = call_user_func_array($fn,$params);

				if(!empty($rule['flags']['not'])){
					Debug::toss(['type'=>'not']);
				}
			}catch(\Exception $e){
				$error = self::parse_exception($e);
				if($rule['flags']['not'] && $error['type'] != 'not'){ # potentially, the not flag caused the Error
					continue;
				}
				if(empty($rule['flags']['optional'])){
					$error['rule'] = $rule;
					$this->error($error, $field);
				}
				if(!empty($rule['flags']['break'])){
					$error['type'] = 'break';
					Debug::toss($error);
				}
				if(!empty($rule['flags']['break_all'])){
					$error['type'] = 'break_all';
					Debug::toss($error);
				}
			}
		}
		return $value;
	}
	/** gets standardised errors */
	function errors_get(){
		return $this->standardise_errors();
	}

	/** deprecated : use errors_get */
	function get_errors(){
		return $this->errors_get();
	}
	function standardise_errors($errors=false){
		$errors = $errors === false ? $this->errors : $errors;
		$std_errors = [];
		foreach($errors as $error){
			$std_error = $error;
			if(empty($std_error['type'])){
				$std_error['type'] = $error['rule']['fn_path'];
			}
			if(!empty($error['rule']['flags']['not'])){
				$std_error['type'] = '~'.$std_error['type'];
			}
			if(empty($error['message'])){
				$std_error['message'] = $std_error['type'];
			}
			$std_error['params'] = $error['rule']['params'];

			$std_errors[] = $std_error;
		}

		return $std_errors;
	}

	/** false or true on error after fields_rules */
	function valid($field_map){
		$output = $this->fields_rules($field_map);
		if($this->errors){
			return false;
		}
		return true;
	}


	/** get std errors from fields_rules */
	function errors_from($field_map){
		$this->fields_rules($field_map);
		return $this->standardise_errors();
	}
	/**

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

	/** standard for clearing rules that aren't applying to a particular field, where key starts with `-` */
	function non_fields_clear($array_to_filter = null){
		if($array_to_filter === null){
			$array_to_filter = $this->output;
		}
		foreach($array_to_filter as $k=>$v){
			if($k[0] == '-'){
				unset($array_to_filter[$k]);
			}
		}
		return $array_to_filter;
	}

	/** Throw custom exception on error, otherwise return conformed input */
	/** @NOTE	can be called from an instance, or statically, with user input as second parameter */
	function validate($rules){
		if($this){
			if(!$this->valid($rules)){
				throw new ConformException($this);
			}
			return $this->output;
		}
	}

	/** static-able */
	/** if errors provided, new Conform instance made for ConformException with errors */
	/** if errors not provided, expected public instance call, and $this provided to ConformException */

	/** params
	errors: < array of errors >
		|
		< single text error >
	*/
	function except($errors=null){
		if($errors){
			if(!is_array($errors)){
				$errors = [['message'=>$errors]];
			}
			$conform = new Conform([]);
			$conform->errors = $errors;
			throw new ConformException($conform);
		}else{
			throw new ConformException($this);
		}
	}
}

class ConformException extends ComplexException{}

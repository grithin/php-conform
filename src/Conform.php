<?php
namespace Grithin;

use \Exception;

/** Conforming and validating input to expected */
/* About
Processing input is often a series of filtering and validation.  In short, the input is conformed.

For some input, conformance can result in:
-	some fields conforming
-	some fields resultin in errors

To allow for this dual interest, the return of `get` either returns the conformed array, if there were no errors, or returns false if there were errors.  One must then use $Conform->errors() to get the errors.
If there were no errors, but there was no conforming keys, an empty array will be returned.
For convenience, since an empty array does not equate to true, `valid` is also provided to return true or false

Often, it is desired to check sections of input.  For instance, an input that has multiple ticket objects, each needing conforming.
To reflect the common use, `get` and `valid` both clear errors for each call.  If you need to save the form level errors in a structure like
-	form
	-	ticket
	-	ticket
You need to do so before running `get` on the tickets.

Because it is sometimes desire to inject logic between conforming on the same input, the input is not reset per call.

*/
class Conform{

	use \Grithin\Traits\SingletonDefault;

	/** current input being tested */
	public $input;
	/** output after rules */
	public $output = [];
	/** fields associated with errors */
	public $fields_with_error = [];

	/**	params
	< input > < array of input > < if false, standard web input will be used >
	*/
	function __construct($input=false){
		$this->input = $input;
		if($input === false){
			$this->input = self::request_input();
		}
		$this->conformer_add('f',\Grithin\Conform\Filter::init());
		$this->conformer_add('v',\Grithin\Conform\Validate::init());
		$this->conformer_add('g',new \Grithin\Conform\GlobalFunction);
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

	/** return conformed array or false if there were errors */
	public function get($fields_rules){
		$this->outcome_clear();
		$this->fields_rules_apply($fields_rules);
		if($this->errors){
			return false;
		}
		return $this->output;
	}
	/** if there were no errors, return true */
	public function valid($fields_rules){
		$got = $this->get($fields_rules);
		return $got === false ? false : true;
	}
	/** get std errors from fields_rules */
	public function errors_from($field_map){
		$this->fields_rules_apply($field_map);
		return $this->errors_standardized();
	}


	/** @var \Grithin\Conform\Error[] $errors array of errors resulting from most recent call to get*/
	public $errors = [];

	/** get $_GET with the addition of allowing a "_json" key to define a structured GET input

	When _json key is presented, it is assumed the content of that key preresents a JSON string.  As such, the _json value is interpretted and set as the returned post input.
	 */
	static function request_get(){
		$get = $_GET; # avoid replacing $_GET
		if(!empty($get['_json'])){
			# replace any overlapping keys with those found in `_json`
			# since other concerns (such as paging) may not be included in the `_json` structure, but within the $_GET keys apart from `_json`, merge the existing $_GET with `_json`
			$get = \Grithin\Arrays::replace($get, Tool::json_decode((string)$get['_json']));
		}
		return (array)$get;
	}
	/** get $_POST, also allowing for "content_type: json" and a reserved _json key

	When the content type is application/json, the $_POST will not be filled.  Instead, to get the JSON from the request, php://input must be read and intrepretted.  This is done in this function.
	When _json key is presented, it is assumed the content of that key preresents a JSON string.  As such, the _json value is interpretted and set as the returned post input.
	 */

	static function request_post(){
		$post = $_POST; # avoid replacing $_POST
		if(!$post && isset($_SERVER['CONTENT_TYPE']) && substr($_SERVER['CONTENT_TYPE'],0,16) == 'application/json'){
			$post = json_decode(file_get_contents('php://input'),true);
		}elseif(!empty($post['_json'])){ # allow `_json` to overwrite post contents
			$post = Tool::json_decode((string)$post['_json']);
		}
		return (array)$post;
	}
	/** a merge of :request_get, and :request_post, with preference to post

	@see \Grithin\Conform::request_post()
	@see \Grithin\Conform::request_get()
	 */
	static function request_input(){
		return array_merge(self::request_get(), self::request_post());
	}

	/** manually add an error
	@param	\Grithin\Conform\Error	$error error
	*/
	function error_add(\Grithin\Conform\Error $error){
		$this->errors[] = $error;
	}

	/** getter */
	function errors(){
		return $this->errors;
	}

	/** clear errors, output, fields_with_error */
	function outcome_clear(){
		$this->errors = [];
		$this->output = [];
		$this->fields_with_error = [];
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

	protected function fields_with_error_add($fields){
		$this->fields_with_error = array_unique(array_merge($this->fields_with_error, (array)$fields));
	}



	/** @var array $error	the current unhandled error */
	protected $error = false;
	/** set the current unhandled error */
	public function error($details=''){
		if(is_scalar($details)){
			$details = ['message'=>$details];
		}
		if(!empty($details['fields'])){
			$details['fields'] = array_unique(array_merge(Arrays::from($details['fields']), [$this->field]));
		}else{
			$details['fields'] = [$this->field];
		}
		$format = ['message'=>'','type'=>'','fields'=>null, 'rule'=>null, 'params'=>null];
		$this->error = array_merge($format, $details);
	}
	public function error_unset(){
		$this->error = false;
	}


	/** get 	*/
	/** params
	< field_map >
		< key > : < rule list >
		...
	*/
	function fields_rules_apply($field_map){
		if(!is_array($field_map)){
			throw new \Exception('field_map must be an array');
		}

		# function needed to allow escape points
		$field_map_loop = function($field_map){
			foreach($field_map as $field=>$rules){
				$output = $this->field_rules_apply($field, $rules);

				if($this->error){
					if(isset($this->error['type'])){
						if($this->error['type'] == 'break'){
							# ! indicated to break out of current field
							continue;
						}elseif($this->error['type'] == 'continuity'){
							# & indicated to break out of current field
							continue;
						}
					}
					return;
				}

				if(!$this->field_errors($field) && $field){ # Don't add empty fields to output
					$this->output[$field] = $output;
				}
			}
		};
		$field_map_loop($field_map);

		if($this->error){
			# a !! indicated to break out of the entire fields validation loop
			$was_break_all_flag = isset($this->error['type']) && $this->error['type'] == 'break_all';
			if(!$was_break_all_flag){
				Debug::toss(['message'=>'unhandled error', 'error'=>$this->error]);
			}
		}

		#+ only return output for fields that aren't associated with errors.  Since a rule for one field can include association for another field, must check the current errors {
		# @TODO $this->field_errors($field)
		#+ }


		return Arrays::omit($this->output, $this->fields_with_error);
	}


	/** magic get for `field` */
	public function __get($key){
		return $this->$key;
	}
	/** @var string $field the current field being processed by rules */
	protected $field;
	/** apply a set or rules to an object-path specified field within input */
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

		$rules = $this->rules_compile($rules);

		if(!$rules){
			return  $value;
		}

		# set field for access by rule functions
		$this->field = $field;

		foreach($rules as $rule){
			# handle continuity
			if($rule['flags']['continuity'] && $this->field_errors($field)){
				$this->error(['type'=>'continuity']);
				return;
			}elseif($rule['flags']['full_continuity'] && $this->errors){
				$this->error(['type'=>'continuity']);
				return;
			}

			# resolve and try function
			$fn = $this->resolve_fn($rule['fn_path']);
			$params = array_merge([$value], $rule['params']); # add passed parameters (in rule) into validation funciton
			$params[] = $this;


			# run the rule function
			$value = call_user_func_array($fn,$params);



			#+ check the outcome {
			if(!empty($rule['flags']['not'])){
				if(!$this->error){
					# the rule was supposed to fail, and did not, add error
					$this->error(['type'=>'not']);
				}else{
					# the rule was supposed to fail, and did so, clear the error
					$this->error_unset();
				}
			}
			if($this->error){
				if(empty($rule['flags']['optional'])){
					$this->error['rule'] = $rule;
					$error = new Conform\Error($this->error['message'], $this->error['fields'], $this->error['type'], $this->error['rule'], $this->error['params']);

					$this->fields_with_error_add($this->error['fields']);

					$this->error_add($error);
					$this->error_unset();
				}
				if(!empty($rule['flags']['break'])){
					$this->error['type'] = 'break';
					return;
				}
				if(!empty($rule['flags']['break_all'])){
					$this->error['type'] = 'break_all';
					return;
				}
			}
			#+ }
		}
		return $value;
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
			'params_string'=> isset($match[4]) ? $match[4] : null
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
		if(is_string($fn_path)){
			$fn = Arrays::get($this->conformers, $fn_path);
			if(!$fn){
				if(strpos($fn_path, '.')){ # it is part of an object, check GLOBALS
					$fn = Arrays::get($GLOBALS, $fn_path);
				}
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


}



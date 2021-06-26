<?php
namespace Grithin\Conform;


class Error implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable {
	use \Grithin\Traits\ObjectAsArray;

	/** @var string $message  text message of error */
	public $message;
	/** @var string[] $fields  fields the error is associated with */
	public $fields;
	/** @var string $type  the type of error.  Can be used as a constant key on a message that varies */
	public $type;
	/** @var array $rule  the rule array (prefixes, function) */
	public $rule;
	/** @var array $params  the parameters include both the input and the parameters passed within the fields_rules array */
	public $params;

	function __construct($message, $fields=[], $type=null, $rule=null, $params=null){
		$this->message = $message;
		$this->fields = (array)$fields;
		$this->rule = $rule;
		$this->params = $params;

		if($type){
			$this->type = $type;
		}elseif($rule && isset($rule['fn_path'])){
			$this->type = $rule['fn_path']; # default to using the fn_path if present
		}
		# apply `not` to type
		if($rule && isset($rule['flags']) && !empty($rule['flags']['not'])){
			$this->type = '~'.$this->type;
		}
		if(!$message){
			$this->message = $this->type;
		}
	}

}
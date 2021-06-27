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

	function __construct($message, $fields=[], $type=null){
		$this->message = $message;
		$this->fields = (array)$fields;

		$this->type = $type;
		if(!$message){
			$this->message = $this->type;
		}
	}

}
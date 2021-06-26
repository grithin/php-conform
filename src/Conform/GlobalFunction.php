<?php
namespace Grithin\Conform;

class GlobalFunction{
	# strip Conformer parameter and call on global function
	public function __call($func, $args){
		return call_user_func_array($func, array_slice($args, 0, -1));
	}
}
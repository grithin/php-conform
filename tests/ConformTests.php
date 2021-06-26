<?php

use PHPUnit\Framework\TestCase;

use \Grithin\Debug;
use \Grithin\Time;
use \Grithin\Arrays;


# http://stackoverflow.com/questions/24316347/how-to-format-var-export-to-php5-4-array-syntax
# useful for printing out output for assertEquals
function var_export54($var, $indent="") {
		switch (gettype($var)) {
			case "string":
				return '"' . addcslashes($var, "\\\$\"\r\n\t\v\f") . '"';
			case "array":
				$indexed = array_keys($var) === range(0, count($var) - 1);
				$r = [];
				foreach ($var as $key => $value) {
						$r[] = "$indent    "
							. ($indexed ? "" : var_export54($key) . " => ")
							. var_export54($value, "$indent    ");
				}
				return "[\n" . implode(",\n", $r) . "\n" . $indent . "]";
			case "boolean":
				return $var ? "TRUE" : "FALSE";
			default:
				return var_export($var, TRUE);
		}
}



/**
* @group Conform
*/
class ConformTests extends TestCase{
	function __construct(){
		$this->input = $input = [
			'bob'=>'bob',
			'email'=>'bob@bob.com',
			'price'=>'$14,240.02',
			'float'=>12.1,
			'int'=>2,
			'int_string'=>' 2',
		];
		$this->conform = new \Grithin\Conform($input);
		$this->conform->add_conformer('f',\Grithin\Conform\Filter::init());
		$this->conform->add_conformer('v',new \Grithin\Conform\Validate);
		$e = (function(){ throw new \Exception;});
		$this->conform->add_conformer('e',$e);
	}
	function test_sequence(){
		$rules = [
			'bob'=>'f.int',
			'float'=>'f.int ~v.int e'
		];
		$v = $this->conform->fields_rules($rules);
		#Debug::out($this->conform->errors_standardized($this->conform->errors()));
		$this->assertEquals(1, 1);
		$this->conform->clear();
	}
	function test_field_continuity_1(){
		$rules = [
			'float'=>'v.email e'
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertTrue(is_array($errors[1]));
		$this->assertEquals($errors[1]['type'], 'e');
		$this->conform->clear();
	}
	function test_field_continuity_2(){
		$rules = [
			'float'=>'v.email &e'
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
		$this->conform->clear();
	}
	function test_field_continuity_3(){
		$rules = [
			'float'=>'v.float &e'
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
		$this->assertEquals($errors[0]['type'], 'e');
		$this->conform->clear();
	}

	function test_full_continuity_1(){
		$rules = [
			'bob'=>'f.int',
			'float'=>'e'
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertTrue(is_array($errors[0]));
		$this->assertEquals($errors[0]['type'], 'e');
		$this->conform->clear();
	}
	function test_full_continuity_2(){
		$rules = [
			'bob'=>'v.email',
			'float'=>'&&e'
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
		$this->assertEquals($errors[0]['type'], 'v.email');
		$this->conform->clear();
	}
	function test_full_continuity_3(){
		$rules = [
			'bob'=>'v.email',
			'float'=>'e &&e'
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 2);
		$this->conform->clear();
	}
	function test_full_continuity_4(){
		$rules = [
			'bob'=>'f.int',
			'float'=>'&&e'
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
		$this->assertEquals($errors[0]['type'], 'e');
		$this->conform->clear();
	}

	function test_field_break(){
		$rules = [
			'float'=>'e !e e'
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 2);
		$this->conform->clear();
	}

	function test_break_all_1(){
		$rules = [
			'bob'=>'v.int',
			'float'=>'e !!e e'
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 3);
		$this->conform->clear();
	}
	function test_break_all_2(){
		$rules = [
			'bob'=>'!!v.int',
			'float'=>'e !!e e'
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
		$this->conform->clear();
	}

	function test_not(){
		$rules = [
			'bob'=>'~v.int',
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 0);
		$this->conform->clear();
	}

	function test_optional(){
		$rules = [
			'bob'=>'?v.int',
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 0);
		$this->conform->clear();
	}

	function test_optional_break_1(){
		$rules = [
			'bob'=>'?v.int v.int',
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
		$this->conform->clear();
	}

	function test_optional_break_2(){
		$rules = [
			'bob'=>'?!v.int v.int',
		];
		$errors = $this->conform->errors_from($rules);
		$this->assertEquals(count($errors), 0);
		$this->conform->clear();
	}

	function test_output_1(){
		$rules = [
			'bob'=>'v.int',
			'float'=>'f.int v.int',
			`int`=>'&&v.int',
			'int_string'=>'!!v.int',
			'price'=>''
		];
		$output = $this->conform->fields_rules($rules);
		$errors = $this->conform->errors_standardized();

		$this->assertEquals($output, ["float" => 12]);
		$this->conform->clear();
	}

	function test_output_2(){
		$rules = [
			'bob'=>'v.int',
			'float'=>'f.int v.int',
			`int`=>'&&v.int',
			'int_string'=>'~!!v.int',
			'price'=>''
		];
		$output = $this->conform->fields_rules($rules);
		$errors = $this->conform->errors_standardized();

		$this->assertEquals($output, ["float" => 12,"int_string" => " 2", "price" => "\$14,240.02"]);
		$this->conform->clear();
	}
}
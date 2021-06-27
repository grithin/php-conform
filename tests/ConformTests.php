<?php

use PHPUnit\Framework\TestCase;

use \Grithin\Debug;
use \Grithin\Time;
use \Grithin\Arrays;
use \Grithin\Conform;



/**
* @group Conform
*/
class ConformTests extends TestCase{
	function __construct(){
		$_GET = [
			'bob'=>'bob',
			'email'=>'bob@bob.com',
			'price'=>'$14,240.02',
			'float'=>12.1,
			'int'=>2,
			'int_string'=>' 2',
		];

		$_POST = ['int'=>3];

		$this->Conform = new Conform;
		$this->Conform->conformer_add('e', function($x, $Conform){ $Conform->error(['type'=>'e', 'message'=>'error']); });
	}
	function test_input(){
		$get = $_GET;
		$post = $_POST;

		$Conform = new Conform;
		$this->assertEquals(3, $Conform->input['int']);
		$this->assertEquals('2', $Conform->input['int_string']);

		$_GET['_json'] = json_encode(array_merge($_GET, ['bob'=>'sue', 'bill'=>'bob']));
		$Conform = new Conform;
		$this->assertEquals('sue', $Conform->input['bob']);
		$this->assertEquals('bob', $Conform->input['bill']);

		$_POST['_json'] = json_encode(array_merge($_POST, ['int'=>4, 'bill'=>'bob2']));
		$Conform = new Conform;
		$this->assertEquals(4, $Conform->input['int']);
		$this->assertEquals('bob2', $Conform->input['bill']);

		$_GET = $get;
		$_POST = $post;
	}

	function test_error_format(){
		$Conform = new Conform;
		$rules = [
			'bob'=>'f.int',
			'float'=>'f.int, ~v.int'
		];
		$v = $Conform->get($rules);
		$this->assertEquals(new \Grithin\Conform\Error('~v.int', ['float'], '~v.int'), $Conform->errors[0]);
	}

	function test_sequence(){
		$Conform = new Conform;
		$rules = [
			'bob'=>'f.int',
			'float'=>'f.int, ~v.int'
		];
		$v = $Conform->get($rules);
		$this->assertEquals(false, $v);

		$this->assertEquals(1, count($Conform->errors));
	}

	function test_field_continuity_1(){

		$rules = [
			'float'=>'v.email e'
		];
		$v = $this->Conform->get($rules);
		$this->assertEquals(false, $v);
		$this->assertEquals($this->Conform->errors[0]['type'], 'v.email');
		$this->assertEquals($this->Conform->errors[1]['type'], 'e');
	}

	function test_field_continuity_2(){
		$rules = [
			'float'=>'v.email &e'
		];
		$errors = $this->Conform->get($rules);
		$this->assertEquals(count($this->Conform->errors), 1);
	}

	function test_field_continuity_3(){
		$rules = [
			'float'=>'v.float &e'
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
		$this->assertEquals($errors[0]['type'], 'e');
	}


	function test_full_continuity_1(){
		$rules = [
			'bob'=>'f.int',
			'float'=>'&e'
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals($errors[0]['type'], 'e');
	}

	function test_full_continuity_2(){
		$rules = [
			'bob'=>'v.email',
			'float'=>'&&e'
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
		$this->assertEquals($errors[0]['type'], 'v.email');
	}

	function test_full_continuity_3(){
		$rules = [
			'bob'=>'v.email',
			'float'=>'e &&e'
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 2);
	}

	function test_full_continuity_4(){
		$rules = [
			'bob'=>'f.int',
			'float'=>'&&e'
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
		$this->assertEquals($errors[0]['type'], 'e');
	}


	function test_field_break(){
		$rules = [
			'float'=>'e !e e'
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 2);
	}

	function test_break_all_1(){
		$rules = [
			'bob'=>'v.int',
			'float'=>'e !!e e'
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 3);
	}

	function test_break_all_2(){
		$rules = [
			'bob'=>'!!v.int',
			'float'=>'e !!e e'
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
	}



	function test_not(){
		$rules = [
			'bob'=>'~v.int',
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 0);
	}



	function test_optional(){
		$rules = [
			'bob'=>'?v.int',
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 0);
	}



	function test_optional_sequence(){
		$rules = [
			'bob'=>'?v.int v.int',
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 1);
	}

	function test_optional_break_1(){
		$rules = [
			'bob'=>'?!v.int v.int',
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 0);
	}

	function test_optional_break_2(){
		$rules = [
			'bob'=>'?!!v.int v.int',
			'float' => 'v.int'
		];
		$errors = $this->Conform->errors_from($rules);
		$this->assertEquals(count($errors), 0);
	}


	function test_output_1(){
		$rules = [
			'bob'=>'v.int',
			'float'=>'f.int v.int',
			`int`=>'&&v.int',
			'int_string'=>'!!v.int',
			'price'=>''
		];
		$this->Conform->get($rules);
		$errors = $this->conform->errors;

		$this->assertEquals($this->Conform->output, ["float" => 12]);
	}

	function test_output_2(){
		$rules = [
			'bob'=>'v.int',
			'float'=>'f.int v.int',
			`int`=>'&&v.int',
			'int_string'=>'~!!v.int',
			'price'=>''
		];
		$this->Conform->get($rules);

		$this->assertEquals($this->Conform->output, ["float" => 12,"int_string" => " 2", "price" => "\$14,240.02"]);
	}
}
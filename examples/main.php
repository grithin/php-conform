<?php

$_ENV['root_folder'] = realpath(dirname(__FILE__).'/../').'/';
require $_ENV['root_folder'] . '/vendor/autoload.php';

use \Grithin\Debug;
use \Grithin\Time;
use \Grithin\Arrays;

# input passed-in
$input = [
	'hits'=>'bob',
	'clicks'=>2,
];
$conform = new \Grithin\Conform($input);
$conform->add_conformer('f',\Grithin\Conform\Filter::init());
$conform->add_conformer('v',new \Grithin\Conform\Validate);
$e = (function(){ throw new \Exception;});
$conform->add_conformer('e',$e);

$rules = [
	'hits'=>'v.int',
	'clicks'=>'v.int v.min|1'];
Debug::out($conform->errors_from($rules));
/*
0 : [
		'fields' : [
			0 : 'hits'
		]
		'message' : 'v.int'
		'rule' : [
			'flags' : []
			'params' : []
			'fn_path' : 'v.int'
		]
		'type' : 'v.int'
		'params' : []
	]
]
*/

Debug::out($conform->output);
/*
[
	'clicks' : 2
]
*/




# input assumed from normal stdin and POST and GET
$rules = [
	'hits'=>'v.int',
	'clicks'=>'v.int v.min|1'];
$_POST = [
	'hits'=>'v.int',
	'clicks'=>'v.int v.min|1'];
$conform = new \Grithin\Conform($input);
$conform->add_conformer('f',\Grithin\Conform\Filter::init());
$conform->add_conformer('v',new \Grithin\Conform\Validate);
$e = (function(){ throw new \Exception;});
$conform->add_conformer('e',$e);

Debug::out($conform->fields_rules($rules));
/*
[
	'clicks' : 2
]
*/
Debug::out($conform->standardise_errors());
/*
0 : [
		'fields' : [
			0 : 'hits'
		]
		'message' : 'v.int'
		'rule' : [
			'flags' : []
			'params' : []
			'fn_path' : 'v.int'
		]
		'type' : 'v.int'
		'params' : []
	]
]
*/

# Clearing previous errors and output
$conform->clear();

# rules which always pass
$rules = [
	'hits' => 'f.int',
	'clicks' => ''];

Debug::out($conform->fields_rules($rules));
/*
[
	'hits' : 0
	'clicks' : 2
]
*/
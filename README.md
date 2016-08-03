# PHP Conform Input

## Purpose

Porting the improvements made on phpinput to Conform.js back to php as phpConform

## Use
Running input through conform rules yields two results: 1. input conformed output 2. errors

There are methods to get either, and either are available directly from the Conform instance.

Getting the errors with a shortcut error method
```php
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

```

Getting the conformed output and errors
```php
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
```

The conform instance will reset the output for each time `fields_rules` is called.  However, the errors are not reset.  To reset the errors, you can either use `remove_errors` or `clear`.  The `clear` method also removes the output.
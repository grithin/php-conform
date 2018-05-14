# PHP Conform Input
Ordered filtering and validation with standard basic logic

_see source code for doc_

## Basic
```php
$input = [
	'sue'=>'sue',
	'goner'=>'goner'
];
$rules = [
	'sue'=>null, # the key must be provided or it will not be returned in the `conformed` array even if it is in the input
	'bob'=>'f.default|bob'
];

# instance creation with provided instance
# input defaults to ~ $_POST and $_GET
$conform = Conform::standard_instance($input);
$conformed = $conform->validate($rules);
#> {"sue": "sue", "bob": "bob"}

# auto instance creation
$conformed = Conform::validate($rules, $input);
#> {"sue": "sue", "bob": "bob"}
```

## Object Attirbutes
`input` is set at instantiation.  It is what is used for filtering/validating.  
`output` is reset each fieldset rules run.  It is the output of filtering/validating. It is a field-to-value keyed array.
`errors` is accumulated on fieldset rules runs, and is not reset automatically.  If you reuse the same Conform object with different fields in a different context, you should reset  `errors`;


## Custom Function
```
< field value >
< context > :
	instance: < Conform object >
	field: < field name >
	input: < reference to Conform.input >
	output < reference to Conform.output >
```

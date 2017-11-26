# PHP Conform Input
Ordered filtering and validation with standard basic logic

## Basic
```php
$conform = Conform::standard_instance(); # input defaults to ~ $_POST and $_GET.  Can also pass as first parameter.
$rules = ['user_id'=>'v.int']; # an array of rules whos keys map the keys of the input.
$conformed = $conform->validate($rules); # returns the conformed input or throws an exception

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

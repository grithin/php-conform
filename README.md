# PHP Conform Input
Ordered filtering and validation with standard input logic.


## About
Back around 2009, I wanted to build something that would resolve having to code the normal input logic in situations like
-	if x field failed, don't check y field
-	if x field failed one step of validation, don't validate it further
-	if x field failed validation, stop validating all input and return with error

There are a lot of combinations of this input field dependent logic which I thought could be simplified in expression.  So, I created this class, which has changed through the years.  I've recently updated it to be more compatible for others to use (no short tags, better code standard).



## Use Introduction


Form validation logic mostly follows patterns:

For example, if the id input is an int, check it in the database:
```php
$rules = ['id' => `!v.int, db.check`];
```
The `!` says, ensure `id` input exists as an int before doing `db.check`.
What if we had multiple fields, but we don't care to validate them if there was no id?
```php
$rules = [
	'id' => `!!v.int, db.check`,
	'name' => 'db.check_unique'
];
```
Here, the `!!` says, if `id` is not an int, exit with fail for the whole set of inputs.


Sometimes there are optional fields that we still want to filter if they are present.  To do this, you can combine two operaters.
```php
$rules = [
	'email' => `?!v.filled, v.email`,
];
```
This says, if the field is not filled, stop applying the rule, but don't show as an error.  This way, if the user left the field empty, there will be no email validation and no error, but if the user had filled out the email input, there will be email validation.

The order of `!` and `?` doesn't matter.


See [Rule Item Prefixes](#rule-item-prefixes)





## Rule Item Prefixes
The prefix can be some combination of the following
-	"!" to break on error with no more rules for that field should be applied
-	"!!" to break on error with no more rules for any field should be applied
-	"?" to indicate the validation is optional, and not to throw an error (useful when combined with '!' => '?!v.filled,email')
-	"~" to indicate if the validation does not fail, then there was an error.  Note, the original value (passed in to the function) will be pushed forward
-	"&" to indicate code should break if there were any previous errors on that field
-	"&&" to indicate code should break if there were any previous errors on any field in the validate run




## Examples
```php
$input = [
	'sue'=>'sue',
	'goner'=>'goner'
];
$rules = [
	'sue'=>'',
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










There



	-
		-	this says, "ensure "





_see source code for doc_





## Validate And Filter Alone
You can use Validate and Filter pseudo statically, b/c they are traited with SingletonDefault.

```php
Filter::init()->url($url)
Validate::init()->url($url)
```



## Notes And Common Functions

Comform uses the `\ConformException`  to pass information about errors in input values.  The common method `validate($rules)` takes a set of rules, and returns either the conformed input, or an exception if there was an error.  If there were errors, they are placed in `$Conform->errors`.  The output is also available in `$Conform->output`.  Errors and output is reset each time the various functions that call `fields_rules` are run (including `validate`).

Common Functions
-	`validate` returns conformed output or a `\ConformException` exception if there is an error
-	`valid` returns true if input conforms or false if it does not.  `$Conform->output` and `$Conform->errors` are available.
-	`Conform::except($errors)`  throw a `\ConformException` with the errors provided
-	`$Conform->except()` throw a `\ConformException` with the current Conform instance



## Standard Comformers And Custom Comformers

All conformers exist in the Confrom instance in the `conformers` attribute dictionary.  They can be applied to a field by specifying their stringified depth path (see `underscore.get`).  For example:
-	`class_name.method` expects that `class_name` is the key in the `conformers` dictionary that points to an instance, and that instance has a method `method` that will be called on the field
-	`function`  that `class_name` is the key in the `function` dictionary that points to a function
You can add a conformer to a `Conform` instance using `Conform->conformer_add()`


By default,  with `standard_instance`, the `comformers` array gets two keys:
1.	`f` : `\Grithin\Conform\Filter`
2.	`v` : `\Grithin\Conform\Validate`


The conformer function takes these parameters:
```
< field value >
< context > :
	instance: < Conform object >
	field: < field name >
	input: < reference to Conform.input >
	output < reference to Conform.output >
```

The value of the field becomes whatever the comformer returns.  If the coformer just validates the value, it is still necessary for the conformer to return the value.

If a value does not conform/validate, this is signalled by the conformer by throwing and exception.  The message of the exception becomes the error linked to the field.



## Errors
`$Conform->errors` exist as a numeric array.  Each element is formatted as:
```coffee
message: < error message >
fields: [ < field list > ]
rule: < details of rule causing error >
params: < the parameters supplied to the rule, apart from the field value and the default appended parameters >
```

For a custom conformer, it is sufficient to throw a normal exception, in which case the exception error becomes the `error.message`.  To supply the other keys, use `\Grithin\ComplexException`.  Arrays supplied in `throw new \Grithin\ComplexException($array)` will fill the keys of the error.

The exception that `Conform` returns is als a `\Grithin\ComplexException`.  To access the details of this exception, use `$exception->details`.  The `details` is the Conform instance itself.



## Object Attirbutes
`input` is set at instantiation.  It is what is used for filtering/validating.  
`output` is reset each fieldset rules run.  It is the output of filtering/validating. It is a field-to-value keyed array.
`errors` is accumulated on fieldset rules runs, and is not reset automatically.  If you reuse the same Conform object with different fields in a different context, you should reset  `errors`;




## Accessing another instance conformer
```php`
	function conformer_function($v, $context){
		$context['instance']->conformers['name']->METHOD($v, $context);
	}
```



## Integration
### API
`\Grithin\Conform` is integrated into `\Grithin\Api`.  See the documentation there


### Other
Custom implementations of `\Grithin\Template` use a javascript interface between backend data and the front end logic through a JS variable `site` (set up normally in the `top.php` template), where Template supplies `site.backend_data`.

```
try{
	Conform::validate(['user_field'=>'!v.filled'])

	// process input

}catch{\Exception $e}{
	$page_errors = $e->details->errors;

	// Using \Grithin\Template set up to handle errors
	foreach($pages_errors as $error){
		$Template->error_add($error['message']);
	}

	// Adding directly to backend_data in \Grithin\Template
	$this->backend_data['errors'] = $pages_errors;


	$Template->end_current();
}

```
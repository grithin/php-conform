# PHP Conform Input
Ordered filtering and validation with standard input logic.


## About
Back around 2009, I wanted to build something that would resolve having to code the normal input logic in situations like
-	if x field failed, don't check y field
-	if x field failed one step of validation, don't validate it further
-	if x field failed validation, stop validating all input and return with error

There are a lot of combinations of this input field dependent logic which I thought could be simplified in expression.  So, I created this class, which has changed through the years.  I've recently updated it to be more compatible for others to use (no short tags, better code standard).



## Use

### Smallest Example
```php
# Simplest example
$Conform = new Conform(['age'=>' 10 ']);
if($conformed = $Conform->get(['age'=>'f.int'])){
	# in converting age to in, Conform has stripped the spaces
	echo $conformed['age']; # => 10
}

```


### Setting The Input
```php
# You can set the input either at construction or after
$Conform = new Conform(['name'=>'bob']);
$Conform->input(['name'=>'sue']);

# if you do not provide an input on the construction (input value is false), Conform will use $_GET and $_POST as defaults, with $_POST overwriting colliding $_GET keys
$_POST = ['name'=>'bob'];
$Conform = new Conform();
$Conform->input(); # > ['name'=>'bob']

# if you want to set the input to the default input after construction, you can use the `request_input` method
$Conform->input(Conform::request_input());

# the request_input has some additional logic about pulling input from the request that include both the use of a _json key and the handling of request type application/json.  To see more, read the function doc.
```



### Using Default Conformers
There are three `conformers` that come auto-attached to a Conform instance: `\Grithin\Conform\Filter`, `\Grithin\Conform\Validate`, and \Grithin\Conform\GlobalFunction.  These are represented in rules by the prefixes `f`, `v`, and `g`.  

To filter an input to an int, you can use `f.int`, but to validate the input is an int, you would use `v.int`.  Filter and validate can be used in conjuction because the value passed to a proceeding rule is the output from the preceeding rule.  For instance, we can filter to digits, and then check of the result is a valid int
```php
$Conform = new Conform(['age'=>'bob']);
$rules = ['age'=>'f.digits, v.int'];

if($conformed = $Conform->get($rules)){
	# ...
}else{
	echo 'did not validate';
}
# > did not validate
```

`g` is used to reference a global function.
```php
$rules = ['age'=>'g.strtoupper'];
```
(The $Conform parameter will not be passed to the global function)


### Adding Conformers
Use the `conformer_add` function.
```php
$Conform->conformer_add($string_identity, $pathed_callable);
```

The pathed callable must be callable at the path provided by the rule (according to lodash style pathing for `_.get`)

The callable receives the $Conform instance as the final parameter.

You can add various types of conformers
```php
# a closure
$Conform->conformer_add('upper', function($x){ return strtoupper($x);});
$rules = ['name'=>'upper'];

# an object with methods
$Conform->conformer_add('f', new \Grithin\Conform\Filter);
$rules = ['id'=>'f.int'];

# a function reference
$Conform->conformer_add('upper', 'strtoupper');
$rules = ['name'=>'upper']; # note, this will error b/c strtoupper does not expect 2 parameters ($Conform instance is passed as the last parameter)
```


### Using Flags

Form validation logic mostly follows patterns.

For example, if the id input is an int, check it in the database:
```php
$rules = ['id' => `!v.int, db.check`];
```
The `!` says, ensure `id` input exists as an int before proceeding to the following rules on the field.  In this way, we don't attempt to check the database with input that might be some arbitrary string.

What if we had multiple fields that relied on resolving a user id?  You can exit out of validation entirely:
```php
$rules = [
	'id' => `!!v.int, !!db.check`,
	'name' => 'db.matches_id'
];
```
Here, the `!!` says, if `id` is not an int, exit with fail and parse no more fields.  And if `db.check` fails, also exit with fail.


Sometimes there are optional fields that we still want to filter if they are present.  To do this, you can combine two prefixes.
```php
$rules = [
	'email' => `?!v.filled, v.email`,
];
```
This says, if the field is not filled, stop applying the rule, but don't show as an error.  This way, if the user left the field empty, there will be no email validation and no error, but if the user had filled out the email input, there will be email validation.  (The order of `!` and `?` doesn't matter.)


#### Special Cases
There are prefixes for some rarer cases.

What if we wanted to collect multiple validation errors for a field, but then not process some ending rules b/c of the errors?
```php
$rules = [
	'email' => `v.filled, v.email, &db.check`,
];
```
If the email field were not filled, this field rule set would result in an error for not being filled and an error for not validating as an email.  The `&` prefix indicates, if there were any errors in the previous part of the rule chain, stop execution of rules for the field.

This can similarly be done for the entire form.  What if we wanted to collect errors across multiple fields, but wanted the presence of those errors to prevent some ending rule exectuion?

```php
$rules = [
	'id' => `!v.int, db.check`,
	'name' => 'v.name, &&db.matches_id'
];
```
Here, by use of the `&&`, if there were any errors in any of the previous chain or previous fields, the proceeding rule will not execute.  

Finally, some times the reverse of a rule is desired.  For instance, what if I wanted an email that was unique in the database but I just has a `email_exists` conformer function?
```php
$rules = [
	'email' => `~db.email_exists`,
];
```
Here, the `~` is like a "not", and indicates a lack of error indicates an error.



### Rule Format And Parameters

Parameters may be passed to a conformer in addition to the value of the input.
```php
$rules = ['age'=>'!v.range|18;60'];
```
Here we see the `|` separates the parameters from the function path, and the `;` separates the parameters from themselves.
This form is the short form.  Sometimes the use of `!` or `;` can be problematic, so there are long forms

```php
# array seperated rules
$rules = ['age'=>['!v.int', '!v.range|18;60']];
# array separated parameters
$rules = ['age'=>[['!v.range', 18, 60]]];
# array separated callable
$rules = ['age'=>[[['!', 'v.range'], 18, 60]]];
```
Note, with the array separated callable, the function itself can be a callable instead of a path.
```php
$rules = [
	'age'=>[
		[['!', function(){}], 18, 60]]
	];
```


### Retrieving Partial Output
Although `get` returns false if there were any errors, the Conform output for the fields that did not have errors is available with `$Conform->output`
```php
$input = ['name' => 'bob', 'age' => 2];
$Conform = new Conform($input);
$conformed = $Conform->get(['name'=>'v.int', 'age'=>'v.int']);
# > false
$Conform->output;
# > ['age'=>2]

```


### Validate And Filter Alone
You can use Validate and Filter pseudo statically, b/c they are traited with SingletonDefault.

```php
Filter::init()->url($url)
Validate::init()->test('url', $url);
```





## Rule Item Prefixes
The prefix can be some combination of the following
-	"!" to break on error with no more rules for that field should be applied
-	"!!" to break on error with no more rules for any field should be applied
-	"?" to indicate the validation is optional, and not to throw an error (useful when combined with '!' => '?!v.filled,email')
-	"~" to indicate if the validation does not fail, then there was an error.  Note, the original value (passed in to the function) will be pushed forward
-	"&" to indicate code should break if there were any previous errors on that field
-	"&&" to indicate code should break if there were any previous errors on any field in the validate run





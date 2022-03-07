# Ruler for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/henzeb/ruler-laravel.svg?style=flat-square)](https://packagist.org/packages/henzeb/ruler-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/henzeb/ruler-laravel.svg?style=flat-square)](https://packagist.org/packages/henzeb/ruler-laravel)

This library allows you to use your Rule as a string, just like you use existing validators like `required`, `required_if`
or `unique`.

For example: Laravel has bundled a rule for enums. As the parameter you pass is a string, you should be able to use it
like this:

```php
 [
   'attribute'=>'required|enum:App\Enums\YourEnum'
 ]
```

right?

wrong!

In order to use it like this, you need to extend your Validator
and use the `Illuminate\Validation\Rules\Enum` rule, or create your
own version. This library simplifies that process.

I've gone ahead and registered `enum` for you, but you can just as easily add your own rules.

## Installation

You can install the package via composer:

```bash
composer require henzeb/ruler-laravel
```

note: I only support PHP ^8.1 and Laravel ^8.69 and ^9.0 because of the enums.

## Usage

Simply add the `Henzeb\Ruler\Concerns\Ruler` trait to your service provider and
add your rules to the `$rules` property.

```php
use Henzeb\Ruler\Concerns\Ruler;
use Illuminate\Support\ServiceProvider;
use App\Rules\YourRule;
use App\Rules\YourOtherRule;

class YourProvider extends ServiceProvider
{
    use Ruler;
    
    private array $rules = [
        'your_rule' => YourRule::class,
        YourOtherRule::class
    ]; 
}
```

You can either specify a name, or just let Ruler create a name for you.

For example: `YourOtherRule` will get the name `your_other_rule`.

If your provider needs to implement the boot method, just call the `bootRuler`
method inside that `boot` method

```php
use Henzeb\Ruler\Concerns\Ruler;
use Illuminate\Support\ServiceProvider;
use App\Rules\YourRule;
use App\Rules\YourOtherRule;

class YourProvider extends ServiceProvider
{
    use Ruler;
    
    private array $rules = [
        'your_rule' => YourRule::class,
        YourOtherRule::class
    ]; 
    
    public function boot() {
        $this->bootRuler();
        // your code
    }
}
```

It is also possible to do it yourself in case you need to do it conditionally.

```php
use Henzeb\Ruler\Concerns\Ruler;
use Illuminate\Support\ServiceProvider;
use App\Rules\YourRule;
use App\Rules\YourOtherRule;

class YourProvider extends ServiceProvider
{
    use Ruler;
    
    public function boot() {
        if(/** some condition */) {
            $this->rule(YourRule::class, 'your_rule');
        }
        
        if(/** some condition */) {
            $this->rule(YourOtherRule::class);
        }
        // your code
    }
}
```

### The Rule class

The rules are implemented just like any other `Rule` you'd normally define.
So you'll be familiar with the implementation.

```php
use Illuminate\Contracts\Validation\Rule;

class YourRule implements Rule {

    public function passes($attribute, $value)
    {
        // your validation code
    }
    
    public function message() 
    {
        return 'Message when fails';
    }
}
```

#### Parameters

You can use parameters. Just add a constructor with the parameters in the order
you'd like to use them. Optional parameters are supported.

Note: As for now, you'll receive them as strings, no casting to other scalars is
done at this time.

```php
public function __construct(private string $param1, private string $param2 = null){}
```

#### Implicit rules

To add an implicit rule, all you have to do is implement
the `Illuminate\Contracts\Validation\ImplicitRule` interface.
Ruler will do the rest for you.

```php
use Illuminate\Contracts\Validation\ImplicitRule;

class YourImplicitRule implements ImplicitRule {
    // the code
}
```

#### Dependent rules

To add a dependent rule, just implement
the `Illuminate\Contracts\Validation\DataAwareRule` interface.
interface Ruler will do the rest for you.

```php
use Illuminate\Contracts\Validation\DataAwareRule;

class DependentRule implements DataAwareRule {
    private array $data;
    
    public function setData(array $data) {
        $this->data = $data;
        return $this;
    }
    
    // your code 
}
```

#### mixing up interfaces

You can mix up the interfaces just like you would in vanilla Laravel.
For instance: A rule that is `implicit` can also
be `dependent`

### The error message

The error message should be placed in the `message` method
as defined in `Illuminate\Contracts\Validation\Rule`,
just as you normally would.

The message method is called dynamically, which means you can store the message in your 
`Rule` instance and return it in the `message` method.

```php
use Henzeb\Ruler\Contracts\ReplacerAwareRule;
use Illuminate\Contracts\Validation\Rule;

class YourRule implements Rule
{
    return string $message = 'Something went wrong';
    
    public __construct($param_1, $paramTwo) {}
    
    public function passes($attribute, $value)
    {
        $this->message = 'Your error message';
        return false;
    }
    
    public function message()
    {
        return return $this->message;
    }
}
```

It also supports returning arrays. When an array is returned, the `MessageBag` contains
the messages as if they were coming from different validation rules. This way your `Rule`
can do grouped validations (for instance using another Validator instance).

#### replacers

Out of the box, you can use `:<number>` to point to a parameter, but if you want them named,
you can use the `Henzeb\Ruler\Contracts\ReplacerAwareRule` interface.

```php
use Henzeb\Ruler\Contracts\ReplacerAwareRule;

class YourRule implements ReplacerAwareRule
{
    public __construct($param_1, $paramTwo) {}
    
    public function message()
    {
        return ':attribute :param_1 :paramTwo';
    }

    public function replacers(): array
    {
        return [
            'param_1',
            'paramTwo'
        ];
    }
}
```

The parameters are in order as specified. `param_1` will point to the value of `$param_1` and so on.

#### Closures

You can add a `Closure` to a replacer if you have specific needs. A `Closure` always
receives the value of the current parameter, the attributes name, the other parameters (named)
and all the fields that are under validation.

```php
class YourRule implements ReplacerAwareRule
{
    public function message()
    {
        return ':attribute :param_1 :paramTwo';
    }

    public function replacers(): array
    {
        return [
            'param_1'=> function(string $value, string $attribute, array $parameters, array $data) {
                return 'any string'.$parameters['paramTwo']
            },
            'paramTwo'
        ];
    }
}
```

### Overriding the Validator resolver
Because Ruler has a custom Validator instance set to resolve by Laravel, you need to extend
the `Henzeb\Ruler\Validator\RulerValidator` class in case you want to change the resolver.

### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email henzeberkheij@gmail.com instead of using the issue tracker.

## Credits

- [Henze Berkheij](https://github.com/henzeb)

## License

The GNU AGPLv. Please see [License File](LICENSE.md) for more information.

<?php

use Henzeb\Ruler\Concerns\Ruler;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Support\Facades\Validator;
use Henzeb\Ruler\Validator\RulerValidator;
use Henzeb\Ruler\Tests\Fixtures\BasicRule;
use Henzeb\Ruler\Tests\Fixtures\DependentRule;
use Henzeb\Ruler\Tests\Fixtures\ArrayMessageRule;
use Henzeb\Ruler\Tests\Fixtures\InvalidRuleClass;
use Henzeb\Ruler\Tests\Fixtures\InvokableTestRule;
use Henzeb\Ruler\Tests\Fixtures\ParameterizedRule;
use Henzeb\Ruler\Tests\Fixtures\SimpleImlicitRule;
use Henzeb\Ruler\Tests\Fixtures\WithReplacersRule;
use Henzeb\Ruler\Tests\Fixtures\DynamicMessageRule;
use Henzeb\Ruler\Tests\Fixtures\DynamicMessagesRule;
use Henzeb\Ruler\Tests\Fixtures\WithReplacerWithCallbackRule;

uses(Ruler::class);

beforeEach(function () {
    $this->rules = [BasicRule::class];

    Validator::resolver(function ($translator, $data, $rules, $messages, $customAttributes) {
        return new RulerValidator($translator, $data, $rules, $messages, $customAttributes);
    });
});

it('should extend validator using classname as rulename', function () {
    Validator::partialMock()->expects('extend')
        ->once()
        ->withSomeOfArgs('basic_rule');

    $this->rule(BasicRule::class);
});

it('should extend validator with given name', function () {
    Validator::partialMock()->expects('extend')
        ->once()
        ->withSomeOfArgs('myRandomName');

    $this->rule(BasicRule::class, 'myRandomName');
});

it('should fail with message', function (string $rule, string|Rule $extension, array $expected) {
    $this->rule($extension, $rule);

    expect(
        Validator::make(
            ['test' => 'test'],
            ['test' => $rule]
        )->getMessageBag()->toArray()
    )->toBe(['test' => $expected]);
})->with([
    'string-given' => ['byString', BasicRule::class, ['This is the message']],
    'instance-given' => ['byInstance', new BasicRule(), ['This is the message']],
    'instance-that-returns-array-as-message' => [
        'messageReturnsArray',
        new ArrayMessageRule(),
        ['This is the message', 'Another message'],
    ],
]);

it('should pass when given correct value', function () {
    $this->rule(BasicRule::class, 'testUsingValue');

    expect(
        Validator::make(
            ['test' => 'correctValue'],
            ['test' => 'testUsingValue']
        )->passes()
    )->toBeTrue();
});

it('should throw exception when is not a rule', function () {
    $this->rule(InvalidRuleClass::class, 'invalidRule');
})->throws(
    RuntimeException::class,
    'Validation rule \'invalidRule\' should be an instance of \'' . Rule::class
    . '\' or \'' . InvokableRule::class . '\''
);

it('should throw exception when is not a rule without rule name', function () {
    $this->rule(InvalidRuleClass::class);
})->throws(
    RuntimeException::class,
    'Validation rule \'invalid_rule_class\' should be an instance of \'' . Rule::class
    . '\' or \'' . InvokableRule::class . '\''
);

it('should get message with parameters replaced', function (string $class, string $parameters, string $expectedMessage) {
    $this->rule($class, 'test');

    expect(
        Validator::make(
            ['myAttribute' => 'test'],
            ['myAttribute' => 'test:' . $parameters]
        )->getMessageBag()->toArray()
    )->toBe([
        'myAttribute' => [$expectedMessage],
    ]);
})->with([
    'numeric-keys' => [
        'class' => ParameterizedRule::class,
        'parameters' => 'true,value',
        'expectedMessage' => 'my attribute true value',
    ],
    'numeric-keys-with-different-parameters' => [
        'class' => ParameterizedRule::class,
        'parameters' => 'false,test',
        'expectedMessage' => 'my attribute false test',
    ],
    'replacer-aware-parameters' => [
        'class' => WithReplacersRule::class,
        'parameters' => 'true,value',
        'expectedMessage' => 'my attribute true value',
    ],
    'different-replacer-aware-parameters' => [
        'class' => WithReplacersRule::class,
        'parameters' => 'false,test',
        'expectedMessage' => 'my attribute false test',
    ],
    'replacer-aware-callbacks-and-parameters' => [
        'class' => WithReplacerWithCallbackRule::class,
        'parameters' => 'true,value',
        'expectedMessage' => 'my attribute should equal value',
    ],
    'replacer-aware-callbacks-and-different-parameters' => [
        'class' => WithReplacerWithCallbackRule::class,
        'parameters' => 'false,test',
        'expectedMessage' => 'my attribute should not equal test',
    ],
]);

it('should register as implicit', function () {
    Validator::spy()->expects('extendImplicit')->once();

    $this->rule(SimpleImlicitRule::class, 'implicitRule');
});

it('should register as implicit when rule has implicit property set to true', function () {
    $rule = new class implements \Illuminate\Contracts\Validation\ValidationRule {
        public bool $implicit = true;

        public function validate(string $attribute, mixed $value, \Closure $fail): void
        {
        }
    };

    Validator::partialMock()->expects('extendImplicit')->once();

    $this->rule($rule, 'implicitByProperty');
});

it('should not register as implicit when rule has implicit property set to false', function () {
    $rule = new class implements \Illuminate\Contracts\Validation\ValidationRule {
        public bool $implicit = false;

        public function validate(string $attribute, mixed $value, \Closure $fail): void
        {
        }
    };

    Validator::partialMock()->shouldNotReceive('extendImplicit');

    $this->rule($rule, 'notImplicitByProperty');
});

it('should register as dependent', function () {
    Validator::spy()->expects('extendDependent')->once();

    $this->rule(DependentRule::class, 'dependentRule');
});

it('should be able to access other attributes under validation', function () {
    $this->rule(DependentRule::class, 'dependent');

    expect(
        Validator::make(
            [
                'first_field' => 'test',
                'other_field' => 'test',
            ],
            ['first_field' => 'dependent']
        )->passes()
    )->toBeTrue();
});

it('registers rules without key specified', function () {
    Validator::spy()->expects('extend')->once();

    $this->rules([BasicRule::class]);
});

it('rules should extend validator', function () {
    Validator::spy()->expects('extend')->once();

    $this->rules(['basic' => BasicRule::class]);
});

it('rules should extend validator with multiple rules', function () {
    Validator::spy()->expects('extend')->twice();

    $this->rules(['basic' => BasicRule::class, 'another' => DependentRule::class]);
});

it('should boot ruler with boot method', function () {
    Validator::partialMock()->expects('extend')
        ->withSomeOfArgs('basic_rule');

    $this->boot();
});

it('rules should have dynamic messages', function () {
    $this->rule(DynamicMessagesRule::class, 'dynamic');

    expect(
        Validator::make(
            ['test_field' => 'testMe'],
            ['test_field' => 'dynamic']
        )->messages()->toArray()
    )->toBe([
        'test_field' => [
            'This is a message',
            'This is another message',
        ],
    ]);
});

it('rules should have dynamic message', function () {
    $this->rule(DynamicMessageRule::class, 'dynamic');

    expect(
        Validator::make(
            ['test_field' => 'testMe'],
            ['test_field' => 'dynamic']
        )->messages()->toArray()
    )->toBe([
        'test_field' => [
            'This is a message for test_field',
        ],
    ]);
});

it('should allow invokable rule', function () {
    $this->rule(InvokableTestRule::class, 'invokable');

    expect(
        Validator::make(
            ['test_field' => 'testMe'],
            ['test_field' => 'invokable']
        )->messages()->toArray()
    )->toBe([]);

    expect(
        Validator::make(
            ['test_field' => 'testMe'],
            ['test_field' => 'invokable:1']
        )->messages()->toArray()
    )->toBe([
        'test_field' => ['shouldFail'],
    ]);
})->skip(!interface_exists('Illuminate\Contracts\Validation\InvokableRule'));

it('should allow validation rule', function () {
    $this->rule(InvokableTestRule::class, 'invokable');

    expect(
        Validator::make(
            ['test_field' => 'testMe'],
            ['test_field' => 'invokable']
        )->messages()->toArray()
    )->toBe([]);

    expect(
        Validator::make(
            ['test_field' => 'testMe'],
            ['test_field' => 'invokable:1']
        )->messages()->toArray()
    )->toBe([
        'test_field' => ['shouldFail'],
    ]);
})->skip(!interface_exists('Illuminate\Contracts\Validation\ValidationRule'));

it('should allow multiple instances of the same rule', function () {
    $this->rule(DynamicMessageRule::class, 'dynamic');

    expect(
        Validator::make(
            [
                'test_field' => 'testMe',
                'test_field2' => 'testMe',
            ],
            [
                'test_field' => 'dynamic',
                'test_field2' => 'dynamic',
            ]
        )->messages()->toArray()
    )->toBe([
        'test_field' => [
            'This is a message for test_field',
        ],
        'test_field2' => [
            'This is a message for test_field2',
        ],
    ]);
});

it('ruler should still be able to pass laravel validation messages', function () {
    expect(
        Validator::make(
            ['a_field' => 'string'],
            ['a_field' => 'array|prohibited_unless:another_field,test']
        )->messages()->toArray()
    )->toBe([
        'a_field' => [
            'The a field field must be an array.',
            'The a field field is prohibited unless another field is in test.',
        ],
    ]);
});

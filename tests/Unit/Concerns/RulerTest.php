<?php

namespace Henzeb\Ruler\Tests\Unit\Concerns;


use RuntimeException;
use ReflectionException;
use Henzeb\Ruler\Concerns\Ruler;
use Orchestra\Testbench\TestCase;
use Illuminate\Contracts\Validation\Rule;
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
use Illuminate\Contracts\Validation\InvokableRule;
use Henzeb\Ruler\Tests\Fixtures\DynamicMessageRule;
use Henzeb\Ruler\Tests\Fixtures\DynamicMessagesRule;
use Henzeb\Ruler\Tests\Fixtures\WithReplacerWithCallbackRule;

use function version_compare;
use function interface_exists;


class RulerTest extends TestCase
{
    use Ruler;

    private array $rules = [
        BasicRule::class
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Validator::resolver(function ($translator, $data, $rules, $messages, $customAttributes) {
            return new RulerValidator($translator, $data, $rules, $messages, $customAttributes);
        });
    }

    public function testShouldExtendValidatorUsingClassnameAsRulename()
    {
        Validator::partialMock()->expects('extend')
            ->once()
            ->withSomeOfArgs('basic_rule');

        $this->rule(BasicRule::class);
    }

    public function testShouldExtendValidatorWithGivenName()
    {
        Validator::partialMock()->expects('extend')
            ->once()
            ->withSomeOfArgs('myRandomName');

        $this->rule(BasicRule::class, 'myRandomName');
    }

    public static function providesTestcasesForShouldGetMessage(): array
    {
        return [
            'string-given' => ['byString', BasicRule::class, ['This is the message']],
            'instance-given' => ['byInstance', new BasicRule(), ['This is the message']],
            'instance-that-returns-array-as-message' => [
                'messageReturnsArray',
                new ArrayMessageRule(),
                ['This is the message', 'Another message']
            ],
        ];
    }

    /**
     * @param string $rule
     * @param string|Rule $extension
     * @return void
     * @throws ReflectionException
     *
     * @dataProvider providesTestcasesForShouldGetMessage
     */
    public function testShouldFailWithMessage(string $rule, string|Rule $extension, array $expected): void
    {
        $this->rule($extension, $rule);

        $this->assertEquals(
            [
                'test' => $expected
            ],
            Validator::make(
                [
                    'test' => 'test'
                ],
                [
                    'test' => $rule
                ]
            )->getMessageBag()->toArray()
        );
    }

    public function testShouldPassWhenGivenCorrectValue()
    {
        $this->rule(BasicRule::class, 'testUsingValue');

        $this->assertTrue(
            Validator::make(
                [
                    'test' => 'correctValue'
                ],
                [
                    'test' => 'testUsingValue'
                ]
            )->passes()
        );
    }

    public function testShouldThrowExceptionWhenIsNotARule(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Validation rule \'invalidRule\' should be an instance of \'' . Rule::class
            . '\' or \'' . InvokableRule::class . '\''
        );

        $this->rule(InvalidRuleClass::class, 'invalidRule');
    }

    public function testShouldThrowExceptionWhenIsNotARuleWithoutRuleName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Validation rule \'invalid_rule_class\' should be an instance of \'' . Rule::class
            . '\' or \'' . InvokableRule::class . '\''
        );

        $this->rule(InvalidRuleClass::class);
    }

    public static function providesTestCasesForShouldGetMessagesWithParametersReplaced(): array
    {
        return [
            'numeric-keys' =>
                [
                    'rule' => ParameterizedRule::class,
                    'parameters' => 'true,value',
                    'expectedMessage' => 'my attribute true value'
                ],
            'numeric-keys-with-different-parameters' =>
                [
                    'rule' => ParameterizedRule::class,
                    'parameters' => 'false,test',
                    'expectedMessage' => 'my attribute false test'
                ],

            'replacer-aware-parameters' =>
                [
                    'rule' => WithReplacersRule::class,
                    'parameters' => 'true,value',
                    'expectedMessage' => 'my attribute true value'
                ],
            'different-replacer-aware-parameters' =>
                [
                    'rule' => WithReplacersRule::class,
                    'parameters' => 'false,test',
                    'expectedMessage' => 'my attribute false test'
                ],

            'replacer-aware-callbacks-and-parameters' =>
                [
                    'rule' => WithReplacerWithCallbackRule::class,
                    'parameters' => 'true,value',
                    'expectedMessage' => 'my attribute should equal value'
                ],
            'replacer-aware-callbacks-and-different-parameters' =>
                [
                    'rule' => WithReplacerWithCallbackRule::class,
                    'parameters' => 'false,test',
                    'expectedMessage' => 'my attribute should not equal test'
                ],
        ];
    }

    /**
     * @param string $class
     * @param string $parameters
     * @param string $expectedMessage
     * @return void
     * @throws ReflectionException
     *
     * @dataProvider providesTestCasesForShouldGetMessagesWithParametersReplaced
     */

    public function testShouldGetMessageWithParametersReplaced(
        string $class,
        string $parameters,
        string $expectedMessage
    )
    {
        $this->rule($class, 'test');
        $this->assertEquals(
            [
                'myAttribute' => [
                    $expectedMessage
                ]
            ],
            Validator::make(
                [
                    'myAttribute' => 'test'
                ],
                [
                    'myAttribute' => 'test:' . $parameters
                ]
            )->getMessageBag()->toArray()
        );
    }

    public function testShouldRegisterAsImplicit()
    {
        Validator::spy()->expects('extendImplicit')->once();

        $this->rule(SimpleImlicitRule::class, 'implicitRule');
    }

    public function testShouldRegisterAsDependent()
    {
        Validator::spy()->expects('extendDependent')->once();

        $this->rule(DependentRule::class, 'dependentRule');
    }

    public function testShouldBeAbleToAccessOtherAttributesUnderValidation()
    {
        $this->rule(DependentRule::class, 'dependent');

        $this->assertTrue(
            Validator::make(
                [
                    'first_field' => 'test',
                    'other_field' => 'test',
                ],
                [
                    'first_field' => 'dependent'
                ]
            )->passes()
        );
    }

    public function testRulesWithoutKeySpecified()
    {
        Validator::spy()->expects('extend')->once();

        $this->rules([BasicRule::class]);
    }

    public function testRulesShouldExtendValidator()
    {
        Validator::spy()->expects('extend')->once();

        $this->rules(['basic' => BasicRule::class]);
    }

    public function testRulesShouldExtendValidatorWithMultipleRules()
    {
        Validator::spy()->expects('extend')->twice();

        $this->rules(['basic' => BasicRule::class, 'another' => DependentRule::class]);
    }

    public function testShouldBootRulerWhithBootMethod()
    {
        Validator::partialMock()->expects('extend')
            ->withSomeOfArgs('basic_rule');

        $this->boot();
    }

    public function testRulesShouldHaveDynamicMessages()
    {
        $this->rule(DynamicMessagesRule::class, 'dynamic');

        $this->assertEquals(
            [
                'test_field' => [
                    'This is a message',
                    'This is another message'
                ]
            ],
            Validator::make(
                [
                    'test_field' => 'testMe',
                ],
                [
                    'test_field' => 'dynamic'
                ]
            )->messages()->toArray()
        );
    }

    public function testRulesShouldHaveDynamicMessage()
    {
        $this->rule(DynamicMessageRule::class, 'dynamic');

        $this->assertEquals(
            [
                'test_field' => [
                    'This is a message for test_field',
                ]
            ],
            Validator::make(
                [
                    'test_field' => 'testMe',
                ],
                [
                    'test_field' => 'dynamic'
                ]
            )->messages()->toArray()
        );
    }

    public function testShouldAllowInvokableRule()
    {
        if (!interface_exists('Illuminate\Contracts\Validation\InvokableRule')) {
            $this->markTestSkipped();
            return;
        }
        $this->rule(InvokableTestRule::class, 'invokable');

        $this->assertEquals(
            [],
            Validator::make(
                [
                    'test_field' => 'testMe',
                ],
                [
                    'test_field' => 'invokable'
                ]
            )->messages()->toArray()
        );

        $this->assertEquals(
            [
                'test_field' => [
                    'shouldFail'
                ]
            ],
            Validator::make(
                [
                    'test_field' => 'testMe',
                ],
                [
                    'test_field' => 'invokable:1'
                ]
            )->messages()->toArray()
        );
    }

    public function testShouldAllowValidationRule()
    {
        if (!interface_exists('Illuminate\Contracts\Validation\ValidationRule')) {
            $this->markTestSkipped();
        }
        $this->rule(InvokableTestRule::class, 'invokable');

        $this->assertEquals(
            [],
            Validator::make(
                [
                    'test_field' => 'testMe',
                ],
                [
                    'test_field' => 'invokable'
                ]
            )->messages()->toArray()
        );

        $this->assertEquals(
            [
                'test_field' => [
                    'shouldFail'
                ]
            ],
            Validator::make(
                [
                    'test_field' => 'testMe',
                ],
                [
                    'test_field' => 'invokable:1'
                ]
            )->messages()->toArray()
        );
    }

    public function testShouldAllowMultipleInstancesOfTheSameRule()
    {
        $this->rule(DynamicMessageRule::class, 'dynamic');

        $this->assertEquals(
            [
                'test_field' => [
                    'This is a message for test_field',
                ],
                'test_field2' => [
                    'This is a message for test_field2',
                ]
            ],
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
        );
    }


    public function testRulerShouldStillBeAbleToPassLaravelValidationMessages()
    {
        $withField = version_compare($this->app->version(), '10.0.0') >= 0;

        $this->assertEquals(
            [
                'a_field' => [
                    'The a field ' . ($withField ? 'field ' : '') . 'must be an array.',
                    'The a field field is prohibited unless another field is in test.'
                ]
            ],
            Validator::make(
                [
                    'a_field' => 'string'
                ],
                ['a_field' => 'array|prohibited_unless:another_field,test']
            )->messages()->toArray()
        );
    }
}

<?php

use Henzeb\Ruler\Mixins\RuleMixin;
use Henzeb\Ruler\Tests\Fixtures\BasicRule;
use Henzeb\Ruler\Tests\Fixtures\DependentRule;
use Henzeb\Ruler\Validator\RulerValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

beforeEach(function () {
    Rule::mixin(new RuleMixin());

    Validator::resolver(function ($translator, $data, $rules, $messages, $customAttributes) {
        return new RulerValidator($translator, $data, $rules, $messages, $customAttributes);
    });
});

it('should register a rule by class name', function () {
    Rule::register(BasicRule::class);

    expect(
        Validator::make(
            ['test' => 'correctValue'],
            ['test' => 'basic_rule']
        )->passes()
    )->toBeTrue();
});

it('should register a rule with a custom name', function () {
    Rule::register(BasicRule::class, 'my_rule');

    expect(
        Validator::make(
            ['test' => 'correctValue'],
            ['test' => 'my_rule']
        )->passes()
    )->toBeTrue();
});

it('should register a rule instance', function () {
    Rule::register(new BasicRule(), 'instance_rule');

    expect(
        Validator::make(
            ['test' => 'wrong'],
            ['test' => 'instance_rule']
        )->getMessageBag()->toArray()
    )->toBe(['test' => ['This is the message']]);
});

it('should register an array of rules', function () {
    Rule::register([
        'basic' => BasicRule::class,
        'dependent' => DependentRule::class,
    ]);

    expect(
        Validator::make(
            ['test' => 'correctValue'],
            ['test' => 'basic']
        )->passes()
    )->toBeTrue();

    expect(
        Validator::make(
            ['first_field' => 'test', 'other_field' => 'test'],
            ['first_field' => 'dependent']
        )->passes()
    )->toBeTrue();
});

it('should register an array of rules without keys', function () {
    Rule::register([BasicRule::class]);

    expect(
        Validator::make(
            ['test' => 'correctValue'],
            ['test' => 'basic_rule']
        )->passes()
    )->toBeTrue();
});

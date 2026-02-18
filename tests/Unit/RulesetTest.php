<?php

use Henzeb\Ruler\Concerns\Ruler;
use Henzeb\Ruler\Ruleset;
use Henzeb\Ruler\Tests\Fixtures\TestRuleset;
use Henzeb\Ruler\Validator\RulerValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

uses(Ruler::class);

beforeEach(function () {
    $this->rules = [];

    Validator::resolver(function ($translator, $data, $rules, $messages, $customAttributes) {
        return new RulerValidator($translator, $data, $rules, $messages, $customAttributes);
    });
});

it('should pass when all rules pass', function () {
    expect(
        Validator::make(
            ['name' => 'John'],
            ['name' => [new TestRuleset()]]
        )->passes()
    )->toBeTrue();
});

it('should fail with correct messages when rules fail', function () {
    expect(
        Validator::make(
            ['name' => 'ab'],
            ['name' => [new TestRuleset()]]
        )->messages()->toArray()
    )->toBe([
        'name' => [
            'The name field must be at least 3 characters.',
        ],
    ]);
});

it('should fail with required message when value is empty', function () {
    expect(
        Validator::make(
            ['name' => ''],
            ['name' => [new TestRuleset()]]
        )->messages()->toArray()
    )->toBe([
        'name' => [
            'The name field is required.',
        ],
    ]);
});

it('should work with data-aware rules', function () {
    $ruleset = new class extends Ruleset {
        protected function rules(): array
        {
            return ['same:other_field'];
        }
    };

    expect(
        Validator::make(
            ['field' => 'value', 'other_field' => 'value'],
            ['field' => [$ruleset]]
        )->passes()
    )->toBeTrue();

    $ruleset = new class extends Ruleset {
        protected function rules(): array
        {
            return ['same:other_field'];
        }
    };

    expect(
        Validator::make(
            ['field' => 'value', 'other_field' => 'different'],
            ['field' => [$ruleset]]
        )->passes()
    )->toBeFalse();
});

it('should produce messages identical to inline usage', function () {
    $inlineMessages = Validator::make(
        ['name' => 'ab'],
        ['name' => ['required', 'string', 'min:3']]
    )->messages()->toArray();

    $rulesetMessages = Validator::make(
        ['name' => 'ab'],
        ['name' => [new TestRuleset()]]
    )->messages()->toArray();

    expect($rulesetMessages)->toBe($inlineMessages);
});

it('should work with Rule objects mixed in', function () {
    $ruleset = new class extends Ruleset {
        protected function rules(): array
        {
            return ['required', new \Illuminate\Validation\Rules\In(['foo', 'bar'])];
        }
    };

    expect(
        Validator::make(
            ['field' => 'foo'],
            ['field' => [$ruleset]]
        )->passes()
    )->toBeTrue();

    $ruleset = new class extends Ruleset {
        protected function rules(): array
        {
            return ['required', new \Illuminate\Validation\Rules\In(['foo', 'bar'])];
        }
    };

    expect(
        Validator::make(
            ['field' => 'baz'],
            ['field' => [$ruleset]]
        )->passes()
    )->toBeFalse();
});

it('skips validation on empty value when implicit is false', function () {
    $ruleset = new TestRuleset();
    $ruleset->implicit = false;

    expect(
        Validator::make(
            ['name' => ''],
            ['name' => [$ruleset]]
        )->passes()
    )->toBeTrue();
});

it('should use custom messages', function () {
    $ruleset = new class extends Ruleset {
        protected function rules(): array
        {
            return ['required', 'string', 'min:3'];
        }

        protected function messages(): array
        {
            return ['min' => 'Too short!'];
        }
    };

    expect(
        Validator::make(
            ['name' => 'ab'],
            ['name' => [$ruleset]]
        )->messages()->toArray()
    )->toBe([
        'name' => [
            'Too short!',
        ],
    ]);
});

it('should use custom attributes', function () {
    $ruleset = new class extends Ruleset {
        protected function rules(): array
        {
            return ['required', 'string', 'min:3'];
        }

        protected function attributes(): array
        {
            return ['name' => 'username'];
        }
    };

    expect(
        Validator::make(
            ['name' => 'ab'],
            ['name' => [$ruleset]]
        )->messages()->toArray()
    )->toBe([
        'name' => [
            'The username field must be at least 3 characters.',
        ],
    ]);
});

it('should call configure on the inner validator', function () {
    $ruleset = new class extends Ruleset {
        protected function rules(): array
        {
            return ['required', 'string', 'min:5', 'max:3'];
        }

        protected function configure(ValidatorInstance $validator): void
        {
            $validator->stopOnFirstFailure();
        }
    };

    expect(
        Validator::make(
            ['name' => 'ab'],
            ['name' => [$ruleset]]
        )->messages()->toArray()
    )->toBe([
        'name' => [
            'The name field must be at least 5 characters.',
        ],
    ]);
});

it('should use configure to add sometimes logic', function () {
    $ruleset = new class extends Ruleset {
        protected function rules(): array
        {
            return ['required', 'string', 'min:3'];
        }

        protected function configure(ValidatorInstance $validator): void
        {
            $validator->sometimes('name', 'email', function ($input) {
                return strlen($input->name) > 3;
            });
        }
    };

    expect(
        Validator::make(
            ['name' => 'John'],
            ['name' => [$ruleset]]
        )->messages()->toArray()
    )->toBe([
        'name' => [
            'The name field must be a valid email address.',
        ],
    ]);

    $ruleset2 = new class extends Ruleset {
        protected function rules(): array
        {
            return ['required', 'string', 'min:3'];
        }

        protected function configure(ValidatorInstance $validator): void
        {
            $validator->sometimes('name', 'email', function ($input) {
                return strlen($input->name) > 3;
            });
        }
    };

    expect(
        Validator::make(
            ['name' => 'Joe'],
            ['name' => [$ruleset2]]
        )->passes()
    )->toBeTrue();
});

it('should accept rules as a string', function () {
    $ruleset = new class extends Ruleset {
        protected function rules(): string
        {
            return 'required|string|min:3';
        }
    };

    expect(
        Validator::make(
            ['name' => 'John'],
            ['name' => [$ruleset]]
        )->passes()
    )->toBeTrue();

    $ruleset = new class extends Ruleset {
        protected function rules(): string
        {
            return 'required|string|min:3';
        }
    };

    expect(
        Validator::make(
            ['name' => 'ab'],
            ['name' => [$ruleset]]
        )->messages()->toArray()
    )->toBe([
        'name' => [
            'The name field must be at least 3 characters.',
        ],
    ]);
});

it('should work when registered via the Ruler trait as a string-based rule', function () {
    $this->rule(TestRuleset::class, 'test_ruleset');

    expect(
        Validator::make(
            ['name' => 'John'],
            ['name' => 'test_ruleset']
        )->passes()
    )->toBeTrue();

    expect(
        Validator::make(
            ['name' => 'ab'],
            ['name' => 'test_ruleset']
        )->passes()
    )->toBeFalse();
});

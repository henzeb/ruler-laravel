<?php

namespace Henzeb\Ruler;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

abstract class Ruleset implements ValidationRule, DataAwareRule
{
    public bool $implicit = true;

    private array $data = [];

    abstract protected function rules(): string|array;

    protected function messages(): array
    {
        return [];
    }

    protected function attributes(): array
    {
        return [];
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    protected function configure(ValidatorInstance $validator): void
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validator = Validator::make(
            array_merge($this->data, [$attribute => $value]),
            [$attribute => $this->rules()],
            $this->messages(),
            $this->attributes()
        );

        $this->configure($validator);

        foreach ($validator->errors()->get($attribute) as $message) {
            $fail($message);
        }
    }
}

<?php

namespace Henzeb\Ruler\Tests\Fixtures;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class DependentRule implements Rule, DataAwareRule
{

    private array $data = [];

    public function setData($data)
    {
        $this->data = $data;
    }

    public function passes($attribute, $value)
    {
        return $value === $this->data['other_field'];
    }

    public function message()
    {
        return 'this failed';
    }
}

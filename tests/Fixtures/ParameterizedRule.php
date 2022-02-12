<?php

namespace Henzeb\Ruler\Tests\Fixtures;



use Illuminate\Contracts\Validation\Rule;

class ParameterizedRule implements Rule
{
    public function __construct(private string $shouldEqual, private string $with)
    {
    }

    public function passes($attribute, $value)
    {
        if($this->shouldEqual === 'true') {
            return $value === $this->with;
        }
        return $value !== $this->with;
    }

    public function message()
    {
        return ':attribute :0 :1';
    }
}

<?php

namespace Henzeb\Ruler\Tests\Fixtures;

use Illuminate\Contracts\Validation\Rule;

class BasicRule implements Rule
{
    public function passes($attribute, $value)
    {
        return $value === 'correctValue';
    }

    public function message()
    {
        return 'This is the message';
    }
}

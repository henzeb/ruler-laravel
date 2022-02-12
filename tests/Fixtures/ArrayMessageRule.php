<?php

namespace Henzeb\Ruler\Tests\Fixtures;

use Illuminate\Contracts\Validation\Rule;

class ArrayMessageRule implements Rule
{
    public function passes($attribute, $value)
    {
        return false;
    }

    public function message()
    {
        return ['This is the message', 'hide this'];
    }
}

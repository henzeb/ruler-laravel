<?php

namespace Henzeb\Ruler\Tests\Fixtures;

use Illuminate\Contracts\Validation\ImplicitRule;

class SimpleImlicitRule implements ImplicitRule
{

    public function passes($attribute, $value)
    {
        // TODO: Implement passes() method.
    }

    public function message()
    {
        return 'this is a message';
    }
}

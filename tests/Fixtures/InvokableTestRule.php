<?php

namespace Henzeb\Ruler\Tests\Fixtures;

use Illuminate\Contracts\Validation\InvokableRule as IlluminateInvokableRule;

class InvokableTestRule implements IlluminateInvokableRule
{
    private bool $shouldFail;

    public function __construct(
        bool $shouldFail = false
    )
    {
        $this->shouldFail = $shouldFail;
    }

    public function __invoke($attribute, $value, $fail)
    {
        if($this->shouldFail) {
            $fail('shouldFail');
        }
    }
}

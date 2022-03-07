<?php

namespace Henzeb\Ruler\Tests\Fixtures;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Validation\Rule;

class DynamicMessageRule implements Rule
{
    private string $message;

    public function __construct()
    {
    }

    public function passes($attribute, $value)
    {
        $this->message = 'This is a message for '.$attribute;

        return false;
    }

    public function message(): string
    {
        return $this->message;
    }
}

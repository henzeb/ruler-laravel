<?php

namespace Henzeb\Ruler\Tests\Fixtures;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Validation\Rule;

class DynamicMessagesRule implements Rule
{
    private Collection $messages;

    public function __construct()
    {
        $this->messages = collect();
    }

    public function passes($attribute, $value)
    {
        $this->messages->add('This is a message');
        $this->messages->add('This is another message');

        return $this->messages->isEmpty();
    }

    public function message(): array
    {
        return $this->messages->all();
    }
}

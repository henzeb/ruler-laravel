<?php

namespace Henzeb\Ruler\Tests\Fixtures;

use Henzeb\Ruler\Ruleset;

class TestRuleset extends Ruleset
{
    protected function rules(): array
    {
        return ['required', 'string', 'min:3'];
    }
}

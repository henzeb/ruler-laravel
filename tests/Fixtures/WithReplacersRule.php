<?php

namespace Henzeb\Ruler\Tests\Fixtures;


use Henzeb\Ruler\Contracts\ReplacerAwareRule;

class WithReplacersRule extends ParameterizedRule implements ReplacerAwareRule
{
    public function message()
    {
        return ':attribute :shouldEqual :with';
    }

    public function replacers(): array
    {
        return [
            'shouldEqual',
            'with'
        ];
    }
}

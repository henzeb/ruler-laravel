<?php

namespace Henzeb\Ruler\Tests\Fixtures;


use Henzeb\Ruler\Contracts\ReplacerAwareRule as ReplacerAwareRuleInterface;

class WithReplacerWithCallbackRule extends WithReplacersRule implements ReplacerAwareRuleInterface
{
    public function message()
    {
        return ':attribute :shouldEqual :with';
    }

    public function replacers(): array
    {
        return [
            'shouldEqual' => fn(string $shouldEqual) => $shouldEqual === 'true' ? 'should equal' : 'should not equal',
            'with'
        ];
    }
}

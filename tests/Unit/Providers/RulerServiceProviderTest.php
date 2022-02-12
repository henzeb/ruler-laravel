<?php

namespace Henzeb\Ruler\Tests\Unit\Providers;

use Henzeb\Ruler\Providers\RulerServiceProvider;
use Henzeb\Ruler\Tests\Fixtures\TestEnum;
use Illuminate\Support\Facades\Validator;
use Orchestra\Testbench\TestCase;

/**
 * I am not testing Laravel validation here, I am testing
 * if this library properly handles the external Enum rule.
 */
class RulerServiceProviderTest extends Testcase
{
    protected function getPackageProviders($app)
    {
        return [RulerServiceProvider::class];
    }

    public function testEnumRuleShouldPass()
    {
        $this->assertTrue(
            Validator::make(
                [
                    'my_field' => 'validEnum'
                ],
                [
                    'my_field' => 'enum:' . TestEnum::class
                ]

            )->passes()
        );
    }

    public function testEnumRuleShouldFail()
    {
        $this->expectExceptionMessage('The selected my field is invalid.');

        Validator::make(
            [
                'my_field' => 'invalidEnum'
            ],
            [
                'my_field' => 'enum:' . TestEnum::class
            ]

        )->validate();
    }


}

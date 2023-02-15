<?php

namespace Henzeb\Ruler\Tests\Unit\Providers;

use Illuminate\Validation\ValidationException;
use Henzeb\Ruler\Providers\RulerServiceProvider;
use Henzeb\Ruler\Tests\Fixtures\CustomBootServiceProvider;
use Henzeb\Ruler\Tests\Fixtures\TestEnum;
use Illuminate\Support\Facades\Validator;
use Orchestra\Testbench\TestCase;

/**
 * I am not testing Laravel validation here, I am testing
 * if this library properly handles the external Enum rule.
 */
class RulerServiceProviderTest extends Testcase
{
    protected function providesServiceProviders(): array
    {
        return [
            'trait-boot' => [RulerServiceProvider::class],
            'with-custom-boot' => [CustomBootServiceProvider::class],
        ];
    }

    /**
     * @return void
     *
     * @dataProvider providesServiceProviders
     */
    public function testEnumRuleShouldPass(string $serviceProvider)
    {
        $this->app->make($serviceProvider, ['app'=>$this->app])->boot();

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

    /**
     * @return void
     *
     * @dataProvider providesServiceProviders
     */
    public function testEnumRuleShouldFail(string $serviceProvider)
    {
        $this->app->make($serviceProvider, ['app'=>$this->app])->boot();

        $this->expectException(ValidationException::class);

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

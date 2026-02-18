<?php

use Henzeb\Ruler\Providers\RulerServiceProvider;
use Henzeb\Ruler\Tests\Fixtures\CustomBootServiceProvider;
use Henzeb\Ruler\Tests\Fixtures\TestEnum;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

it('enum rule should pass', function (string $serviceProvider) {
    $this->app->make($serviceProvider, ['app' => $this->app])->boot();

    expect(
        Validator::make(
            ['my_field' => 'validEnum'],
            ['my_field' => 'enum:' . TestEnum::class]
        )->passes()
    )->toBeTrue();
})->with([
    'trait-boot' => [RulerServiceProvider::class],
    'with-custom-boot' => [CustomBootServiceProvider::class],
]);

it('enum rule should fail', function (string $serviceProvider) {
    $this->app->make($serviceProvider, ['app' => $this->app])->boot();

    Validator::make(
        ['my_field' => 'invalidEnum'],
        ['my_field' => 'enum:' . TestEnum::class]
    )->validate();
})->with([
    'trait-boot' => [RulerServiceProvider::class],
    'with-custom-boot' => [CustomBootServiceProvider::class],
])->throws(ValidationException::class);

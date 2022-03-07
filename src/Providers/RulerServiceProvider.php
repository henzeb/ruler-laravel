<?php

namespace Henzeb\Ruler\Providers;

use Validator;
use Henzeb\Ruler\Concerns\Ruler;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\ServiceProvider;
use Henzeb\Ruler\Validator\RulerValidator;

class RulerServiceProvider extends ServiceProvider
{
    use Ruler;

    protected array $rules = [
        Enum::class
    ];

    public function boot()
    {
        Validator::resolver(
            function ($translator, $data, $rules, $messages, $customAttributes) {
                return new RulerValidator(
                    $translator,
                    $data,
                    $rules,
                    $messages,
                    $customAttributes
                );
            }
        );

        $this->bootRuler();
    }
}

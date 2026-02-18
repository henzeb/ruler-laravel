<?php

namespace Henzeb\Ruler\Providers;

use Henzeb\Ruler\Concerns\Ruler;
use Henzeb\Ruler\Mixins\RuleMixin;
use Henzeb\Ruler\Validator\RulerValidator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Validator;

class RulerServiceProvider extends ServiceProvider
{
    use Ruler;

    protected array $rules = [
        Enum::class
    ];

    public function boot(): void
    {
        Rule::mixin(new RuleMixin());
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

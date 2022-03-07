<?php

namespace Henzeb\Ruler\Validator;

use Arr;
use Closure;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Validator;

class RulerValidator extends Validator
{
    protected static array $rulers = [];

    public function getMessage($attribute, $rule)
    {
        $message = parent::getMessage($attribute, $rule);

        if ($message instanceof Closure) {
            return $message();
        }

        return $message;
    }

    public function messages()
    {
        $bag = parent::messages();

        return new MessageBag(
            collect(
                $bag->toArray()
            )->map(
                function (array $item) {
                    return Arr::flatten($item);

                }
            )->toArray()
        );

    }
}

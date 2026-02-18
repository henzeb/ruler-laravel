<?php

namespace Henzeb\Ruler\Mixins;

use Closure;
use Henzeb\Ruler\Concerns\Ruler;

class RuleMixin
{
    public function register(): Closure
    {
        return function (string|object|array $extension, string $rule = null) {
            $ruler = new class {
                use Ruler;
            };

            if (is_array($extension)) {
                $ruler->rules($extension);
                return;
            }

            $ruler->rule($extension, $rule);
        };
    }
}
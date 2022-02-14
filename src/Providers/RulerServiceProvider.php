<?php

namespace Henzeb\Ruler\Providers;

use Henzeb\Ruler\Concerns\Ruler;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Enum;

class RulerServiceProvider extends ServiceProvider
{
    use Ruler;

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->rule(Enum::class);
    }
}

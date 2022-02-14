<?php

namespace Henzeb\Ruler\Tests\Fixtures;

use Henzeb\Ruler\Concerns\Ruler;
use Henzeb\Ruler\Providers\RulerServiceProvider;

class CustomBootServiceProvider extends RulerServiceProvider
{
    use Ruler;

    public function boot(): void
    {
        $this->bootRuler();
    }
}

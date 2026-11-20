<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Laravel;

use Illuminate\Support\ServiceProvider;
use Wnikk\QrCodeCute\Generator;

/**
 * Register the generator in a Laravel application.
 *
 * Laravel finds this provider automatically. The generator is immutable,
 * thus one shared instance is safe. Inject it into a class, or get it with
 * app(Generator::class). The library itself does not need Laravel.
 */
class QrCodeServiceProvider extends ServiceProvider
{
    /**
     * Register the generator as a shared instance.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Generator::class);
    }
}

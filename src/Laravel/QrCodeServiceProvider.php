<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Laravel;

use Illuminate\Support\ServiceProvider;
use Wnikk\QrCodeCute\QrCode;

/**
 * Register QrCode in a Laravel application.
 *
 * Laravel finds this provider automatically. A QrCode object is immutable,
 * thus one shared instance is safe. Inject it into a class, or get it with
 * app(QrCode::class). The library itself does not need Laravel.
 */
class QrCodeServiceProvider extends ServiceProvider
{
    /**
     * Register QrCode as a shared instance with the default design.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(QrCode::class);
    }
}

<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests\Laravel;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use Wnikk\QrCodeCute\Laravel\QrCodeServiceProvider;
use Wnikk\QrCodeCute\QrCode;

final class ServiceProviderTest extends TestCase
{
    /**
     * Get the package providers.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [QrCodeServiceProvider::class];
    }

    public function test_the_container_gives_one_shared_object(): void
    {
        $qr = resolve(QrCode::class);

        $this->assertInstanceOf(QrCode::class, $qr);
        $this->assertSame($qr, resolve(QrCode::class));
        $this->assertSame(QrCode::DEFAULTS, $qr->getOptions());
    }

    public function test_the_shared_object_does_not_change(): void
    {
        $png = resolve(QrCode::class)->shape('circle')->text('https://example.com')->toPng(64);

        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertSame('square', resolve(QrCode::class)->getOptions()['shape']);
        $this->assertSame('', resolve(QrCode::class)->getText());
        $this->assertSame(QrCode::svg('https://example.com'), resolve(QrCode::class)->text('https://example.com')->toSvg());
    }
}

<?php

declare(strict_types=1);

namespace Wnikk\QrCodeCute\Tests\Laravel;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use Wnikk\QrCodeCute\Generator;
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

    public function test_the_container_gives_one_shared_generator(): void
    {
        $generator = resolve(Generator::class);

        $this->assertInstanceOf(Generator::class, $generator);
        $this->assertSame($generator, resolve(Generator::class));
    }

    public function test_the_shared_generator_does_not_change(): void
    {
        $png = resolve(Generator::class)->shape('circle')->format('png')->size(64)->generate('https://example.com');

        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertSame('square', resolve(Generator::class)->getOptions()['shape']);
        $this->assertSame(QrCode::svg('https://example.com'), resolve(Generator::class)->generate('https://example.com'));
    }
}

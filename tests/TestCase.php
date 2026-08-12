<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Tests;

use Illuminate\Foundation\Application;
use Liberu\Ecommerce\Tax\TaxServiceProvider;
use Liberu\PackageTestbench\PackageTestCase;
use Liberu\PackageTestbench\TestUser;
use Liberu\PackageTestbench\UsesTestUser;

/**
 * The package's own base case.
 *
 * Two things it does that a domain package's does not:
 *
 * - it discovers **every** installed package's providers, not only those of its
 *   direct dependencies. Filament is nine Composer packages with nine providers,
 *   plus Livewire and Blade Icons underneath them, and only one of those is
 *   something this package requires by name. Booting the panel provider without
 *   the rest fails inside Filament rather than inside anything this package
 *   wrote.
 * - it registers the domain package's provider by name. `ecommerce-tax` ships no
 *   `extra.laravel.providers` — deliberately, so that installing it boots
 *   nothing — so nothing discovers it and the migrations it loads would be
 *   absent.
 */
abstract class TestCase extends PackageTestCase
{
    use UsesTestUser;

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('auth.providers.users.model', TestUser::class);
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_values(array_unique([
            ...$this->installedProviders(),
            TaxServiceProvider::class,
            TestPanelProvider::class,
            ...parent::getPackageProviders($app),
        ]));
    }

    /**
     * Every provider declared by anything in the installed tree.
     *
     * @return list<class-string>
     */
    private function installedProviders(): array
    {
        $file = $this->packageRoot().'/vendor/composer/installed.json';

        if (! is_file($file)) {
            return [];
        }

        /** @var array{packages?: array<int, array<string, mixed>>} $installed */
        $installed = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        $providers = [];

        foreach ($installed['packages'] ?? [] as $package) {
            /** @var array<int, string> $declared */
            $declared = (array) ($package['extra']['laravel']['providers'] ?? []);

            foreach ($declared as $provider) {
                $providers[] = $provider;
            }
        }

        return array_values(array_filter($providers, class_exists(...)));
    }
}

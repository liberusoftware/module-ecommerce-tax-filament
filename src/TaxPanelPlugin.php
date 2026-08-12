<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Liberu\Ecommerce\Tax\Filament\Pages\PeriodTaxReport;
use Liberu\Ecommerce\Tax\Filament\Resources\JurisdictionResource;
use Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource;
use Liberu\Ecommerce\Tax\Filament\Resources\RateVersionResource;
use Liberu\Ecommerce\Tax\Filament\Resources\RegistrationResource;

/**
 * The whole surface, attached by the application to whichever panel it wants:
 *
 * ```php
 * $panel->plugin(TaxPanelPlugin::make());
 * ```
 *
 * Nothing here registers globally and nothing is discovered. A panel that does
 * not attach this plugin has no tax surface, which is the point: an operator
 * surface for an audit ledger belongs on the panel the operators use and nowhere
 * else.
 */
final class TaxPanelPlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'ecommerce-tax';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                JurisdictionResource::class,
                RegistrationResource::class,
                RateVersionResource::class,
                QuoteResource::class,
            ])
            ->pages([
                PeriodTaxReport::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}

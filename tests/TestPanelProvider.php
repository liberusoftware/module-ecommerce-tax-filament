<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Tests;

use Filament\Panel;
use Filament\PanelProvider;
use Liberu\Ecommerce\Tax\Filament\TaxPanelPlugin;

/**
 * A host panel, standing in for the application's.
 *
 * This is the whole integration: the application attaches the plugin, and
 * nothing about the package registers itself. A panel that does not attach it
 * has no tax surface.
 */
class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(TaxPanelPlugin::make());
    }
}

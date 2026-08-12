<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Resources\RateVersionResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Liberu\Ecommerce\Tax\Filament\Resources\RateVersionResource;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;

/**
 * Create opens a *first* version for a jurisdiction and class. Every subsequent
 * change goes through Revise, which closes the current version and inserts a
 * successor — there is no edit page here to route to, and no edit ability to
 * grant.
 */
class ManageRateVersions extends ManageRecords
{
    protected static string $resource = RateVersionResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New rate version')
                ->mutateDataUsing(static fn (array $data): array => [...$data, 'tenant_id' => Tenant::id()]),
        ];
    }
}

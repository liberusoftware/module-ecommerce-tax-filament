<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Resources\JurisdictionResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Liberu\Ecommerce\Tax\Filament\Resources\JurisdictionResource;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;

/**
 * The tenant is stamped on the server, from the panel's current tenant, and is
 * never a form field. A tenant id the client can send is how one storefront's
 * credential files rows into another business.
 */
class ManageJurisdictions extends ManageRecords
{
    protected static string $resource = JurisdictionResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(
                static fn (array $data): array => [...$data, 'tenant_id' => Tenant::id()],
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Resources\RegistrationResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Liberu\Ecommerce\Tax\Filament\Resources\RegistrationResource;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;

class ManageRegistrations extends ManageRecords
{
    protected static string $resource = RegistrationResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Open a registration')
                ->mutateDataUsing(static fn (array $data): array => [...$data, 'tenant_id' => Tenant::id()]),
        ];
    }
}

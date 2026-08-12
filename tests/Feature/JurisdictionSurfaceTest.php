<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Liberu\Ecommerce\Tax\Enums\Sourcing;
use Liberu\Ecommerce\Tax\Filament\Resources\JurisdictionResource\Pages\ManageJurisdictions;
use Liberu\Ecommerce\Tax\Models\Jurisdiction;
use Livewire\Livewire;

/*
 * The module ships no jurisdictions and no rates. `EuVat::STANDARD_RATES` is a
 * PHP `const` array of twenty-seven real-world VAT rates docblocked "as of
 * 2025", where a rate change in any member state is a code deploy. Rates and
 * jurisdictions are operator data, and this is where the operator enters them.
 */

beforeEach(function (): void {
    operator();
});

it('creates a jurisdiction with the tenant stamped on the server', function () {
    Livewire::test(ManageJurisdictions::class)
        ->callAction(TestAction::make('create'), [
            'code' => 'GB',
            'name' => 'United Kingdom',
            'sourcing' => Sourcing::Destination->value,
        ])
        ->assertHasNoActionErrors();

    $jurisdiction = Jurisdiction::query()->firstOrFail();

    expect($jurisdiction->tenant_id)->toBe(tenantId())
        ->and($jurisdiction->sourcing)->toBe(Sourcing::Destination);
});

it('supports a destination-sourced and an origin-sourced jurisdiction at once', function () {
    jurisdiction('GB', Sourcing::Destination);
    jurisdiction('IE', Sourcing::Origin);

    Livewire::test(ManageJurisdictions::class)
        ->assertCanSeeTableRecords(Jurisdiction::query()->get())
        ->assertSee('destination')
        ->assertSee('origin');
});

it('offers no delete action, because the evidence below a jurisdiction cascades', function () {
    $jurisdiction = registeredJurisdiction();
    $table = Livewire::test(ManageJurisdictions::class)->instance()->getTable();

    expect(array_keys($table->getFlatRecordActions()))->toBe(['edit']);

    Livewire::test(ManageJurisdictions::class)
        ->assertActionVisible(TestAction::make('edit')->table($jurisdiction));
});

it('counts the registrations and rate versions behind each jurisdiction', function () {
    registeredJurisdiction('GB');

    Livewire::test(ManageJurisdictions::class)->assertSuccessful();

    expect(Jurisdiction::query()->withCount(['registrations', 'rateVersions'])->firstOrFail())
        ->registrations_count->toBe(1)
        ->rate_versions_count->toBe(1);
});

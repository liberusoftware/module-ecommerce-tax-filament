<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Liberu\Ecommerce\Tax\Enums\Treatment;
use Liberu\Ecommerce\Tax\Filament\Resources\RateVersionResource;
use Liberu\Ecommerce\Tax\Filament\Resources\RateVersionResource\Pages\ManageRateVersions;
use Liberu\Ecommerce\Tax\Models\RateVersion;
use Livewire\Livewire;

/*
 * Fault two: the host's `tax_rates` row is mutable and undated, so editing it
 * retroactively changed what every historical order would recompute to. The
 * surface has to make versioning the only way a rate changes, and it does that
 * by having no edit route at all rather than by hiding a button.
 */

beforeEach(function (): void {
    operator();
});

it('offers no edit page, because there is nothing an edit page could do', function () {
    expect(array_keys(RateVersionResource::getPages()))->toBe(['index']);
});

it('revises a rate by closing the current version and inserting a successor', function () {
    $jurisdiction = registeredJurisdiction('GB', 2000);
    $current = $jurisdiction->rateVersions()->firstOrFail();

    Livewire::test(ManageRateVersions::class)
        ->callAction(TestAction::make('revise')->table($current), [
            'label' => 'Standard rate',
            'treatment' => Treatment::Taxable->value,
            'basis_points' => 2500,
            'sequence' => 1,
            'compound' => false,
            'effective_from' => '2026-04-01 00:00',
        ])
        ->assertHasNoActionErrors();

    $current->refresh();
    $successor = RateVersion::query()->where('supersedes_id', $current->id)->firstOrFail();

    expect($current->basis_points)->toBe(2000)
        ->and($current->effective_to?->toDateString())->toBe('2026-04-01')
        ->and($successor->basis_points)->toBe(2500)
        ->and($successor->effective_to)->toBeNull()
        ->and($successor->effective_from->toDateString())->toBe('2026-04-01')
        ->and(RateVersion::query()->count())->toBe(2);
});

it('does not offer to revise a version that is already closed', function () {
    $jurisdiction = registeredJurisdiction();
    $closed = rateVersion($jurisdiction, [
        'basis_points' => 1500,
        'effective_from' => march('2019-01-01 00:00:00'),
        'effective_to' => march('2020-01-01 00:00:00'),
    ]);

    Livewire::test(ManageRateVersions::class)
        ->assertActionHidden(TestAction::make('revise')->table($closed));
});

it('creates a first version with the tenant stamped on the server', function () {
    $jurisdiction = jurisdiction('GB');

    Livewire::test(ManageRateVersions::class)
        ->callAction(TestAction::make('create'), [
            'jurisdiction_id' => $jurisdiction->id,
            'tax_class' => 'reduced',
            'label' => 'Reduced rate',
            'treatment' => Treatment::Taxable->value,
            'basis_points' => 500,
            'sequence' => 1,
            'compound' => false,
            'effective_from' => '2026-01-01 00:00',
        ])
        ->assertHasNoActionErrors();

    $created = RateVersion::query()->where('tax_class', 'reduced')->firstOrFail();

    expect($created->tenant_id)->toBe(tenantId())
        ->and($created->basis_points)->toBe(500);
});

it('lists the rate versions of this tenant', function () {
    registeredJurisdiction('GB', 850);

    Livewire::test(ManageRateVersions::class)
        ->assertCanSeeTableRecords(RateVersion::query()->get())
        ->assertSee('850 bp (8.5%)');
});

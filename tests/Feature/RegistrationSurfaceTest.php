<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Liberu\Ecommerce\Tax\Filament\Resources\RegistrationResource;
use Liberu\Ecommerce\Tax\Filament\Resources\RegistrationResource\Pages\ManageRegistrations;
use Liberu\Ecommerce\Tax\Models\Registration;
use Livewire\Livewire;

/*
 * Fault three: the host has no nexus concept at all, so a store with no
 * registration in a jurisdiction charges tax there anyway. Here a registration
 * is a period that is opened and later closed, and neither end of it can be
 * rewritten.
 */

beforeEach(function (): void {
    operator();
});

it('offers no edit page for a registration', function () {
    expect(array_keys(RegistrationResource::getPages()))->toBe(['index']);
});

it('opens a registration with the tenant stamped on the server', function () {
    $jurisdiction = jurisdiction('GB');

    Livewire::test(ManageRegistrations::class)
        ->callAction(TestAction::make('create'), [
            'jurisdiction_id' => $jurisdiction->id,
            'registration_number' => 'GB123456789',
            'effective_from' => '2026-01-01 00:00',
        ])
        ->assertHasNoActionErrors();

    $registration = Registration::query()->firstOrFail();

    expect($registration->tenant_id)->toBe(tenantId())
        ->and($registration->effective_to)->toBeNull();
});

it('closes a period rather than editing one', function () {
    $registration = registration(jurisdiction('GB'));

    Livewire::test(ManageRegistrations::class)
        ->callAction(TestAction::make('close')->table($registration), [
            'effective_to' => '2026-06-30 00:00',
        ])
        ->assertHasNoActionErrors();

    expect($registration->refresh()->effective_to?->toDateString())->toBe('2026-06-30');
});

it('does not offer to close a period that is already closed', function () {
    $closed = registration(jurisdiction('GB'), to: march('2025-01-01 00:00:00'));

    Livewire::test(ManageRegistrations::class)
        ->assertActionHidden(TestAction::make('close')->table($closed));
});

it('does not put the registration number in the query string', function () {
    // Search terms and filter state both persist there, and a tax registration
    // number is not something to leave in a browser history or a proxy log.
    $table = Livewire::test(ManageRegistrations::class)->instance()->getTable();

    expect(array_keys($table->getColumns()))->not->toContain('registration_number')
        ->and(array_keys($table->getFilters()))->toBe(['jurisdiction_id']);
});

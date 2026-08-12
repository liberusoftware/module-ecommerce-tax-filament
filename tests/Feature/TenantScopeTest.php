<?php

declare(strict_types=1);

use Liberu\Ecommerce\Tax\Filament\Resources\JurisdictionResource;
use Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource;
use Liberu\Ecommerce\Tax\Filament\Resources\RateVersionResource;
use Liberu\Ecommerce\Tax\Filament\Resources\RegistrationResource;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;
use Liberu\PackageTestbench\TestUser;

/*
 * Fault ten: `tax_classes` and `tax_rates` carry no tenant column at all, so
 * every store on the installation shares one rate table. Every table this module
 * reads carries one from its first migration, and the surface scopes to it in
 * the query rather than in a policy — a policy answers about a row it has
 * already been handed, and a list that hands over the wrong rows has leaked them
 * before anyone is asked.
 */

it('lists only the acting tenant’s rows', function () {
    $mine = operator();
    $jurisdiction = registeredJurisdiction('GB');
    quoteFor('GB');

    $theirs = TestUser::factory()->create();
    actForTenant($theirs);
    $other = jurisdiction('FR');
    registration($other);
    rateVersion($other);
    quoteFor('FR');

    actForTenant($mine);

    expect(JurisdictionResource::getEloquentQuery()->pluck('code')->all())->toBe(['GB'])
        ->and(RegistrationResource::getEloquentQuery()->count())->toBe(1)
        ->and(RateVersionResource::getEloquentQuery()->count())->toBe(1)
        ->and(QuoteResource::getEloquentQuery()->count())->toBe(1)
        ->and(QuoteResource::getEloquentQuery()->first()?->jurisdiction_code)->toBe('GB')
        ->and($jurisdiction->tenant_id)->not->toBe($other->tenant_id);
});

it('lists nothing at all when there is no tenant, rather than every orphan row', function () {
    operator();
    registeredJurisdiction();
    quoteFor();

    actForTenant(null);

    // `where('tenant_id', null)` compiles to `tenant_id is null`, which lists
    // exactly the rows an unscoped panel has no business seeing. This does not.
    expect(Tenant::id())->toBeNull()
        ->and(JurisdictionResource::getEloquentQuery()->count())->toBe(0)
        ->and(RegistrationResource::getEloquentQuery()->count())->toBe(0)
        ->and(RateVersionResource::getEloquentQuery()->count())->toBe(0)
        ->and(QuoteResource::getEloquentQuery()->count())->toBe(0);
});

it('refuses to create anything without a tenant', function () {
    operator();
    actForTenant(null);

    expect(JurisdictionResource::canCreate())->toBeFalse()
        ->and(RegistrationResource::canCreate())->toBeFalse()
        ->and(RateVersionResource::canCreate())->toBeFalse();
});

it('refuses to view another tenant’s record', function () {
    operator();
    $mine = registeredJurisdiction();

    $theirs = TestUser::factory()->create();
    actForTenant($theirs);

    expect(JurisdictionResource::canView($mine))->toBeFalse()
        ->and(Tenant::owns($mine))->toBeFalse();
});

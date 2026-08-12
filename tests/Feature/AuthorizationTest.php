<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\Tax\Filament\Policies\JurisdictionPolicy;
use Liberu\Ecommerce\Tax\Filament\Policies\QuotePolicy;
use Liberu\Ecommerce\Tax\Filament\Policies\RateVersionPolicy;
use Liberu\Ecommerce\Tax\Filament\Policies\RegistrationPolicy;
use Liberu\Ecommerce\Tax\Filament\Resources\JurisdictionResource;
use Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource;
use Liberu\Ecommerce\Tax\Filament\Resources\RateVersionResource;
use Liberu\Ecommerce\Tax\Filament\Resources\RegistrationResource;
use Liberu\Ecommerce\Tax\Filament\Resources\TaxResource;
use Liberu\Ecommerce\Tax\Models\Jurisdiction;
use Liberu\Ecommerce\Tax\Models\Quote;
use Liberu\Ecommerce\Tax\Models\RateVersion;
use Liberu\Ecommerce\Tax\Models\Registration;

/*
 * The guarantee, and the reason it is not a policy.
 *
 * An application is free to install `Gate::before(fn () => true)` — a superuser
 * role does exactly that — and every one of these tests installs one. A quote
 * and a rate version stay closed anyway, because the refusal happens above the
 * gate rather than inside it. Wave 8 learned this the expensive way and this is
 * an audit ledger.
 */

beforeEach(function (): void {
    operator();
});

/** @return array{0: class-string<TaxResource>, 1: Model} */
function subject(string $resource): array
{
    $jurisdiction = registeredJurisdiction();

    $record = match ($resource) {
        JurisdictionResource::class => $jurisdiction,
        RegistrationResource::class => $jurisdiction->registrations()->firstOrFail(),
        RateVersionResource::class => $jurisdiction->rateVersions()->firstOrFail(),
        QuoteResource::class => quoteFor(),
        default => throw new InvalidArgumentException($resource),
    };

    return [$resource, $record];
}

const RESOURCES = [
    JurisdictionResource::class,
    RegistrationResource::class,
    RateVersionResource::class,
    QuoteResource::class,
];

it('refuses every closed ability with a gate that answers yes to everything', function (string $resource) {
    [$resource, $record] = subject($resource);

    Gate::before(static fn (): bool => true);

    foreach ($resource::closedAbilities() as $ability) {
        expect($resource::can($ability, $record))
            ->toBeFalse("[{$ability}] on [{$resource}] was answered by the gate rather than refused above it.");
    }
})->with(RESOURCES);

it('closes exactly the abilities it means to, so dropping one is a failing test', function (string $resource, array $expected) {
    expect($resource::closedAbilities())->toBe($expected);
})->with([
    [JurisdictionResource::class, [...TaxResource::DESTRUCTIVE, ...TaxResource::RELATIONAL]],
    [RegistrationResource::class, [...TaxResource::DESTRUCTIVE, ...TaxResource::RELATIONAL, 'update']],
    [RateVersionResource::class, [...TaxResource::DESTRUCTIVE, ...TaxResource::RELATIONAL, 'update']],
    [QuoteResource::class, [...TaxResource::DESTRUCTIVE, ...TaxResource::RELATIONAL, 'update', 'create']],
]);

it('names the relation abilities even though it ships no relation manager', function () {
    // Live on a `hasMany` and open by default. A relation manager added later
    // cannot quietly reopen them.
    expect(TaxResource::RELATIONAL)
        ->toContain('associate')
        ->and(TaxResource::RELATIONAL)->toContain('dissociate')
        ->and(TaxResource::RELATIONAL)->toContain('attach')
        ->and(TaxResource::RELATIONAL)->toContain('detach');
});

it('leaves the gate answering yes, which is what makes the refusal meaningful', function () {
    $quote = quoteFor();

    Gate::before(static fn (): bool => true);

    // The gate really is permissive: a policy-only guarantee would be gone here.
    expect(Gate::check('update', $quote))->toBeTrue()
        ->and(Gate::check('delete', $quote))->toBeTrue()
        // And the resource refuses regardless.
        ->and(QuoteResource::canEdit($quote))->toBeFalse()
        ->and(QuoteResource::canDelete($quote))->toBeFalse()
        ->and(QuoteResource::canCreate())->toBeFalse()
        ->and(QuoteResource::canDeleteAny())->toBeFalse()
        ->and(QuoteResource::canReplicate($quote))->toBeFalse()
        ->and(QuoteResource::canRestore($quote))->toBeFalse();
});

it('keeps a rate version closed to editing however the application answers', function () {
    $rate = registeredJurisdiction()->rateVersions()->firstOrFail();

    Gate::before(static fn (): bool => true);

    expect(RateVersionResource::canEdit($rate))->toBeFalse()
        ->and(RateVersionResource::canDelete($rate))->toBeFalse()
        ->and(RateVersionResource::isReadOnly())->toBeTrue()
        // Creating a *version* is how a rate is added, and stays open.
        ->and(RateVersionResource::canCreate())->toBeTrue();
});

it('refuses a closed ability without any permissive gate too', function () {
    $quote = quoteFor();

    expect(QuoteResource::canEdit($quote))->toBeFalse()
        ->and(QuoteResource::canDelete($quote))->toBeFalse();
});

it('still permits the reads, so the refusal is targeted rather than a blanket', function (string $resource) {
    [$resource, $record] = subject($resource);

    expect($resource::canViewAny())->toBeTrue()
        ->and($resource::canView($record))->toBeTrue();
})->with(RESOURCES);

it('treats a jurisdiction as editable and everything else as evidence', function () {
    expect(JurisdictionResource::isReadOnly())->toBeFalse()
        ->and(RegistrationResource::isReadOnly())->toBeTrue()
        ->and(RateVersionResource::isReadOnly())->toBeTrue()
        ->and(QuoteResource::isReadOnly())->toBeTrue();
});

it('refuses to delete a jurisdiction, because the evidence below it cascades', function () {
    $jurisdiction = registeredJurisdiction();

    Gate::before(static fn (): bool => true);

    expect(JurisdictionResource::canDelete($jurisdiction))->toBeFalse()
        ->and(JurisdictionResource::canEdit($jurisdiction))->toBeTrue();
});

it('answers a policy method for every ability rather than letting one fall through', function (string $policy, array $abilities) {
    // A present policy missing the method it is asked about is *permissive*:
    // Filament's get_authorization_response() returns allow.
    foreach ($abilities as $ability) {
        expect(method_exists($policy, $ability))
            ->toBeTrue("[{$policy}] does not answer [{$ability}], which means it allows it.");
    }
})->with([
    [QuotePolicy::class, ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate', 'reorder']],
    [RateVersionPolicy::class, ['viewAny', 'view', 'create', 'revise', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate', 'reorder']],
    [RegistrationPolicy::class, ['viewAny', 'view', 'create', 'close', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate', 'reorder']],
    [JurisdictionPolicy::class, ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate', 'reorder']],
]);

it('registers a policy for every model it exposes', function (string $model) {
    expect(Gate::getPolicyFor($model))->not->toBeNull();
})->with([
    Jurisdiction::class,
    Registration::class,
    RateVersion::class,
    Quote::class,
]);

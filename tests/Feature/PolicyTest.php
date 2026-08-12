<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\Tax\Filament\Policies\JurisdictionPolicy;
use Liberu\Ecommerce\Tax\Filament\Policies\QuotePolicy;
use Liberu\Ecommerce\Tax\Filament\Policies\RateVersionPolicy;
use Liberu\Ecommerce\Tax\Filament\Policies\RegistrationPolicy;
use Liberu\PackageTestbench\TestUser;

/*
 * The ordinary half of the story.
 *
 * The resource refuses these abilities above the gate, so nothing routed through
 * Filament ever reaches a policy to ask. Anything *else* in the application does
 * — `Gate::allows('delete', $quote)` from a job, a console command, an API
 * controller — and a policy that is present but missing a method is permissive.
 * So every one of them is written out, and every one of them is asserted.
 */

const REFUSED = [
    'update',
    'delete',
    'deleteAny',
    'forceDelete',
    'forceDeleteAny',
    'restore',
    'restoreAny',
    'replicate',
    'reorder',
];

/** @return array{0: object, 1: Model} */
function policyFor(string $policy): array
{
    $jurisdiction = registeredJurisdiction();

    $record = match ($policy) {
        JurisdictionPolicy::class => $jurisdiction,
        RegistrationPolicy::class => $jurisdiction->registrations()->firstOrFail(),
        RateVersionPolicy::class => $jurisdiction->rateVersions()->firstOrFail(),
        QuotePolicy::class => quoteFor(),
        default => throw new InvalidArgumentException($policy),
    };

    return [new $policy(), $record];
}

it('answers false, by name, for every ability it does not publish', function (string $policy) {
    $user = operator();
    [$instance, $record] = policyFor($policy);

    foreach (REFUSED as $ability) {
        // A jurisdiction is operator data rather than evidence, so it alone is
        // editable. Everything else on this list is refused everywhere.
        if ($ability === 'update' && $policy === JurisdictionPolicy::class) {
            continue;
        }

        $arguments = (new ReflectionMethod($instance, $ability))->getNumberOfParameters() === 2
            ? [$user, $record]
            : [$user];

        expect($instance->{$ability}(...$arguments))
            ->toBeFalse("[{$policy}] does not refuse [{$ability}].");
    }
})->with([
    JurisdictionPolicy::class,
    RegistrationPolicy::class,
    RateVersionPolicy::class,
    QuotePolicy::class,
]);

it('refuses to create a quote, because a quote is evidence of something that already happened', function () {
    expect((new QuotePolicy())->create(operator()))->toBeFalse();
});

it('permits a jurisdiction to be corrected, and only by its own tenant', function () {
    $user = operator();
    $jurisdiction = jurisdiction('GB');

    expect((new JurisdictionPolicy())->update($user, $jurisdiction))->toBeTrue();

    actForTenant(TestUser::factory()->create());

    expect((new JurisdictionPolicy())->update($user, $jurisdiction))->toBeFalse();
});

it('offers revise only while a version is still open', function () {
    $user = operator();
    $jurisdiction = registeredJurisdiction();
    $open = $jurisdiction->rateVersions()->firstOrFail();
    $closed = rateVersion($jurisdiction, ['effective_to' => march('2026-06-01 00:00:00')]);

    expect((new RateVersionPolicy())->revise($user, $open))->toBeTrue()
        ->and((new RateVersionPolicy())->revise($user, $closed))->toBeFalse();
});

it('offers close only while a registration is still open', function () {
    $user = operator();
    $jurisdiction = jurisdiction('GB');
    $open = registration($jurisdiction);
    $closed = registration($jurisdiction, to: march('2026-06-01 00:00:00'));

    expect((new RegistrationPolicy())->close($user, $open))->toBeTrue()
        ->and((new RegistrationPolicy())->close($user, $closed))->toBeFalse();
});

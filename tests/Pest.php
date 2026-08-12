<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Tax\Actions\QuoteTax;
use Liberu\Ecommerce\Tax\Data\QuoteLineRequest;
use Liberu\Ecommerce\Tax\Data\QuoteRequest;
use Liberu\Ecommerce\Tax\Enums\RoundingStrategy;
use Liberu\Ecommerce\Tax\Enums\Sourcing;
use Liberu\Ecommerce\Tax\Enums\Treatment;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;
use Liberu\Ecommerce\Tax\Filament\Tests\TestCase;
use Liberu\Ecommerce\Tax\Models\Jurisdiction;
use Liberu\Ecommerce\Tax\Models\Quote;
use Liberu\Ecommerce\Tax\Models\RateVersion;
use Liberu\Ecommerce\Tax\Models\Registration;
use Liberu\PackageTestbench\TestUser;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * Sign in, choose a panel, and become a tenant.
 *
 * The tenant is a model whose key this module reads and nothing else, so a test
 * user stands in for whatever the host's stores are. Everything below is written
 * under `Tenant::id()`, which is what the resources scope to.
 */
function operator(): TestUser
{
    $user = TestUser::factory()->create();

    test()->actingAs($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($user, isQuiet: true);

    return $user;
}

/** Act as a different store, without changing who is signed in. */
function actForTenant(?TestUser $tenant): void
{
    Filament::setTenant($tenant, isQuiet: true);
}

function tenantId(): int
{
    $id = Tenant::id();

    expect($id)->not->toBeNull();

    return (int) $id;
}

function march(string $time = '2026-03-01 00:00:00'): Carbon
{
    return Carbon::parse($time);
}

function jurisdiction(string $code = 'GB', Sourcing $sourcing = Sourcing::Destination, ?int $tenantId = null): Jurisdiction
{
    return Jurisdiction::query()->create([
        'tenant_id' => $tenantId ?? tenantId(),
        'code' => $code,
        'name' => $code.' jurisdiction',
        'sourcing' => $sourcing,
    ]);
}

function registration(Jurisdiction $jurisdiction, ?Carbon $from = null, ?Carbon $to = null): Registration
{
    return Registration::query()->create([
        'tenant_id' => $jurisdiction->tenant_id,
        'jurisdiction_id' => $jurisdiction->id,
        'registration_number' => 'REG-'.$jurisdiction->code,
        'effective_from' => $from ?? march('2020-01-01 00:00:00'),
        'effective_to' => $to,
    ]);
}

/** @param  array<string, mixed>  $attributes */
function rateVersion(Jurisdiction $jurisdiction, array $attributes = []): RateVersion
{
    return RateVersion::query()->create([
        'tenant_id' => $jurisdiction->tenant_id,
        'jurisdiction_id' => $jurisdiction->id,
        'tax_class' => 'standard',
        'label' => 'Standard rate',
        'treatment' => Treatment::Taxable,
        'basis_points' => 2000,
        'sequence' => 1,
        'compound' => false,
        'effective_from' => march('2020-01-01 00:00:00'),
        ...$attributes,
    ]);
}

/** A registered, destination-sourced jurisdiction with one standard rate. */
function registeredJurisdiction(string $code = 'GB', int $basisPoints = 2000): Jurisdiction
{
    $jurisdiction = jurisdiction($code);
    registration($jurisdiction);
    rateVersion($jurisdiction, ['basis_points' => $basisPoints]);

    return $jurisdiction;
}

/**
 * A real quote, produced by the domain package.
 *
 * Never a fabricated row: the whole subject of this surface is evidence, and
 * evidence assembled by a test fixture would prove nothing about what the
 * operator actually sees.
 *
 * @param  array<int, array{0: string, 1: int}>  $lines
 */
function quoteFor(string $jurisdictionCode = 'GB', array $lines = [['line-1', 10_000]], ?int $tenantId = null, ?Carbon $at = null): Quote
{
    $at ??= march();

    return app(QuoteTax::class)(new QuoteRequest(
        tenantId: $tenantId ?? tenantId(),
        currency: 'GBP',
        originCode: $jurisdictionCode,
        destinationCode: $jurisdictionCode,
        lines: array_map(
            static fn (array $line): QuoteLineRequest => new QuoteLineRequest($line[0], $line[1], false, 'standard'),
            $lines,
        ),
        quotedAt: $at,
        expiresAt: $at->copy()->addHour(),
        rounding: RoundingStrategy::HalfUp,
    ));
}

/**
 * Every PHP file this package ships under src/, with its comments stripped.
 *
 * Two rules in this suite are properties of the source rather than of a run:
 * there is no float arithmetic here, and no sibling module is reached for. The
 * comments quote the host's defects verbatim so a reader can see what was
 * replaced, and a naive grep would find the quotation and call it the defect.
 */
function sourceCode(): string
{
    $code = '';

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src')) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }
    }

    return $code;
}

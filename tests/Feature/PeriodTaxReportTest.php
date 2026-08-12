<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\Tax\Actions\SupersedeQuote;
use Liberu\Ecommerce\Tax\Filament\Pages\PeriodTaxReport;
use Livewire\Livewire;

/*
 * Fault eleven: `OssReportService` folds `orders.tax_amount` in raw SQL and
 * prorates refunds by float division. This page folds the quote ledger three
 * independent ways and shows all three, because a single confident number is
 * exactly what the host produced.
 */

beforeEach(function (): void {
    operator();
});

it('reports the same period figure three ways over a mixed ledger', function () {
    registeredJurisdiction('GB', 2000);
    $france = jurisdiction('FR');
    registration($france);
    rateVersion($france, ['basis_points' => 550, 'label' => 'Reduced rate']);

    quoteFor('GB', [['a', 10_000], ['b', 70]]);
    quoteFor('FR', [['c', 4_999]]);

    $superseded = quoteFor('GB', [['d', 1_234]]);
    $replacement = quoteFor('GB', [['d', 4_321]]);
    app(SupersedeQuote::class)($superseded, $replacement, 'the base was wrong');

    $page = Livewire::test(PeriodTaxReport::class)
        ->set('data.from', '2026-03-01')
        ->set('data.until', '2026-03-31');

    $figures = $page->instance()->figures();

    expect($figures)->not->toBeNull()
        ->and($figures['fold'])->toBe($figures['lines'])
        ->and($figures['lines'])->toBe($figures['reproduced'])
        ->and($figures['fold'])->toBeGreaterThan(0);
});

it('reproduces the period from the quotes alone, with the rate tables emptied', function () {
    registeredJurisdiction('GB', 2000);
    quoteFor('GB', [['a', 10_000]]);

    $page = Livewire::test(PeriodTaxReport::class)
        ->set('data.from', '2026-03-01')
        ->set('data.until', '2026-03-31');

    $before = $page->instance()->figures();

    // The Eloquent boundary refuses a delete on purpose, so this goes round it
    // — which is the point of the check: the evidence has to survive the rate
    // tables being archived.
    DB::table('tax_rate_versions')->delete();
    DB::table('tax_registrations')->delete();
    DB::table('tax_jurisdictions')->delete();

    $after = Livewire::test(PeriodTaxReport::class)
        ->set('data.from', '2026-03-01')
        ->set('data.until', '2026-03-31')
        ->instance()
        ->figures();

    expect($before['reproduced'])->toBe(2000)
        ->and($after['reproduced'])->toBe($before['reproduced'])
        ->and($after['fold'])->toBe($before['fold']);
});

it('shows the figures and says whether they agree', function () {
    registeredJurisdiction('GB', 2000);
    quoteFor('GB', [['a', 10_000]]);

    Livewire::test(PeriodTaxReport::class)
        ->set('data.from', '2026-03-01')
        ->set('data.until', '2026-03-31')
        ->assertSee('2000 minor units')
        ->assertSee('All three agree.');
});

it('asks for a period rather than guessing one', function () {
    Livewire::test(PeriodTaxReport::class)
        ->set('data.from', '')
        ->set('data.until', '')
        ->assertSee('Choose a period.');

    expect(Livewire::test(PeriodTaxReport::class)->set('data.from', '')->instance()->figures())->toBeNull();
});

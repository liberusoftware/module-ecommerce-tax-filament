<?php

declare(strict_types=1);

use Filament\Tables\Columns\Column;
use Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource;
use Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource\Pages\ListQuotes;
use Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource\Pages\ViewQuote;
use Liberu\Ecommerce\Tax\Models\Quote;
use Livewire\Livewire;

/*
 * Fault six: `orders.tax_lines` is a JSON blob of labels and amounts — a
 * rendering of the calculation rather than a record of it, carrying no rate
 * identity, no jurisdiction, no sequence, no rounding rule and no effective
 * date. This page shows every one of them, because that is what makes the figure
 * reproducible without the rate tables.
 */

beforeEach(function (): void {
    operator();
});

it('lists this tenant’s quotes with money as a decimal string', function () {
    registeredJurisdiction('GB', 2000);
    $quote = quoteFor('GB', [['line-1', 10_000]]);

    Livewire::test(ListQuotes::class)
        ->assertCanSeeTableRecords(Quote::query()->get())
        // 10000 net at 20% is 2000 minor units of tax, rendered "20.00 GBP".
        ->assertSee('20.00 GBP');

    expect($quote->tax_total_minor)->toBe(2000);
});

it('binds a quote by its reference, which is the route key it publishes', function () {
    registeredJurisdiction('GB');
    $quote = quoteFor('GB');

    Livewire::test(ViewQuote::class, ['record' => $quote->reference])
        ->assertSuccessful()
        ->assertSee($quote->reference);
});

it('shows the evidence behind every figure, not a rendering of it', function () {
    registeredJurisdiction('GB', 850);
    $quote = quoteFor('GB', [['line-1', 10_000]]);

    Livewire::test(ViewQuote::class, ['record' => $quote->reference])
        ->assertSee('Standard rate')
        ->assertSee('850 bp (8.5%)')
        ->assertSee('GB')
        ->assertSee('half_up');
});

it('says why no tax was charged rather than leaving a line silently absent', function () {
    // A jurisdiction with rates but no registration. Charging tax you are not
    // registered to collect is a worse failure than not charging it.
    $jurisdiction = jurisdiction('FR');
    rateVersion($jurisdiction, ['basis_points' => 2000]);
    $quote = quoteFor('FR');

    expect($quote->no_tax_reason)->toBe('no_registration');

    Livewire::test(ViewQuote::class, ['record' => $quote->reference])
        ->assertSee('no_registration');
});

it('neither searches nor filters on the reference', function () {
    $table = Livewire::test(ListQuotes::class)->instance()->getTable();

    $reference = collect($table->getColumns())->firstOrFail(
        static fn (Column $column): bool => $column->getName() === 'reference',
    );

    expect($reference->isSearchable())->toBeFalse()
        ->and(array_keys($table->getFilters()))->not->toContain('reference');
});

it('offers no create, edit or delete action anywhere on the quote surface', function () {
    expect(array_keys(QuoteResource::getPages()))->toBe(['index', 'view'])
        ->and(Livewire::test(ListQuotes::class)->instance()->getTable()->getHeaderActions())->toBe([]);
});

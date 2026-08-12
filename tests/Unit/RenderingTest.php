<?php

declare(strict_types=1);

use Liberu\Ecommerce\Tax\Filament\Resources\RateVersionResource;
use Liberu\Ecommerce\Tax\Filament\TaxPanelPlugin;

/*
 * Fault one: `tax_rates.rate` is `decimal(8,4)` holding a percent, and
 * `TaxRate::calculateTax()` is `round($price * ($this->rate / 100), 2)` — three
 * float operations in the one calculation the module exists to perform. Nothing
 * here turns a rate or an amount into a float, not even to print it.
 */

it('renders a rate as basis points and an exact decimal string', function (int $basisPoints, string $rendered) {
    expect(RateVersionResource::renderRate($basisPoints))->toBe($rendered);
})->with([
    [850, '850 bp (8.5%)'],
    [2000, '2000 bp (20%)'],
    [2550, '2550 bp (25.5%)'],
    [2700, '2700 bp (27%)'],
    [0, '0 bp (0%)'],
    [1, '1 bp (0.01%)'],
]);

it('constructs no float anywhere in its source', function (string $needle) {
    expect(sourceCode())->not->toContain($needle);
})->with([
    'round(',
    '(float)',
    'floatval',
    'number_format',
    '/ 100',
]);

it('publishes a stable plugin id, which is how a panel addresses it', function () {
    expect(TaxPanelPlugin::make()->getId())->toBe('ecommerce-tax');
});

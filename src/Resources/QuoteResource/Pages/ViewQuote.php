<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource;
use Liberu\Ecommerce\Tax\Models\Quote;

/**
 * The evidence, whole.
 *
 * The record binds on `reference`, because {@see Quote}
 * overrides `getRouteKeyName()` — the sequential id is not what anything outside
 * this module holds.
 *
 * There is no edit action in the header and no delete action, and their absence
 * is not what stops them: {@see QuoteResource} refuses both abilities below the
 * policy layer.
 */
class ViewQuote extends ViewRecord
{
    protected static string $resource = QuoteResource::class;
}

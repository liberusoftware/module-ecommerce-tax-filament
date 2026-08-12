<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource;

/**
 * No header actions. There is nothing to create here: a quote is evidence that
 * something already happened elsewhere.
 */
class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;
}

<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;
use Liberu\Ecommerce\Tax\Queries\PeriodTaxReport as PeriodTaxReportQuery;
use UnitEnum;

/**
 * A period figure, folded over the quote ledger — never over orders and never
 * over a cached column.
 *
 * The host's `OssReportService` folds `orders.tax_amount` in raw SQL and
 * prorates refunds with `SUM(tax_amount * (total_amount -
 * COALESCE(refund_total,0)) / NULLIF(total_amount,0))`: a statutory return
 * computed from a cached column by float division, and only reachable that way
 * because the evidence to compute it properly did not exist.
 *
 * Three figures are shown rather than one, and that is the point of the page.
 * They come from three independent routes through the same ledger, and the third
 * recomputes every quote from the evidence recorded on the quote itself — it
 * reads no rate table at all. Three figures that agree say the evidence is
 * sufficient. Three that disagree say something is wrong with the ledger, and an
 * operator should be told that rather than shown one confident number.
 *
 * This module produces figures. It does not file, does not submit, and does not
 * know what a return looks like.
 */
class PeriodTaxReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Period tax report';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->getSchema('form')?->fill([
            'from' => Carbon::now()->startOfMonth()->toDateString(),
            'until' => Carbon::now()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Period')
                    ->schema([
                        DatePicker::make('from')->label('From')->live()->required(),
                        DatePicker::make('until')->label('Until')->live()->required(),
                    ])
                    ->columns(2),
                Section::make('Tax due for the period')
                    ->description('The same period, three independent ways. They must agree.')
                    ->schema([
                        Placeholder::make('fold_ledger')
                            ->label('Folded over the quote ledger')
                            ->content(fn (): string => $this->minorUnits('fold')),
                        Placeholder::make('sum_lines')
                            ->label('Summed across quote lines')
                            ->content(fn (): string => $this->minorUnits('lines')),
                        Placeholder::make('reproduce')
                            ->label('Reproduced from each quote’s own evidence')
                            ->helperText('Recomputed from the rate applications recorded on each quote. Reads no rate table.')
                            ->content(fn (): string => $this->minorUnits('reproduced')),
                        Placeholder::make('agreement')
                            ->label('Agreement')
                            ->content(fn (): string => $this->agreement()),
                    ])
                    ->columns(2),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
        ]);
    }

    /**
     * The three figures, in minor units, or null while the period is unstated.
     *
     * @return array{fold: int, lines: int, reproduced: int}|null
     */
    public function figures(): ?array
    {
        $tenant = Tenant::id();
        $from = $this->data['from'] ?? null;
        $until = $this->data['until'] ?? null;

        if ($tenant === null || ! is_string($from) || ! is_string($until) || $from === '' || $until === '') {
            return null;
        }

        $report = app(PeriodTaxReportQuery::class);
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($until)->endOfDay();

        return [
            'fold' => $report->foldLedger($tenant, $start, $end),
            'lines' => $report->sumLines($tenant, $start, $end),
            'reproduced' => $report->reproduce($tenant, $start, $end),
        ];
    }

    /**
     * Minor units, rendered as an integer. No currency: a ledger can hold more
     * than one, and a total summed across currencies would be a worse answer
     * than an honest count of minor units.
     */
    private function minorUnits(string $key): string
    {
        $figures = $this->figures();

        return $figures === null ? 'Choose a period.' : $figures[$key].' minor units';
    }

    private function agreement(): string
    {
        $figures = $this->figures();

        if ($figures === null) {
            return 'Choose a period.';
        }

        return count(array_unique($figures)) === 1
            ? 'All three agree.'
            : 'The three routes disagree. The ledger is not reproducible for this period and the figure must not be relied on.';
    }
}

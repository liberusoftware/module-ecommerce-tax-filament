<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Resources;

use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource\Pages\ListQuotes;
use Liberu\Ecommerce\Tax\Filament\Resources\QuoteResource\Pages\ViewQuote;
use Liberu\Ecommerce\Tax\Models\Quote;
use Liberu\Ecommerce\Tax\Models\QuoteLine;
use Liberu\Ecommerce\Tax\Models\RateApplication;
use Liberu\Ecommerce\Tax\Support\Money;
use UnitEnum;

/**
 * The ledger, read.
 *
 * Nothing here writes. A quote is created by whatever asked for one, it is never
 * corrected, and a correction is a new quote plus a supersession — so there is
 * no create, no edit and no delete on this resource, and none of those refusals
 * is left to a policy. {@see TaxResource} answers them before the gate is
 * consulted, because a `Gate::before` that answers yes to everything is a
 * perfectly ordinary thing for an application to install and this is an audit
 * ledger.
 *
 * The view page shows what the host's `orders.tax_lines` JSON blob could not: a
 * rate identity, a jurisdiction, a sequence, a rounding rule and an effective
 * date behind every figure. That is what makes a quote reproducible without the
 * rate tables, and reproducibility is the whole claim.
 *
 * The reference is deliberately not searchable and not filterable. Search terms
 * and filter state persist into the query string, and the reference is an opaque
 * unguessable handle by design; a quote is reached by its own URL, from whatever
 * recorded it.
 */
class QuoteResource extends TaxResource
{
    protected static ?string $model = Quote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'reference';

    /**
     * A quote is not created here either. It is evidence of something that
     * already happened, and the operator surface is not where it happens.
     *
     * @return list<string>
     */
    public static function closedAbilities(): array
    {
        return [...parent::closedAbilities(), 'create'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->limit(12)->label('Reference'),
                TextColumn::make('quoted_at')->label('Quoted')->dateTime()->sortable(),
                TextColumn::make('jurisdiction_code')->label('Jurisdiction')->placeholder('none')->sortable(),
                TextColumn::make('sourcing_used')->label('Sourcing')->badge()->placeholder('n/a'),
                TextColumn::make('tax_total_minor')
                    ->label('Tax')
                    ->state(static fn (Quote $record): string => self::money($record, $record->tax_total_minor))
                    ->sortable(),
                TextColumn::make('gross_total_minor')
                    ->label('Gross')
                    ->state(static fn (Quote $record): string => self::money($record, $record->gross_total_minor)),
                TextColumn::make('no_tax_reason')->label('No tax because')->badge()->placeholder('—'),
                TextColumn::make('supersession.reason')->label('Superseded')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('jurisdiction_code')
                    ->label('Jurisdiction')
                    ->options(fn (): array => self::getEloquentQuery()
                        ->whereNotNull('jurisdiction_code')
                        ->distinct()
                        ->orderBy('jurisdiction_code')
                        ->pluck('jurisdiction_code', 'jurisdiction_code')
                        ->all()),
                Filter::make('operative')
                    ->label('Operative only')
                    ->query(static fn (Builder $query): Builder => $query->whereDoesntHave('supersession'))
                    ->default(),
                Filter::make('quoted_at')
                    ->schema([
                        DatePicker::make('from')->label('Quoted from'),
                        DatePicker::make('until')->label('Quoted until'),
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, static fn (Builder $q, string $from): Builder => $q->where('quoted_at', '>=', Carbon::parse($from)->startOfDay()))
                            ->when($data['until'] ?? null, static fn (Builder $q, string $until): Builder => $q->where('quoted_at', '<=', Carbon::parse($until)->endOfDay()));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('quoted_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Quote')->schema([
                TextEntry::make('reference')->copyable(),
                TextEntry::make('quoted_at')->dateTime(),
                TextEntry::make('expires_at')->dateTime(),
                TextEntry::make('calculator')
                    ->label('Calculated by')
                    ->helperText('The implementation of CalculatesTax that produced this. An adapter that returned a bare total would have been refused.'),
                TextEntry::make('rounding_strategy')->label('Rounding')->badge(),
            ])->columns(2),

            Section::make('How the jurisdiction was reached')->schema([
                TextEntry::make('origin_code')->label('Origin')->placeholder('—'),
                TextEntry::make('destination_code')->label('Destination')->placeholder('—'),
                TextEntry::make('jurisdiction_code')->label('Jurisdiction')->placeholder('none resolved'),
                TextEntry::make('sourcing_used')->label('Sourcing rule applied')->badge()->placeholder('—'),
                TextEntry::make('registration_number')
                    ->label('Registration relied on')
                    ->placeholder('none'),
                TextEntry::make('no_tax_reason')
                    ->label('Why no tax was charged')
                    ->badge()
                    ->placeholder('tax was charged'),
            ])->columns(2),

            Section::make('Exemption claim')->schema([
                TextEntry::make('claimed_registration_number')->label('Number claimed')->placeholder('—'),
                TextEntry::make('validation_authority')->label('Authority')->placeholder('—'),
                TextEntry::make('validation_outcome')->label('Outcome')->badge()->placeholder('—'),
                TextEntry::make('validated_at')->label('Validated at')->dateTime()->placeholder('—'),
                TextEntry::make('validation_response')->label('Response recorded')->placeholder('—'),
                TextEntry::make('exemption_reason')->label('Reason')->placeholder('—'),
            ])
                ->description('An exemption is a claim with evidence, or it does not exist. A refusal is recorded here too, and tax was charged.')
                ->columns(2)
                ->visible(static fn (Quote $record): bool => $record->claimed_registration_number !== null),

            Section::make('Totals')->schema([
                TextEntry::make('net_total_minor')->label('Net')->state(static fn (Quote $record): string => self::money($record, $record->net_total_minor)),
                TextEntry::make('tax_total_minor')->label('Tax')->state(static fn (Quote $record): string => self::money($record, $record->tax_total_minor)),
                TextEntry::make('gross_total_minor')->label('Gross')->state(static fn (Quote $record): string => self::money($record, $record->gross_total_minor)),
            ])
                ->description('The quote total is the sum of its lines, and a line is the sum of its rate applications. Nothing is rounded twice.')
                ->columns(3),

            Section::make('Lines')->schema([
                RepeatableEntry::make('lines')->schema([
                    TextEntry::make('line_reference')->label('Line'),
                    TextEntry::make('treatment')->badge(),
                    TextEntry::make('reason')->placeholder('—'),
                    TextEntry::make('tax_class')->label('Class'),
                    TextEntry::make('base_minor')
                        ->label('Base as supplied')
                        ->state(static fn (QuoteLine $record): string => self::lineMoney($record, $record->base_minor).($record->base_inclusive ? ' (inclusive)' : ' (exclusive)')),
                    TextEntry::make('net_minor')->label('Net')->state(static fn (QuoteLine $record): string => self::lineMoney($record, $record->net_minor)),
                    TextEntry::make('tax_minor')->label('Tax')->state(static fn (QuoteLine $record): string => self::lineMoney($record, $record->tax_minor)),
                    TextEntry::make('gross_minor')->label('Gross')->state(static fn (QuoteLine $record): string => self::lineMoney($record, $record->gross_minor)),
                    RepeatableEntry::make('applications')
                        ->label('Rate applications, in the sequence that produced the figure')
                        ->schema([
                            TextEntry::make('sequence'),
                            TextEntry::make('label'),
                            TextEntry::make('jurisdiction_code')->label('Jurisdiction'),
                            TextEntry::make('basis_points')
                                ->label('Rate')
                                ->state(static fn (RateApplication $record): string => RateVersionResource::renderRate($record->basis_points)),
                            TextEntry::make('compound')
                                ->label('Compounds on')
                                ->state(static fn (RateApplication $record): string => $record->compound ? 'base plus tax before it' : 'the line base'),
                            TextEntry::make('effective_from')->label('Rate in force from')->dateTime(),
                            TextEntry::make('rounding_strategy')->label('Rounding')->badge(),
                            TextEntry::make('taxable_base_minor')->label('Applied to')->state(static fn (RateApplication $record): string => self::minor($record->taxable_base_minor)),
                            TextEntry::make('tax_minor')->label('Produced')->state(static fn (RateApplication $record): string => self::minor($record->tax_minor)),
                        ])
                        ->columns(3),
                ])->columns(4),
            ])->description('Three treatments, never one boolean: taxable, zero-rated and exempt, each with the reason it was reached.'),
        ]);
    }

    /** Money as the fleet settled it: minor units, and a decimal string. Never a float. */
    public static function money(Quote $quote, int $minor): string
    {
        return (new Money($minor, $quote->currency, $quote->exponent))->decimal().' '.$quote->currency;
    }

    public static function lineMoney(QuoteLine $line, int $minor): string
    {
        return self::money($line->quote, $minor);
    }

    /** A bare minor-unit figure, for a row that carries no currency of its own. */
    public static function minor(int $minor): string
    {
        return $minor.' minor units';
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListQuotes::route('/'),
            'view' => ViewQuote::route('/{record}'),
        ];
    }
}

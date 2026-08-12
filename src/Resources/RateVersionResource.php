<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Tax\Actions\ReviseRate;
use Liberu\Ecommerce\Tax\Enums\Treatment;
use Liberu\Ecommerce\Tax\Filament\Resources\RateVersionResource\Pages\ManageRateVersions;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;
use Liberu\Ecommerce\Tax\Models\Jurisdiction;
use Liberu\Ecommerce\Tax\Models\RateVersion;
use Liberu\Ecommerce\Tax\Support\Rate;
use UnitEnum;

/**
 * Rates, and the only way this surface lets one change.
 *
 * There is no edit form and there is no edit route. A rate version is written
 * once; revising it closes the current version at an instant and inserts a
 * successor from that instant, which is what {@see ReviseRate} does and the only
 * thing the domain offers. The host's `tax_rates` row is mutable and undated, so
 * editing it retroactively changed what every historical order would recompute
 * to; the closest thing to an answer for "what rate did we charge in March?" was
 * a number cached on the order.
 *
 * Refusing the edit is not left to the policy — see {@see TaxResource}. A rate
 * version is the evidence a quote is reproduced against, and a guarantee an
 * application can switch off with one `Gate::before` is not a guarantee.
 *
 * A rate is an integer in basis points. 8.5% is 850. Nothing here renders it as
 * a float: `Rate::decimal()` produces a string, and the column shows the basis
 * points alongside it so the stored figure is the one on screen.
 */
class RateVersionResource extends TaxResource
{
    protected static ?string $model = RateVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'rate version';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('jurisdiction_id')
                ->label('Jurisdiction')
                ->options(fn (): array => Tenant::scope(Jurisdiction::query())
                    ->orderBy('code')
                    ->pluck('code', 'id')
                    ->all())
                ->required(),
            TextInput::make('tax_class')
                ->label('Tax class')
                ->helperText('Whatever the caller quotes a line under: standard, reduced, books. Free text, matched exactly.')
                ->required()
                ->maxLength(64),
            TextInput::make('label')
                ->helperText('What this rate is called on a quote and on a return.')
                ->required()
                ->maxLength(120),
            Select::make('treatment')
                ->options(Treatment::class)
                ->default(Treatment::Taxable->value)
                ->helperText('Zero-rated is in scope at a rate of zero and belongs on a return. Exempt carries no liability at all.')
                ->required(),
            TextInput::make('reason')
                ->label('Statutory reason')
                ->helperText('Required in practice for anything not plainly taxable: an exemption without a reason is not an exemption.')
                ->maxLength(120),
            TextInput::make('basis_points')
                ->label('Rate in basis points')
                ->helperText('8.5% is 850. Not a percent and not a decimal — a rate divided by 100 in a float is fault one.')
                ->integer()
                ->minValue(0)
                ->default(0)
                ->required(),
            TextInput::make('sequence')
                ->helperText('Rates in a jurisdiction apply in this order, and the order is recorded on the quote.')
                ->integer()
                ->minValue(1)
                ->default(1)
                ->required(),
            Toggle::make('compound')
                ->helperText('A compound rate applies to the base plus the tax accumulated before it in the sequence.'),
            DateTimePicker::make('effective_from')
                ->label('Effective from')
                ->helperText('When the rate genuinely took effect. Dating it today makes every historical reproduction wrong.')
                ->seconds(false)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jurisdiction.code')->label('Jurisdiction')->sortable(),
                TextColumn::make('tax_class')->label('Class')->sortable(),
                TextColumn::make('label'),
                TextColumn::make('treatment')->badge(),
                TextColumn::make('basis_points')
                    ->label('Rate')
                    ->formatStateUsing(static fn (RateVersion $record): string => self::renderRate($record->basis_points))
                    ->sortable(),
                TextColumn::make('sequence')->sortable(),
                IconColumn::make('compound')->boolean(),
                TextColumn::make('effective_from')->label('From')->dateTime()->sortable(),
                TextColumn::make('effective_to')->label('To')->dateTime()->placeholder('open')->sortable(),
            ])
            ->filters([
                SelectFilter::make('jurisdiction_id')
                    ->label('Jurisdiction')
                    ->relationship('jurisdiction', 'code'),
                SelectFilter::make('treatment')
                    ->options(Treatment::class),
                TernaryFilter::make('in_force')
                    ->label('In force')
                    ->queries(
                        true: static fn (Builder $query): Builder => $query->whereNull('effective_to'),
                        false: static fn (Builder $query): Builder => $query->whereNotNull('effective_to'),
                        blank: static fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                self::reviseAction(),
            ])
            ->defaultSort('effective_from', 'desc');
    }

    /**
     * The only way a rate changes.
     *
     * Presented as an edit form would be, and it is not one: the fields are
     * prefilled from the current version so the operator states the new rate
     * rather than a delta, and what happens is a close plus an insert. Both rows
     * survive, and a March quote still reproduces to the March figure.
     */
    public static function reviseAction(): Action
    {
        return Action::make('revise')
            ->label('Revise')
            ->icon(Heroicon::OutlinedArrowPath)
            ->authorize('revise')
            ->modalHeading('Revise this rate')
            ->modalDescription('This version is closed at the instant below and a successor is inserted from it. Nothing is overwritten, and quotes already given keep the rate they were given.')
            ->fillForm(static fn (RateVersion $record): array => [
                'label' => $record->label,
                'treatment' => $record->treatment->value,
                'reason' => $record->reason,
                'basis_points' => $record->basis_points,
                'sequence' => $record->sequence,
                'compound' => $record->compound,
                'effective_from' => Carbon::now()->format('Y-m-d H:i:s'),
            ])
            ->schema([
                TextInput::make('label')->required()->maxLength(120),
                Select::make('treatment')->options(Treatment::class)->required(),
                TextInput::make('reason')->label('Statutory reason')->maxLength(120),
                TextInput::make('basis_points')->label('Rate in basis points')->integer()->minValue(0)->required(),
                TextInput::make('sequence')->integer()->minValue(1)->required(),
                Toggle::make('compound'),
                DateTimePicker::make('effective_from')
                    ->label('In force from')
                    ->helperText('The current version is closed at this instant and the new one runs from it.')
                    ->seconds(false)
                    ->required(),
            ])
            ->action(static function (RateVersion $record, array $data): void {
                $at = Carbon::parse((string) $data['effective_from']);

                app(ReviseRate::class)($record, [
                    'label' => (string) $data['label'],
                    'treatment' => Treatment::from((string) $data['treatment']),
                    'reason' => $data['reason'] === null ? null : (string) $data['reason'],
                    'basis_points' => (int) $data['basis_points'],
                    'sequence' => (int) $data['sequence'],
                    'compound' => (bool) $data['compound'],
                ], $at);
            });
    }

    /** Basis points and their percentage, both exact, neither a float. */
    public static function renderRate(int $basisPoints): string
    {
        return $basisPoints.' bp ('.(new Rate($basisPoints))->decimal().'%)';
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageRateVersions::route('/'),
        ];
    }
}

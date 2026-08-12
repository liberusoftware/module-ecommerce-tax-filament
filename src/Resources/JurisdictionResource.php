<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Resources;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\Tax\Enums\Sourcing;
use Liberu\Ecommerce\Tax\Filament\Resources\JurisdictionResource\Pages\ManageJurisdictions;
use Liberu\Ecommerce\Tax\Models\Jurisdiction;
use UnitEnum;

/**
 * Where the operator says which jurisdictions this store actually operates in.
 *
 * The module ships none, and it never will: a rate — or a member-state list —
 * baked into a release is a rate that goes stale between releases, and the fleet
 * has no mechanism to hot-fix twenty-seven of them. The host's answer was
 * `EuVat::STANDARD_RATES`, a PHP `const` array of twenty-seven real-world VAT
 * rates docblocked "as of 2025", where a rate change in any member state is a
 * code deploy.
 *
 * Sourcing is set per jurisdiction rather than globally, because it is a
 * property of that jurisdiction's own rule: an installation can hold a
 * destination-sourced regime and an origin-sourced one at the same time.
 */
class JurisdictionResource extends TaxResource
{
    protected static ?string $model = Jurisdiction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'code';

    /** A jurisdiction is operator data, not evidence: it may be corrected. */
    public static function isReadOnly(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Code')
                ->helperText('However you identify the jurisdiction. Quotes record this code, not the row.')
                ->required()
                ->maxLength(32),
            TextInput::make('name')
                ->required()
                ->maxLength(120),
            Select::make('sourcing')
                ->options(Sourcing::class)
                ->helperText('Destination applies where the supply lands; origin applies where it leaves from.')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->sortable()->searchable(),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('sourcing')->badge(),
                TextColumn::make('registrations_count')
                    ->label('Registrations')
                    ->counts('registrations'),
                TextColumn::make('rate_versions_count')
                    ->label('Rate versions')
                    ->counts('rateVersions'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('code');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageJurisdictions::route('/'),
        ];
    }
}

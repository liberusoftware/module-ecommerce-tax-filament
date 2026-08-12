<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Tax\Filament\Resources\RegistrationResource\Pages\ManageRegistrations;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;
use Liberu\Ecommerce\Tax\Models\Jurisdiction;
use Liberu\Ecommerce\Tax\Models\Registration;
use UnitEnum;

/**
 * Nexus, which the host has no concept of at all.
 *
 * `TaxRate::findMatchingRates()` matches on the buyer's address alone, so a
 * store with no registration in a jurisdiction charges tax there anyway, purely
 * because somebody seeded a row. Charging tax you are not registered to collect
 * is a worse failure than not charging it, and until a registration exists here
 * the module charges nothing in that jurisdiction on purpose — recording
 * `no_registration` on the quote as the reason rather than leaving a line
 * silently absent.
 *
 * There is no edit form. A registration is opened, and later closed; both are
 * facts about a period, and rewriting either would make "were we registered in
 * March?" unanswerable in April. `effective_to` is the one column the domain
 * lets move, once, from null, and the Close action is the only thing that moves
 * it.
 *
 * The registration number is neither searchable nor filterable. Search terms and
 * filter state both persist into the query string, and a tax registration number
 * is not something to leave in a browser history or a proxy log.
 */
class RegistrationResource extends TaxResource
{
    protected static ?string $model = Registration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'registration';

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
            TextInput::make('registration_number')
                ->label('Registration number')
                ->required()
                ->maxLength(64),
            DateTimePicker::make('effective_from')
                ->label('Effective from')
                ->helperText('The instant registration actually began, not the instant this row is written.')
                ->seconds(false)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jurisdiction.code')->label('Jurisdiction')->sortable(),
                TextColumn::make('effective_from')->label('From')->dateTime()->sortable(),
                TextColumn::make('effective_to')->label('To')->dateTime()->placeholder('open')->sortable(),
                IconColumn::make('is_open')
                    ->label('Open')
                    ->boolean()
                    ->state(static fn (Registration $record): bool => $record->effective_to === null),
            ])
            ->filters([
                SelectFilter::make('jurisdiction_id')
                    ->label('Jurisdiction')
                    ->relationship('jurisdiction', 'code'),
            ])
            ->recordActions([
                self::closeAction(),
            ])
            ->defaultSort('effective_from', 'desc');
    }

    /**
     * Close a period. Not an edit: the domain refuses every other column at the
     * Eloquent boundary, and refuses this one too once it is no longer null.
     */
    public static function closeAction(): Action
    {
        return Action::make('close')
            ->label('Close')
            ->icon(Heroicon::OutlinedLockClosed)
            ->authorize('close')
            ->requiresConfirmation()
            ->modalDescription('Closing a registration is permanent. Supplies after this instant are quoted with no tax in this jurisdiction, recorded as "no registration".')
            ->schema([
                DateTimePicker::make('effective_to')
                    ->label('Closed from')
                    ->seconds(false)
                    ->required(),
            ])
            ->action(static function (Registration $record, array $data): void {
                $record->effective_to = Carbon::parse((string) $data['effective_to']);
                $record->save();
            });
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageRegistrations::route('/'),
        ];
    }
}

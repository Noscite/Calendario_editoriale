<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Organization\Models\Organization;
use App\Filament\Admin\Resources\OrganizationResource\Pages;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Admin Resource: Organizzazioni.
 * Replica admin.py → list_organizations, CRUD organization (solo superuser).
 */
class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | \UnitEnum | null $navigationGroup = 'Organizzazioni';

    protected static ?string $navigationLabel = 'Organizzazioni';

    protected static ?string $modelLabel = 'Organizzazione';

    protected static ?string $pluralModelLabel = 'Organizzazioni';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informazioni')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('phone')
                        ->label('Telefono')
                        ->tel()
                        ->maxLength(50),

                    Forms\Components\TextInput::make('vat_number')
                        ->label('P.IVA')
                        ->maxLength(50),

                    Forms\Components\TextInput::make('address')
                        ->label('Indirizzo')
                        ->maxLength(500),
                ]),

            Section::make('Abbonamento')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('plan_id')
                        ->label('Piano')
                        ->relationship('plan', 'display_name')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('subscription_status')
                        ->label('Stato abbonamento')
                        ->options(OrganizationStatus::class)
                        ->default(OrganizationStatus::Trial),

                    Forms\Components\DateTimePicker::make('trial_ends_at')
                        ->label('Fine trial'),

                    Forms\Components\DateTimePicker::make('subscription_starts_at')
                        ->label('Inizio abbonamento'),

                    Forms\Components\DateTimePicker::make('subscription_ends_at')
                        ->label('Fine abbonamento'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Attiva')
                        ->default(true),
                ]),

            Section::make('Stripe')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('stripe_customer_id')
                        ->label('Stripe Customer ID')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('stripe_subscription_id')
                        ->label('Stripe Subscription ID')
                        ->maxLength(255),
                ]),

            Section::make('Note')
                ->collapsed()
                ->schema([
                    Forms\Components\KeyValue::make('custom_limits')
                        ->label('Limiti personalizzati'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Note')
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('plan.display_name')
                    ->label('Piano')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subscription_status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (OrganizationStatus $state): string => match ($state) {
                        OrganizationStatus::Active          => 'success',
                        OrganizationStatus::Trial           => 'info',
                        OrganizationStatus::PendingPayment  => 'warning',
                        OrganizationStatus::PastDue         => 'warning',
                        OrganizationStatus::Expired         => 'danger',
                        OrganizationStatus::Suspended,
                        OrganizationStatus::Cancelled       => 'danger',
                    }),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Utenti')
                    ->counts('users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('brands_count')
                    ->label('Brand')
                    ->counts('brands')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attiva')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_status')
                    ->label('Stato')
                    ->options(OrganizationStatus::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Attiva'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit'   => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}

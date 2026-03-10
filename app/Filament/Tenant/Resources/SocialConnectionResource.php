<?php

namespace App\Filament\Tenant\Resources;

use App\Domain\Post\Enums\Platform;
use App\Domain\Social\Models\SocialConnection;
use App\Filament\Tenant\Resources\SocialConnectionResource\Pages;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Tenant Resource: Connessioni Social.
 * Gestione connessioni OAuth per pubblicazione automatica.
 */
class SocialConnectionResource extends Resource
{
    protected static ?string $model = SocialConnection::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-share';

    protected static string | \UnitEnum | null $navigationGroup = 'Social';

    protected static ?string $navigationLabel = 'Connessioni';

    protected static ?string $modelLabel = 'Connessione social';

    protected static ?string $pluralModelLabel = 'Connessioni social';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Connessione')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('brand_id')
                        ->label('Brand')
                        ->relationship('brand', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('platform')
                        ->label('Piattaforma')
                        ->options(Platform::class)
                        ->required(),

                    Forms\Components\TextInput::make('external_account_id')
                        ->label('ID account esterno')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('external_account_name')
                        ->label('Nome account')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('external_account_url')
                        ->label('URL account')
                        ->url()
                        ->maxLength(500),

                    Forms\Components\Select::make('account_type')
                        ->label('Tipo account')
                        ->options([
                            'profile'      => 'Profilo',
                            'page'         => 'Pagina',
                            'organization' => 'Organizzazione',
                            'business'     => 'Business',
                            'location'     => 'Location',
                        ]),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Attiva')
                        ->default(true),
                ]),

            Section::make('Token')
                ->columns(2)
                ->collapsed()
                ->description('I token sono crittografati nel database.')
                ->schema([
                    Forms\Components\TextInput::make('access_token')
                        ->label('Access Token')
                        ->password()
                        ->revealable(),

                    Forms\Components\TextInput::make('refresh_token')
                        ->label('Refresh Token')
                        ->password()
                        ->revealable(),

                    Forms\Components\DateTimePicker::make('token_expires_at')
                        ->label('Scadenza token'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('platform')
                    ->label('Piattaforma')
                    ->badge()
                    ->color(fn (Platform $state): string => match ($state) {
                        Platform::LinkedIn => 'info',
                        Platform::Instagram => 'danger',
                        Platform::Facebook => 'primary',
                        Platform::GoogleBusiness => 'warning',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('external_account_name')
                    ->label('Account')
                    ->searchable(),

                Tables\Columns\TextColumn::make('account_type')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attiva')
                    ->boolean(),

                Tables\Columns\TextColumn::make('token_expires_at')
                    ->label('Scadenza token')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Ultimo uso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('connected_at')
                    ->label('Connesso il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('connected_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->label('Piattaforma')
                    ->options(Platform::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Attiva'),

                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Disconnetti')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (SocialConnection $record) => $record->update(['is_active' => false]))
                    ->visible(fn (SocialConnection $record) => $record->is_active),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSocialConnections::route('/'),
            'create' => Pages\CreateSocialConnection::route('/create'),
            'edit'   => Pages\EditSocialConnection::route('/{record}/edit'),
        ];
    }
}

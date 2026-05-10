<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Organization\Models\Organization;
use App\Domain\User\Models\User;
use App\Filament\Admin\Resources\UserResource\Pages;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Admin Resource: Utenti.
 * Replica admin.py → list_users, create_user, update_user, delete_user.
 *
 * Regole Python:
 * - superuser vede tutti, admin solo sua org
 * - solo superuser può creare/assegnare ruolo "superuser"
 * - non puoi modificare il tuo stesso ruolo
 * - delete = soft-delete (is_active = false)
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'Utenti';

    protected static ?string $navigationLabel = 'Utenti';

    protected static ?string $modelLabel = 'Utente';

    protected static ?string $pluralModelLabel = 'Utenti';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Account')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Forms\Components\TextInput::make('full_name')
                        ->label('Nome completo')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->maxLength(255),

                    Forms\Components\Select::make('role')
                        ->label('Ruolo')
                        ->options([
                            'superuser' => 'Superuser',
                            'admin'     => 'Admin',
                            'editor'    => 'Editor',
                            'viewer'    => 'Viewer',
                        ])
                        ->default('editor')
                        ->required(),

                    Forms\Components\Select::make('organization_id')
                        ->label('Organizzazione')
                        ->relationship('organization', 'name')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Attivo')
                        ->default(true),
                ]),

            Section::make('Profilo')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefono')
                        ->tel()
                        ->maxLength(50),

                    Forms\Components\TextInput::make('company')
                        ->label('Azienda')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('address')
                        ->label('Indirizzo')
                        ->maxLength(500),

                    Forms\Components\TextInput::make('city')
                        ->label('Città')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('country')
                        ->label('Paese')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('vat_number')
                        ->label('P.IVA')
                        ->maxLength(50),

                    Forms\Components\Textarea::make('notes')
                        ->label('Note')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Ruolo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'superuser' => 'danger',
                        'admin'     => 'warning',
                        'editor'    => 'info',
                        'viewer'    => 'gray',
                        default     => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organizzazione')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Ruolo')
                    ->options([
                        'superuser' => 'Superuser',
                        'admin'     => 'Admin',
                        'editor'    => 'Editor',
                        'viewer'    => 'Viewer',
                    ]),

                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organizzazione')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Attivo'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Disattiva')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update(['is_active' => false]))
                    ->visible(fn (User $record) => $record->is_active),
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
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

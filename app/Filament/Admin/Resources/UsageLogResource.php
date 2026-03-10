<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Subscription\Models\UsageLog;
use App\Filament\Admin\Resources\UsageLogResource\Pages;
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
 * Admin Resource: Usage Log (usage_tracking).
 * Visualizzazione e gestione dei consumi per organizzazione.
 */
class UsageLogResource extends Resource
{
    protected static ?string $model = UsageLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | \UnitEnum | null $navigationGroup = 'Piani & Utilizzo';

    protected static ?string $navigationLabel = 'Utilizzo';

    protected static ?string $modelLabel = 'Registro utilizzo';

    protected static ?string $pluralModelLabel = 'Registri utilizzo';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Periodo')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('organization_id')
                        ->label('Organizzazione')
                        ->relationship('organization', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\DatePicker::make('period_start')
                        ->label('Inizio periodo')
                        ->required(),

                    Forms\Components\DatePicker::make('period_end')
                        ->label('Fine periodo')
                        ->required(),
                ]),

            Section::make('Contatori')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('calendar_generations_used')
                        ->label('Generazioni calendario')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('text_tokens_used')
                        ->label('Token testo')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('images_generated')
                        ->label('Immagini generate')
                        ->numeric()
                        ->default(0),
                ]),

            Section::make('Overage')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('overage_tokens')
                        ->label('Token extra')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('overage_images')
                        ->label('Immagini extra')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('overage_cost')
                        ->label('Costo extra (€)')
                        ->numeric()
                        ->prefix('€')
                        ->step(0.01)
                        ->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organizzazione')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Inizio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->label('Fine')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('calendar_generations_used')
                    ->label('Generazioni')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('text_tokens_used')
                    ->label('Token')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('images_generated')
                    ->label('Immagini')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('overage_cost')
                    ->label('Costo extra')
                    ->money('eur')
                    ->sortable(),
            ])
            ->defaultSort('period_start', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organizzazione')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                EditAction::make(),
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
            'index'  => Pages\ListUsageLogs::route('/'),
            'create' => Pages\CreateUsageLog::route('/create'),
            'edit'   => Pages\EditUsageLog::route('/{record}/edit'),
        ];
    }
}

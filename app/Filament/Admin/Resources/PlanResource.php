<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Subscription\Models\Plan;
use App\Filament\Admin\Resources\PlanResource\Pages;
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
 * Admin Resource: Piani di abbonamento.
 * Gestione completa dei piani subscription (subscription_plans table).
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';

    protected static string | \UnitEnum | null $navigationGroup = 'Piani & Utilizzo';

    protected static ?string $navigationLabel = 'Piani';

    protected static ?string $modelLabel = 'Piano';

    protected static ?string $pluralModelLabel = 'Piani';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Generale')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome (slug)')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('display_name')
                        ->label('Nome visualizzato')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('price_monthly')
                        ->label('Prezzo mensile (€)')
                        ->numeric()
                        ->prefix('€')
                        ->step(0.01),

                    Forms\Components\TextInput::make('price_yearly')
                        ->label('Prezzo annuale (€)')
                        ->numeric()
                        ->prefix('€')
                        ->step(0.01),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Attivo')
                        ->default(true),
                ]),

            Section::make('Limiti')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('max_brands')
                        ->label('Max brand')
                        ->numeric()
                        ->default(1),

                    Forms\Components\TextInput::make('max_users')
                        ->label('Max utenti')
                        ->numeric()
                        ->default(1),

                    Forms\Components\TextInput::make('monthly_calendar_generations')
                        ->label('Generazioni/mese')
                        ->numeric()
                        ->default(3),

                    Forms\Components\TextInput::make('monthly_text_tokens')
                        ->label('Token testo/mese')
                        ->numeric()
                        ->default(50000),

                    Forms\Components\TextInput::make('monthly_images')
                        ->label('Immagini/mese')
                        ->numeric()
                        ->default(20),
                ]),

            Section::make('Funzionalità')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('has_export_excel')->label('Export Excel'),
                    Forms\Components\Toggle::make('has_activity_log')->label('Activity Log'),
                    Forms\Components\Toggle::make('has_advanced_roles')->label('Ruoli avanzati'),
                    Forms\Components\Toggle::make('has_api_access')->label('Accesso API'),
                    Forms\Components\Toggle::make('has_crm_integration')->label('Integrazione CRM'),
                    Forms\Components\Toggle::make('has_auto_publishing')->label('Auto pubblicazione'),
                    Forms\Components\Toggle::make('has_analytics')->label('Analytics'),
                    Forms\Components\Toggle::make('has_ab_testing')->label('A/B Testing'),
                ]),

            Section::make('Overage')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\Toggle::make('allows_overage')
                        ->label('Permetti overage')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('overage_price_per_1k_tokens')
                        ->label('Prezzo per 1K token extra')
                        ->numeric()
                        ->prefix('€')
                        ->step(0.0001),

                    Forms\Components\TextInput::make('overage_price_per_image')
                        ->label('Prezzo per immagine extra')
                        ->numeric()
                        ->prefix('€')
                        ->step(0.0001),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Piano')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_monthly')
                    ->label('€/mese')
                    ->money('eur')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_yearly')
                    ->label('€/anno')
                    ->money('eur')
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_brands')
                    ->label('Brand')
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_users')
                    ->label('Utenti')
                    ->sortable(),

                Tables\Columns\TextColumn::make('monthly_calendar_generations')
                    ->label('Gen/mese')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organizations_count')
                    ->label('Org. attive')
                    ->counts('organizations')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->defaultSort('id')
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
            'index'  => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit'   => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}

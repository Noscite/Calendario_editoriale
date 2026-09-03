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
use Illuminate\Database\Eloquent\Builder;

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

                    Forms\Components\Select::make('brand_id')
                        ->label('Brand')
                        ->relationship('brand', 'name')
                        ->searchable()
                        ->preload(),

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

                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable()
                    ->placeholder('— (non attribuito)'),

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

                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('Costo AI stimato')
                    ->state(fn (UsageLog $record): string => static::formatEstimatedCost($record))
                    ->tooltip('Stima da usage_tracking (token totali, non lo split input/output): min = tutto a '
                        . 'tariffa input, max = tutto a tariffa output di Opus 4.8, + immagini a tariffa piatta. '
                        . 'Non è un importo esatto fatturato — vedi Sistema → AI Usage & Costi per il dettaglio per step/modello.'),

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

                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
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

    /**
     * Admin panel cross-organization: bypass del global scope 'organization'
     * applicato dal trait BelongsToOrganization. Il super-admin SaaS vede
     * tutti gli usage log di tutte le org.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope('organization');
    }

    /**
     * Range di costo stimato per la riga (token + immagini), stessa
     * metodologia di UsageAggregator::rawUsageByBrand() — vedi il tooltip
     * della colonna per il perché è un range e non un importo esatto.
     */
    private static function formatEstimatedCost(UsageLog $record): string
    {
        $pricing  = config('ai_pricing.anthropic.claude-opus-4-8', config('ai_pricing.anthropic.default'));
        $usdToEur = (float) config('ai_pricing.usd_to_eur', 0.93);
        $imgCost  = (float) config('ai_pricing.openai_images.default', 0.04);

        $tokens = (int) $record->text_tokens_used;
        $images = (int) $record->images_generated;

        if ($tokens <= 0 && $images <= 0) {
            return '—';
        }

        $minUsd = ($tokens / 1_000_000) * $pricing['input'] + $images * $imgCost;
        $maxUsd = ($tokens / 1_000_000) * $pricing['output'] + $images * $imgCost;

        $minEur = $minUsd * $usdToEur;
        $maxEur = $maxUsd * $usdToEur;

        return '€' . number_format($minEur, 2, ',', '.') . ' – €' . number_format($maxEur, 2, ',', '.');
    }
}

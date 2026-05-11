<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Brand\Enums\Sector;
use App\Domain\Brand\Models\Brand;
use App\Domain\Territorial\Models\Municipality;
use App\Filament\Admin\Resources\BrandResource\Pages;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string | \UnitEnum | null $navigationGroup = 'Organizzazioni';
    protected static ?string $navigationLabel = 'Brand';
    protected static ?string $modelLabel = 'Brand';
    protected static ?string $pluralModelLabel = 'Brand';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Identificativo')
                ->schema([
                    Forms\Components\Select::make('organization_id')
                        ->label('Organizzazione')
                        ->relationship('organization', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('name')
                        ->label('Nome brand')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('sector')
                        ->label('Settore')
                        ->maxLength(255)
                        ->helperText('Descrizione libera dell\'attività (es. "Psicologia clinica, formazione, scrittura"). I vincoli deontologici sono gestiti separatamente sotto.'),

                    Forms\Components\Textarea::make('tone_of_voice')
                        ->label('Tono di voce (descrizione)')
                        ->rows(3)
                        ->helperText('Es: caldo, autentico, vicino alle comunità locali. Sovrascrive i default del settore.'),
                ])
                ->columns(2),

            Section::make('Vincoli deontologici')
                ->description('Settori regolamentati i cui vincoli professionali devono essere rispettati nei post AI. Si applicano cumulativamente: tutte le regole dei settori selezionati saranno iniettate nel system prompt.')
                ->schema([
                    Forms\Components\Toggle::make('has_deontological_constraints_toggle')
                        ->label('⚖️ Brand soggetto a vincoli deontologici')
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Forms\Components\Toggle $component, $state, $record) {
                            if ($record instanceof Brand) {
                                $component->state($record->hasDeontologicalConstraints());
                            }
                        }),

                    Forms\Components\CheckboxList::make('deontological_constraints')
                        ->label('Vincoli applicabili')
                        ->options(collect(Sector::regulatedOptions())->pluck('label', 'value')->toArray())
                        ->columns(1)
                        ->helperText('Seleziona uno o più. I vincoli si applicano cumulativamente alla generazione dei post.')
                        ->visible(fn (Get $get): bool => (bool) $get('has_deontological_constraints_toggle'))
                        ->afterStateHydrated(function (Forms\Components\CheckboxList $component, $state, $record) {
                            if ($record instanceof Brand) {
                                $component->state($record->deontologicalConstraintSlugs()->toArray());
                            }
                        }),
                ]),

            Section::make('Verticalizzazione (opzionale)')
                ->description('Attiva pipeline dati esterne per casi specifici. Lasciare vuoto per brand normali.')
                ->schema([
                    Forms\Components\Select::make('vertical')
                        ->label('Vertical')
                        ->options([
                            'unpli_regional' => 'UNPLI Regionale (federazione regionale)',
                            'pro_loco'       => 'Pro Loco (singolo comune)',
                        ])
                        ->placeholder('— Brand normale, nessuna verticalizzazione —')
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $set('territory_region', null);
                            $set('territory_municipality_istat', null);
                        })
                        ->helperText('Solo per brand UNPLI/Pro Loco. Altri brand: lascia vuoto.'),

                    Forms\Components\Select::make('territory_region')
                        ->label('Regione')
                        ->options(self::regionOptions())
                        ->required(fn (Get $get) => $get('vertical') === 'unpli_regional')
                        ->visible(fn (Get $get) => $get('vertical') === 'unpli_regional')
                        ->helperText('La pipeline territoriale filtrerà gli eventi per questa regione.'),

                    Forms\Components\Select::make('territory_municipality_istat')
                        ->label('Comune')
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            $normalized = Municipality::normalize($search);
                            return Municipality::query()
                                ->where('nome_normalized', 'ILIKE', $normalized . '%')
                                ->orderBy('nome')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Municipality $m) => [$m->codice_istat => "{$m->nome} ({$m->provincia})"])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            $m = Municipality::find($value);
                            return $m ? "{$m->nome} ({$m->provincia})" : null;
                        })
                        ->required(fn (Get $get) => $get('vertical') === 'pro_loco')
                        ->visible(fn (Get $get) => $get('vertical') === 'pro_loco')
                        ->helperText('Digita le prime lettere per cercare. La pipeline territoriale prenderà solo eventi di questo comune.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organizzazione')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sector')
                    ->label('Settore')
                    ->badge(),
                Tables\Columns\TextColumn::make('deontological_constraints_summary')
                    ->label('Vincoli ⚖️')
                    ->state(function (Brand $record): string {
                        $count = $record->deontologicalConstraints()->count();
                        if ($count === 0) {
                            return '—';
                        }
                        $labels = $record->deontologicalConstraintSectors()
                            ->map(fn (Sector $s) => $s->label())
                            ->implode(', ');
                        return "⚖️ {$labels}";
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vertical')
                    ->label('Vertical')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'unpli_regional' => 'info',
                        'pro_loco'       => 'success',
                        default          => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ?? '—'),
                Tables\Columns\TextColumn::make('territory_summary')
                    ->label('Territorio')
                    ->state(function (Brand $record): string {
                        $meta = $record->territory_meta ?? [];
                        if (! empty($meta['region'])) return $meta['region'];
                        if (! empty($meta['municipality_istat'])) {
                            $m = Municipality::find($meta['municipality_istat']);
                            return $m ? "{$m->nome} ({$m->provincia})" : "ISTAT {$meta['municipality_istat']}";
                        }
                        return '—';
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vertical')
                    ->options([
                        'unpli_regional' => 'UNPLI Regionale',
                        'pro_loco'       => 'Pro Loco',
                    ]),
                Tables\Filters\SelectFilter::make('deontological_constraint')
                    ->label('Vincolo deontologico')
                    ->options(collect(Sector::regulatedOptions())->pluck('label', 'value')->toArray())
                    ->query(function ($query, array $data) {
                        if (! empty($data['value'])) {
                            $query->whereHas('deontologicalConstraints', fn ($q) => $q->where('constraint_slug', $data['value']));
                        }
                    }),
                Tables\Filters\SelectFilter::make('organization_id')
                    ->relationship('organization', 'name')
                    ->label('Organizzazione'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit'   => Pages\EditBrand::route('/{record}/edit'),
        ];
    }

    /**
     * Le 20 regioni italiane.
     */
    private static function regionOptions(): array
    {
        $regions = [
            'Abruzzo', 'Basilicata', 'Calabria', 'Campania', 'Emilia-Romagna',
            'Friuli-Venezia Giulia', 'Lazio', 'Liguria', 'Lombardia', 'Marche',
            'Molise', 'Piemonte', 'Puglia', 'Sardegna', 'Sicilia',
            'Toscana', 'Trentino-Alto Adige', 'Umbria', "Valle d'Aosta", 'Veneto',
        ];
        return array_combine($regions, $regions);
    }
}

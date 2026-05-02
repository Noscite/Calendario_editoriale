<?php

namespace App\Filament\Tenant\Resources;

use App\Domain\Brand\Models\Brand;
use App\Filament\Tenant\Resources\BrandResource\Pages;
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
 * Tenant Resource: Brand.
 * Dashboard cliente — gestione brand dell'organizzazione.
 */
class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string | \UnitEnum | null $navigationGroup = 'Contenuti';

    protected static ?string $navigationLabel = 'Brand';

    protected static ?string $modelLabel = 'Brand';

    protected static ?string $pluralModelLabel = 'Brand';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informazioni principali')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('sector')
                        ->label('Settore')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('description')
                        ->label('Descrizione')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('target_audience')
                        ->label('Target audience')
                        ->maxLength(500),

                    Forms\Components\TextInput::make('tone_of_voice')
                        ->label('Tono di voce')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('unique_selling_points')
                        ->label('USP')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('AI & Stile')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('ai_profile')
                        ->label('Profilo AI')
                        ->rows(4)
                        ->columnSpanFull(),

                    Forms\Components\KeyValue::make('brand_values')
                        ->label('Valori del brand')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('colors')
                        ->label('Colori')
                        ->maxLength(500),

                    Forms\Components\Textarea::make('style_guide')
                        ->label('Guida di stile')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Esempi di voce del brand')
                ->description("Inserisci 2-5 post pubblicati o approvati che rappresentano la voce autentica del brand. L'AI userà questi come riferimento concreto per imitare il tuo stile — un esempio reale vale più di 1000 parole di descrizione.")
                ->schema([
                    Forms\Components\Repeater::make('voice_examples')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('platform')
                                ->label('Piattaforma')
                                ->options([
                                    'instagram'       => 'Instagram',
                                    'linkedin'        => 'LinkedIn',
                                    'facebook'        => 'Facebook',
                                    'google_business' => 'Google Business Profile',
                                ])
                                ->required()
                                ->columnSpan(1),

                            Forms\Components\Textarea::make('content')
                                ->label('Testo del post')
                                ->rows(4)
                                ->required()
                                ->minLength(20)
                                ->maxLength(2000)
                                ->helperText('Min 20, max 2000 caratteri')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('note')
                                ->label('Nota (opzionale)')
                                ->placeholder('es: tono che usiamo per gli annunci')
                                ->maxLength(150)
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->maxItems(5)
                        ->defaultItems(0)
                        ->collapsible()
                        ->collapsed()
                        ->reorderable(true)
                        ->addActionLabel('+ Aggiungi esempio')
                        ->itemLabel(fn (array $state): ?string => isset($state['platform']) ? ucfirst((string) $state['platform']) : null)
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Section::make('Link')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('website')
                        ->label('Website')
                        ->url()
                        ->maxLength(500),

                    Forms\Components\TextInput::make('website_url')
                        ->label('Website URL')
                        ->url()
                        ->maxLength(500),

                    Forms\Components\TextInput::make('linkedin_url')
                        ->label('LinkedIn URL')
                        ->url()
                        ->maxLength(500),

                    Forms\Components\TextInput::make('instagram_url')
                        ->label('Instagram URL')
                        ->url()
                        ->maxLength(500),

                    Forms\Components\TextInput::make('facebook_url')
                        ->label('Facebook URL')
                        ->url()
                        ->maxLength(500),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sector')
                    ->label('Settore')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tone_of_voice')
                    ->label('Tono')
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Progetti')
                    ->counts('projects')
                    ->sortable(),

                Tables\Columns\TextColumn::make('social_connections_count')
                    ->label('Social')
                    ->counts('socialConnections')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([])
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
            'index'  => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit'   => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}

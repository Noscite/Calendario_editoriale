<?php

namespace App\Filament\Tenant\Resources;

use App\Domain\Generation\Presets\EditorialPreset;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use App\Filament\Tenant\Resources\ProjectResource\Pages;
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
 * Tenant Resource: Progetti (calendari editoriali).
 */
class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string | \UnitEnum | null $navigationGroup = 'Contenuti';

    protected static ?string $navigationLabel = 'Progetti';

    protected static ?string $modelLabel = 'Progetto';

    protected static ?string $pluralModelLabel = 'Progetti';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Generale')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('brand_id')
                        ->label('Brand')
                        ->relationship('brand', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\DatePicker::make('start_date')
                        ->label('Data inizio')
                        ->required(),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('Data fine')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Stato')
                        ->options(ProjectStatus::class)
                        ->default(ProjectStatus::Draft)
                        ->required(),

                    Forms\Components\Select::make('editorial_preset')
                        ->label('Preset editoriale')
                        ->options(EditorialPreset::options())
                        ->default(EditorialPreset::Standard->value)
                        ->helperText('B2B Authority applica una cadenza settimanale Lun→Ven con un tipo di post dedicato per ogni giorno.'),
                ]),

            Section::make('Piattaforme & Frequenza')
                ->columns(2)
                ->schema([
                    Forms\Components\TagsInput::make('platforms')
                        ->label('Piattaforme')
                        ->suggestions(['linkedin', 'instagram', 'facebook', 'google_business']),

                    Forms\Components\KeyValue::make('posts_per_week')
                        ->label('Post per settimana (piattaforma → numero)'),
                ]),

            Section::make('Contenuti')
                ->schema([
                    Forms\Components\Textarea::make('brief')
                        ->label('Brief')
                        ->rows(3),

                    Forms\Components\Textarea::make('custom_prompt')
                        ->label('Prompt personalizzato')
                        ->rows(3),

                    Forms\Components\TextInput::make('target_audience')
                        ->label('Target audience')
                        ->maxLength(500),

                    Forms\Components\TagsInput::make('themes')
                        ->label('Temi'),

                    Forms\Components\TagsInput::make('content_pillars')
                        ->label('Pilastri contenuto'),

                    Forms\Components\TagsInput::make('objectives')
                        ->label('Obiettivi'),
                ]),

            Section::make('Avanzate')
                ->collapsed()
                ->schema([
                    Forms\Components\TagsInput::make('reference_urls')
                        ->label('URL di riferimento'),

                    Forms\Components\TagsInput::make('competitors')
                        ->label('Competitor'),

                    Forms\Components\KeyValue::make('special_dates')
                        ->label('Date speciali'),

                    Forms\Components\Repeater::make('buyer_personas')
                        ->label('Buyer personas')
                        ->schema([
                            Forms\Components\TextInput::make('name')->label('Nome'),
                            Forms\Components\TextInput::make('role')->label('Ruolo'),
                            Forms\Components\TextInput::make('goals')->label('Obiettivi'),
                        ])
                        ->collapsible()
                        ->collapsed(),
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

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inizio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fine')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (ProjectStatus $state): string => match ($state) {
                        ProjectStatus::Draft => 'gray',
                        ProjectStatus::Generating => 'warning',
                        ProjectStatus::Review => 'info',
                        ProjectStatus::Approved => 'success',
                        ProjectStatus::Published => 'primary',
                    }),

                Tables\Columns\TextColumn::make('posts_count')
                    ->label('Post')
                    ->counts('posts')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options(ProjectStatus::class),

                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
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
            'index'  => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit'   => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}

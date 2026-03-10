<?php

namespace App\Filament\Tenant\Resources;

use App\Domain\Post\Enums\ContentType;
use App\Domain\Post\Enums\Platform;
use App\Domain\Post\Enums\PostStatus;
use App\Domain\Post\Enums\PostType;
use App\Domain\Post\Enums\PublicationStatus;
use App\Domain\Post\Models\Post;
use App\Filament\Tenant\Resources\PostResource\Pages;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Tenant Resource: Post del calendario editoriale.
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static string | \UnitEnum | null $navigationGroup = 'Contenuti';

    protected static ?string $navigationLabel = 'Post';

    protected static ?string $modelLabel = 'Post';

    protected static ?string $pluralModelLabel = 'Post';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Generale')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('project_id')
                        ->label('Progetto')
                        ->relationship('project', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('platform')
                        ->label('Piattaforma')
                        ->options(Platform::class)
                        ->required(),

                    Forms\Components\DatePicker::make('scheduled_date')
                        ->label('Data pianificata'),

                    Forms\Components\TextInput::make('scheduled_time')
                        ->label('Ora pianificata')
                        ->placeholder('HH:MM'),

                    Forms\Components\TextInput::make('title')
                        ->label('Titolo')
                        ->maxLength(500),

                    Forms\Components\TextInput::make('pillar')
                        ->label('Pilastro')
                        ->maxLength(255),
                ]),

            Section::make('Contenuto')
                ->schema([
                    Forms\Components\Textarea::make('content')
                        ->label('Contenuto')
                        ->rows(6)
                        ->columnSpanFull(),

                    Forms\Components\TagsInput::make('hashtags')
                        ->label('Hashtag'),

                    Forms\Components\TextInput::make('call_to_action')
                        ->label('Call to action')
                        ->maxLength(500),

                    Forms\Components\TextInput::make('cta')
                        ->label('CTA breve')
                        ->maxLength(255),
                ]),

            Section::make('Tipo & Stato')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('post_type')
                        ->label('Tipo post')
                        ->options(PostType::class),

                    Forms\Components\Select::make('content_type')
                        ->label('Tipo contenuto')
                        ->options(ContentType::class)
                        ->default(ContentType::Post),

                    Forms\Components\Select::make('status')
                        ->label('Stato')
                        ->options(PostStatus::class)
                        ->default(PostStatus::Draft),

                    Forms\Components\Select::make('publication_status')
                        ->label('Stato pubblicazione')
                        ->options(PublicationStatus::class)
                        ->default(PublicationStatus::Draft),
                ]),

            Section::make('Visual')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('visual_prompt')
                        ->label('Prompt visuale')
                        ->rows(2),

                    Forms\Components\Textarea::make('visual_suggestion')
                        ->label('Suggerimento visuale')
                        ->rows(2),

                    Forms\Components\Textarea::make('image_prompt')
                        ->label('Prompt immagine')
                        ->rows(2),

                    Forms\Components\TextInput::make('image_url')
                        ->label('URL immagine')
                        ->maxLength(1000),

                    Forms\Components\Select::make('media_type')
                        ->label('Tipo media')
                        ->options([
                            'image' => 'Immagine',
                            'video' => 'Video',
                            'reel'  => 'Reel',
                        ])
                        ->default('image'),

                    Forms\Components\Select::make('image_format')
                        ->label('Formato immagine')
                        ->options([
                            '1080x1080' => '1080×1080 (Quadrato)',
                            '1080x1920' => '1080×1920 (Story/Reel)',
                            '1920x1080' => '1920×1080 (Landscape)',
                        ])
                        ->default('1080x1080'),

                    Forms\Components\Toggle::make('is_carousel')
                        ->label('Carosello'),
                ]),

            Section::make('Carosello')
                ->collapsed()
                ->visible(fn (Get $get) => $get('is_carousel'))
                ->schema([
                    Forms\Components\TagsInput::make('carousel_images')
                        ->label('URL immagini carosello'),

                    Forms\Components\TagsInput::make('carousel_prompts')
                        ->label('Prompt carosello'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Progetto')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

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

                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('scheduled_time')
                    ->label('Ora')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Titolo')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('content_type')
                    ->label('Tipo')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (PostStatus $state): string => match ($state) {
                        PostStatus::Draft => 'gray',
                        PostStatus::Approved => 'success',
                    }),

                Tables\Columns\TextColumn::make('publication_status')
                    ->label('Pubblicazione')
                    ->badge()
                    ->color(fn (PublicationStatus $state): string => match ($state) {
                        PublicationStatus::Draft => 'gray',
                        PublicationStatus::Scheduled => 'info',
                        PublicationStatus::Published => 'success',
                        PublicationStatus::Failed => 'danger',
                    }),
            ])
            ->defaultSort('scheduled_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->label('Piattaforma')
                    ->options(Platform::class),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options(PostStatus::class),

                Tables\Filters\SelectFilter::make('publication_status')
                    ->label('Pubblicazione')
                    ->options(PublicationStatus::class),

                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Progetto')
                    ->relationship('project', 'name')
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
            'index'  => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit'   => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}

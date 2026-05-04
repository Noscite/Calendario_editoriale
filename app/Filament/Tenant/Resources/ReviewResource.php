<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Domain\Post\Enums\Platform;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\Review;
use App\Filament\Tenant\Resources\ReviewResource\Pages;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tenant Resource: Recensioni (read-only in M1).
 * M2 introdurrà scoring, M3 reply drafting, M4 reply send.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-star';

    protected static string | \UnitEnum | null $navigationGroup = 'Social';

    protected static ?string $navigationLabel = 'Recensioni';

    protected static ?string $modelLabel = 'Recensione';

    protected static ?string $pluralModelLabel = 'Recensioni';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('status', ReviewStatus::New->value)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Read-only "form" usato dalla ViewPage (Schema/Infolist style). */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Recensione')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('brand.name')
                        ->label('Brand'),

                    Infolists\Components\TextEntry::make('platform')
                        ->label('Piattaforma')
                        ->badge()
                        ->color(fn ($state): string => match ($state) {
                            Platform::GoogleBusiness => 'warning',
                            Platform::LinkedIn       => 'info',
                            Platform::Facebook       => 'primary',
                            Platform::Instagram      => 'danger',
                            default                  => 'gray',
                        })
                        ->formatStateUsing(fn ($state): string => $state instanceof Platform ? $state->label() : (string) $state),

                    Infolists\Components\TextEntry::make('reviewer_name')
                        ->label('Autore')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('rating')
                        ->label('Rating')
                        ->formatStateUsing(fn (int $state): string => str_repeat('★', $state) . str_repeat('☆', 5 - $state)),

                    Infolists\Components\TextEntry::make('review_created_at')
                        ->label('Pubblicata il')
                        ->dateTime('d/m/Y H:i'),

                    Infolists\Components\TextEntry::make('review_updated_at')
                        ->label('Aggiornata il')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('language')
                        ->label('Lingua')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('status')
                        ->label('Stato')
                        ->badge()
                        ->color(fn ($state): string => $state instanceof ReviewStatus ? $state->color() : 'gray')
                        ->formatStateUsing(fn ($state): string => $state instanceof ReviewStatus ? $state->label() : (string) $state),

                    Infolists\Components\TextEntry::make('comment')
                        ->label('Testo')
                        ->columnSpanFull()
                        ->placeholder('(Solo stelle, niente testo)'),
                ]),

            Section::make('Metadata fetch')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('fetched_at')
                        ->label('Fetchata il')
                        ->dateTime('d/m/Y H:i'),

                    Infolists\Components\TextEntry::make('external_review_id')
                        ->label('External ID')
                        ->copyable(),
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
                    ->color(fn ($state): string => match ($state) {
                        Platform::GoogleBusiness => 'warning',
                        Platform::LinkedIn       => 'info',
                        Platform::Facebook       => 'primary',
                        Platform::Instagram      => 'danger',
                        default                  => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => $state instanceof Platform ? $state->label() : (string) $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewer_name')
                    ->label('Autore')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state) . str_repeat('☆', 5 - $state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('comment')
                    ->label('Testo')
                    ->limit(80)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('review_created_at')
                    ->label('Pubblicata')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn ($state): string => $state instanceof ReviewStatus ? $state->color() : 'gray')
                    ->formatStateUsing(fn ($state): string => $state instanceof ReviewStatus ? $state->label() : (string) $state)
                    ->sortable(),
            ])
            ->defaultSort('review_created_at', 'desc')
            ->filters([
                SelectFilter::make('platform')
                    ->label('Piattaforma')
                    ->options(Platform::class),

                SelectFilter::make('rating')
                    ->label('Rating')
                    ->options([
                        1 => '★ (1)',
                        2 => '★★ (2)',
                        3 => '★★★ (3)',
                        4 => '★★★★ (4)',
                        5 => '★★★★★ (5)',
                    ]),

                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(ReviewStatus::class),

                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_ignored')
                        ->label('Marca come ignorata')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(fn (Review $r) => $r->update(['status' => ReviewStatus::Ignored->value]));
                        }),
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
            'index' => Pages\ListReviews::route('/'),
            'view'  => Pages\ViewReview::route('/{record}'),
        ];
    }
}

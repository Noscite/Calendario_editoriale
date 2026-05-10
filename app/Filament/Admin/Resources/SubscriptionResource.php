<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Services\SubscriptionStateService;
use App\Filament\Admin\Resources\SubscriptionResource\Pages;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Organizzazioni';

    protected static ?string $navigationLabel = 'Abbonamenti';

    protected static ?string $modelLabel = 'Abbonamento';

    protected static ?string $pluralModelLabel = 'Abbonamenti';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organizzazione')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Subscription::STATUS_TRIAL           => 'info',
                        Subscription::STATUS_ACTIVE          => 'success',
                        Subscription::STATUS_PENDING_PAYMENT => 'warning',
                        Subscription::STATUS_EXPIRED         => 'danger',
                        Subscription::STATUS_CANCELLED       => 'gray',
                        default                              => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Subscription::STATUS_TRIAL           => 'Trial',
                        Subscription::STATUS_ACTIVE          => 'Attivo',
                        Subscription::STATUS_PENDING_PAYMENT => 'In attesa pagamento',
                        Subscription::STATUS_EXPIRED         => 'Scaduto',
                        Subscription::STATUS_CANCELLED       => 'Cancellato',
                        default                              => $state,
                    }),

                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label('Fine trial')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('paid_period_ends_at')
                    ->label('Scadenza pagato')
                    ->dateTime('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_period_months')
                    ->label('Mesi pagati')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Riferimento')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('activatedBy.full_name')
                    ->label('Attivato da')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('trial_tokens_consumed')
                    ->label('Token trial')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Aggiornato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        Subscription::STATUS_TRIAL           => 'Trial',
                        Subscription::STATUS_ACTIVE          => 'Attivo',
                        Subscription::STATUS_PENDING_PAYMENT => 'In attesa pagamento',
                        Subscription::STATUS_EXPIRED         => 'Scaduto',
                        Subscription::STATUS_CANCELLED       => 'Cancellato',
                    ]),
            ])
            ->actions([
                // ── Attiva pagamento ───────────────────────────────────────────
                Action::make('activate')
                    ->label('Attiva pagamento')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Subscription $record): bool => in_array($record->status, [
                        Subscription::STATUS_PENDING_PAYMENT,
                        Subscription::STATUS_EXPIRED,
                        Subscription::STATUS_CANCELLED,
                    ], strict: true))
                    ->form([
                        Forms\Components\Select::make('plan')
                            ->label('Piano')
                            ->options(array_combine(
                                Subscription::VALID_PLAN_NAMES,
                                array_map('ucfirst', Subscription::VALID_PLAN_NAMES),
                            ))
                            ->required(),

                        Forms\Components\TextInput::make('months')
                            ->label('Mesi')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(12)
                            ->default(3)
                            ->required(),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Riferimento pagamento')
                            ->placeholder('BON-2026-04-26-001')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('notes')
                            ->label('Note')
                            ->rows(2),
                    ])
                    ->action(function (Subscription $record, array $data, SubscriptionStateService $service): void {
                        $service->activatePaidSubscription(
                            sub:              $record,
                            months:           (int) $data['months'],
                            plan:             $data['plan'],
                            adminUserId:      auth()->id(),
                            paymentReference: $data['payment_reference'],
                            notes:            $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->title('Abbonamento attivato')
                            ->body("Organizzazione #{$record->organization_id} attivata per {$data['months']} mesi.")
                            ->success()
                            ->send();
                    }),

                // ── Marca come pending payment ─────────────────────────────────
                Action::make('markPending')
                    ->label('Marca pending')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn (Subscription $record): bool => $record->status === Subscription::STATUS_TRIAL)
                    ->requiresConfirmation()
                    ->modalHeading('Marca come in attesa di pagamento')
                    ->modalDescription('Il trial verrà terminato e lo stato impostato su "in attesa di pagamento".')
                    ->action(function (Subscription $record, SubscriptionStateService $service): void {
                        $service->expireTrial($record);

                        Notification::make()
                            ->title('Stato aggiornato')
                            ->body("Subscription org #{$record->organization_id} → pending_payment.")
                            ->warning()
                            ->send();
                    }),

                // ── Estendi trial 7 giorni ─────────────────────────────────────
                Action::make('extendTrial')
                    ->label('Estendi trial +7gg')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (Subscription $record): bool => in_array($record->status, [
                        Subscription::STATUS_TRIAL,
                        Subscription::STATUS_PENDING_PAYMENT,
                    ], strict: true))
                    ->requiresConfirmation()
                    ->modalHeading('Estendi trial di 7 giorni')
                    ->modalDescription('Il trial verrà esteso di 7 giorni dalla data corrente (o dalla scadenza attuale se futura).')
                    ->action(function (Subscription $record, SubscriptionStateService $service): void {
                        $service->extendTrial($record, 7);

                        Notification::make()
                            ->title('Trial esteso')
                            ->body("Trial org #{$record->organization_id} esteso di 7 giorni.")
                            ->info()
                            ->send();
                    }),

                // ── Cancella subscription ──────────────────────────────────────
                Action::make('cancel')
                    ->label('Cancella')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Subscription $record): bool => $record->status !== Subscription::STATUS_CANCELLED)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo cancellazione')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (Subscription $record, array $data, SubscriptionStateService $service): void {
                        $service->cancel($record, $data['reason']);

                        Notification::make()
                            ->title('Subscription cancellata')
                            ->body("Organizzazione #{$record->organization_id} cancellata.")
                            ->danger()
                            ->send();
                    }),

                ViewAction::make(),
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
            'index' => Pages\ListSubscriptions::route('/'),
            'view'  => Pages\ViewSubscription::route('/{record}'),
        ];
    }
}

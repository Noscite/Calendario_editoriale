<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Selezione organizzazione --}}
        <x-filament::section>
            <x-slot name="heading">Organizzazione</x-slot>
            <div class="max-w-sm">
                <select
                    wire:model.live="selectedOrganizationId"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                >
                    @foreach($this->availableOrganizations() as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>
        </x-filament::section>

        @if($selectedOrganizationId)
            {{-- Saldo corrente --}}
            <x-filament::section>
                <x-slot name="heading">Saldo attuale</x-slot>
                <div class="flex items-center gap-3">
                    <div class="text-4xl font-bold {{ $this->balance() <= 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ number_format($this->balance(), 0, ',', '.') }}
                    </div>
                    <div class="text-sm text-gray-500">post residui</div>
                </div>
                @if($this->balance() <= 0)
                    <p class="text-sm text-red-600 mt-2">⚠️ Credito esaurito: le generazioni per questa organizzazione verranno bloccate finché non ricarichi.</p>
                @endif
            </x-filament::section>

            {{-- Form ricarica manuale --}}
            <x-filament::section>
                <x-slot name="heading">Ricarica</x-slot>
                <x-slot name="description">Registra un accredito manuale (es. dopo bonifico) sul wallet dell'organizzazione selezionata.</x-slot>
                <form wire:submit.prevent="credit" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Quantità post</label>
                        <input
                            type="number" min="1" step="1"
                            wire:model.defer="amount"
                            class="w-full rounded-lg border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Riferimento pagamento</label>
                        <input
                            type="text"
                            wire:model.defer="paymentReference"
                            placeholder="CRO bonifico, ricevuta…"
                            class="w-full rounded-lg border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Nota</label>
                        <input
                            type="text"
                            wire:model.defer="note"
                            placeholder="Note interne facoltative"
                            class="w-full rounded-lg border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div class="sm:col-span-4 flex justify-end">
                        <x-filament::button type="submit" color="primary">
                            Accredita
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>

            {{-- Storico movimenti --}}
            <x-filament::section>
                <x-slot name="heading">Storico movimenti</x-slot>
                @if($this->recentMovements()->isEmpty())
                    <div class="text-sm text-gray-500 italic">Nessun movimento registrato.</div>
                @else
                    <div class="overflow-x-auto -mx-2">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2 px-3 whitespace-nowrap">Data</th>
                                    <th class="text-left py-2 px-3 whitespace-nowrap">Tipo</th>
                                    <th class="text-right py-2 px-3 whitespace-nowrap">Movimento</th>
                                    <th class="text-left py-2 px-3">Dettaglio</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->recentMovements() as $m)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 px-3 whitespace-nowrap text-xs text-gray-500">{{ \Carbon\Carbon::parse($m->created_at)->format('d/m/Y H:i') }}</td>
                                        <td class="py-2 px-3">
                                            @if($m->reason === 'purchase')
                                                <x-filament::badge color="success">Ricarica</x-filament::badge>
                                            @elseif($m->reason === 'consumption')
                                                <x-filament::badge color="gray">Consumo</x-filament::badge>
                                            @else
                                                <x-filament::badge color="warning">Storno</x-filament::badge>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 text-right font-semibold whitespace-nowrap {{ $m->delta > 0 ? 'text-green-600' : 'text-gray-600' }}">
                                            {{ $m->delta > 0 ? '+' : '' }}{{ $m->delta }}
                                        </td>
                                        <td class="py-2 px-3 text-xs text-gray-500">
                                            @if($m->reason === 'purchase')
                                                {{ $m->admin_name ?? '—' }}
                                                @if($m->payment_reference) · rif. {{ $m->payment_reference }} @endif
                                                @if($m->note) · {{ $m->note }} @endif
                                            @elseif($m->post_id)
                                                post #{{ $m->post_id }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>

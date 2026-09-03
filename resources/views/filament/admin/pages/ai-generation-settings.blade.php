@php
    $svc = \App\Domain\Generation\Services\AiGenerationSettingsService::class;
    $help = $svc::parameterHelp();
    $defaults = $svc::hardcodedDefaults();
    $steps = $svc::steps();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Valori di default ereditati: cosa succede se non tocchi nulla --}}
        <x-filament::section>
            <x-slot name="heading">Valori ereditati di default</x-slot>
            <x-slot name="description">
                Se lasci un campo vuoto (sia a livello globale che di brand), la generazione usa questi valori
                — il comportamento del codice prima dell'esistenza di questa pagina. Temperature, Top P e Top K
                non hanno un default hardcoded: se vuoti, semplicemente non vengono inviati all'API e Claude usa
                il proprio default (temperature = 1).
            </x-slot>
            <div class="overflow-x-auto -mx-2">
                <table class="w-full text-sm border-separate" style="border-spacing: 0">
                    <thead>
                        <tr>
                            <th class="text-left py-2 px-3 whitespace-nowrap border-b border-gray-200 dark:border-gray-700">Step</th>
                            <th class="text-left py-2 px-3 whitespace-nowrap border-b border-gray-200 dark:border-gray-700">Modello</th>
                            <th class="text-right py-2 px-3 whitespace-nowrap border-b border-gray-200 dark:border-gray-700">Max tokens</th>
                            <th class="text-center py-2 px-3 whitespace-nowrap border-b border-gray-200 dark:border-gray-700">Prompt caching</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($steps as $step => $label)
                        <tr>
                            <td class="py-2 px-3 border-b border-gray-100 dark:border-gray-800 whitespace-nowrap">{{ $label }}</td>
                            <td class="py-2 px-3 border-b border-gray-100 dark:border-gray-800 whitespace-nowrap">
                                <div class="font-medium">{{ $svc::modelShortLabel($defaults[$step]['model']) }}</div>
                                <div class="text-xs text-gray-400 font-mono">{{ $defaults[$step]['model'] }}</div>
                            </td>
                            <td class="py-2 px-3 border-b border-gray-100 dark:border-gray-800 text-right whitespace-nowrap align-top">{{ number_format($defaults[$step]['max_tokens'], 0, ',', '.') }}</td>
                            <td class="py-2 px-3 border-b border-gray-100 dark:border-gray-800 text-center align-top">
                                @if(! $svc::cachingApplicable($step))
                                    <x-filament::badge color="gray">Non applicabile</x-filament::badge>
                                @elseif($defaults[$step]['caching'])
                                    <x-filament::badge color="success">Attivo</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">Disattivo</x-filament::badge>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                "Non applicabile" = lo step fa una sola chiamata AI (nessuna successiva che possa leggere la cache):
                il codice non legge nemmeno il toggle per questi step, che quindi non ha effetto se lo attivi.
                Vale per tutti tranne "Copy batch", l'unico chiamato più volte di seguito (una per blocco di 14 giorni)
                sullo stesso contesto brand/strategy — per questo è l'unico dove il caching porta un risparmio reale.
            </p>
        </x-filament::section>

        {{-- Cosa fa ogni parametro --}}
        <x-filament::section>
            <x-slot name="heading">Cosa significa ogni parametro</x-slot>
            <dl class="space-y-3 text-sm">
                @foreach($help as $key => $h)
                    <div>
                        <dt class="font-semibold text-gray-800 dark:text-gray-200">{{ $h['label'] }}</dt>
                        <dd class="text-gray-600 dark:text-gray-400">{{ $h['help'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        {{-- Selezione ambito: globale o override per singolo brand --}}
        <x-filament::section>
            <x-slot name="heading">Ambito</x-slot>
            <div class="max-w-sm">
                <select
                    wire:model.live="selectedBrandId"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                >
                    <option value="">🌐 Globale (default per tutta la piattaforma)</option>
                    @foreach($this->availableBrands() as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-2">
                    Un campo lasciato vuoto sull'override di un brand eredita il valore
                    globale; il globale lasciato vuoto eredita il default hardcoded
                    mostrato nella tabella sopra.
                </p>
            </div>
        </x-filament::section>

        <form wire:submit.prevent="save">
            @foreach($steps as $step => $label)
            <x-filament::section class="mb-4">
                <x-slot name="heading">{{ $label }}</x-slot>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="sm:col-span-1 lg:col-span-2">
                        <label class="flex items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                            {{ $help['model']['label'] }}
                            <span title="{{ $help['model']['help'] }}" class="cursor-help text-gray-400">ⓘ</span>
                        </label>
                        <input
                            type="text"
                            list="ai-models-list"
                            wire:model.defer="formData.{{ $step }}.model"
                            placeholder="Eredita ({{ $defaults[$step]['model'] }})"
                            class="w-full rounded-lg border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div>
                        <label class="flex items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                            {{ $help['temperature']['label'] }}
                            <span title="{{ $help['temperature']['help'] }}" class="cursor-help text-gray-400">ⓘ</span>
                        </label>
                        <input
                            type="number" min="0" max="1" step="0.01"
                            wire:model.defer="formData.{{ $step }}.temperature"
                            placeholder="Eredita"
                            class="w-full rounded-lg border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div>
                        <label class="flex items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                            {{ $help['max_tokens']['label'] }}
                            <span title="{{ $help['max_tokens']['help'] }}" class="cursor-help text-gray-400">ⓘ</span>
                        </label>
                        <input
                            type="number" min="1" step="1"
                            wire:model.defer="formData.{{ $step }}.max_tokens"
                            placeholder="Eredita ({{ $defaults[$step]['max_tokens'] }})"
                            class="w-full rounded-lg border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div>
                        <label class="flex items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                            {{ $help['top_p']['label'] }}
                            <span title="{{ $help['top_p']['help'] }}" class="cursor-help text-gray-400">ⓘ</span>
                        </label>
                        <input
                            type="number" min="0" max="1" step="0.01"
                            wire:model.defer="formData.{{ $step }}.top_p"
                            placeholder="Eredita"
                            class="w-full rounded-lg border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div>
                        <label class="flex items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                            {{ $help['top_k']['label'] }}
                            <span title="{{ $help['top_k']['help'] }}" class="cursor-help text-gray-400">ⓘ</span>
                        </label>
                        <input
                            type="number" min="0" step="1"
                            wire:model.defer="formData.{{ $step }}.top_k"
                            placeholder="Eredita"
                            class="w-full rounded-lg border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div class="sm:col-span-3 lg:col-span-1">
                        <label class="flex items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                            {{ $help['prompt_caching_enabled']['label'] }}
                            <span title="{{ $help['prompt_caching_enabled']['help'] }}" class="cursor-help text-gray-400">ⓘ</span>
                        </label>
                        @if($svc::cachingApplicable($step))
                            <select
                                wire:model.defer="formData.{{ $step }}.prompt_caching_enabled"
                                class="w-full rounded-lg border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                            >
                                <option value="">Eredita ({{ $defaults[$step]['caching'] ? 'Attivo' : 'Disattivo' }})</option>
                                <option value="1">Attivo</option>
                                <option value="0">Disattivo</option>
                            </select>
                        @else
                            <select disabled
                                class="w-full rounded-lg border-gray-200 shadow-sm text-sm bg-gray-50 text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500 cursor-not-allowed"
                            >
                                <option>Non applicabile</option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Chiamata singola: il codice non legge questo toggle per questo step.</p>
                        @endif
                    </div>
                </div>
            </x-filament::section>
            @endforeach

            <datalist id="ai-models-list">
                <option value="claude-opus-4-8"></option>
                <option value="claude-opus-4-7"></option>
                <option value="claude-sonnet-4-6"></option>
                <option value="claude-sonnet-4-5"></option>
                <option value="claude-haiku-4-5-20251001"></option>
            </datalist>

            <div class="flex justify-end pt-2">
                <x-filament::button type="submit" color="primary" size="lg">
                    Salva parametri
                </x-filament::button>
            </div>
        </form>

    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Selezione Brand --}}
        <x-filament::section>
            <x-slot name="heading">Seleziona Brand</x-slot>
            <div class="max-w-sm">
                <select
                    wire:model.live="selectedBrandId"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                >
                    @foreach($this->availableBrands() as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
        </x-filament::section>

        @if($selectedBrandId)
        <form wire:submit.prevent="save">

            @foreach(\App\Domain\Brand\Services\BrandApiKeyService::groups() as $groupLabel => $keys)
            <x-filament::section class="mb-4">
                <x-slot name="heading">{{ $groupLabel }}</x-slot>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach($keys as $keyName => $label)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ $label }}
                        </label>
                        <input
                            type="password"
                            wire:model.defer="formData.{{ $keyName }}"
                            placeholder="{{ isset($formData[$keyName]) && $formData[$keyName] ? '••••••••  (impostata)' : 'Non impostata' }}"
                            class="w-full rounded-lg border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                            autocomplete="new-password"
                            autocapitalize="off"
                            autocorrect="off"
                            spellcheck="false"
                        />
                    </div>
                    @endforeach
                </div>
            </x-filament::section>
            @endforeach

            <div class="flex justify-end pt-2">
                <x-filament::button type="submit" color="primary" size="lg">
                    Salva tutte le chiavi
                </x-filament::button>
            </div>

        </form>
        @endif

    </div>
</x-filament-panels::page>

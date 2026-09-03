<x-filament-panels::page>
    @php $data = $this->getDataForView(); @endphp

    @if (isset($data['error']))
        <div class="text-red-600">{{ $data['error'] }}</div>
    @else
        {{-- Selettore organization + periodo --}}
        <div class="flex gap-4 mb-6">
            <select wire:model.live="selectedOrganizationId"
                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-3 py-2 text-sm">
                @foreach (\App\Domain\Organization\Models\Organization::orderBy('name')->get() as $org)
                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="period"
                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-3 py-2 text-sm">
                <option value="current_month">Mese corrente</option>
                <option value="last_30_days">Ultimi 30 giorni</option>
                <option value="last_90_days">Ultimi 90 giorni</option>
            </select>
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                <div class="text-sm text-gray-500">Costo {{ $data['period_label'] }}</div>
                <div class="text-3xl font-bold text-blue-600">
                    €{{ number_format($data['org_cost']['total_eur'], 2, ',', '.') }}
                </div>
                <div class="text-xs text-gray-500 mt-1">{{ $data['org_cost']['event_count'] }} chiamate AI</div>
            </div>

            @if ($data['plan_revenue_eur'] > 0)
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-500">Revenue piano</div>
                    <div class="text-3xl font-bold text-green-600">
                        €{{ number_format($data['plan_revenue_eur'], 2, ',', '.') }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-500">Margine lordo</div>
                    <div class="text-3xl font-bold @if($data['gross_margin_pct'] >= 80) text-green-600 @elseif($data['gross_margin_pct'] >= 60) text-yellow-600 @else text-red-600 @endif">
                        {{ $data['gross_margin_pct'] }}%
                    </div>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                <div class="text-sm text-gray-500">Brand sopra soglia (€{{ $data['alert_threshold'] }})</div>
                <div class="text-3xl font-bold @if($data['brands_over_threshold']->count() > 0) text-red-600 @else text-gray-400 @endif">
                    {{ $data['brands_over_threshold']->count() }}
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                <div class="text-sm text-gray-500">Saldo crediti-post</div>
                @if(! $data['wallet_enrolled'])
                    <div class="text-3xl font-bold text-gray-400">—</div>
                    <div class="text-xs text-gray-500 mt-1">Wallet mai attivato (nessuna ricarica)</div>
                @else
                    <div class="text-3xl font-bold @if($data['credit_balance'] <= 0) text-red-600 @elseif($data['credit_balance'] < $data['low_credit_threshold']) text-orange-500 @else text-green-600 @endif">
                        {{ number_format($data['credit_balance'], 0, ',', '.') }}
                    </div>
                    @if($data['credit_balance'] <= 0)
                        <div class="text-xs text-red-600 mt-1">⚠️ Esaurito — generazioni bloccate</div>
                    @elseif($data['credit_balance'] < $data['low_credit_threshold'])
                        <div class="text-xs text-orange-500 mt-1">⚠️ Sotto soglia ({{ $data['low_credit_threshold'] }})</div>
                    @else
                        <div class="text-xs text-gray-500 mt-1">post residui</div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Grafico spesa giornaliera --}}
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow mb-6">
            <h3 class="font-semibold text-lg mb-4">📈 Spesa giornaliera ultimi 30gg</h3>
            @if ($data['daily']->isEmpty())
                <div class="text-sm text-gray-500 italic">Nessuna generazione registrata negli ultimi 30 giorni.</div>
            @else
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($data['daily'] as $day)
                            <tr class="border-b last:border-0">
                                <td class="py-1 font-mono text-xs w-28">{{ $day['date'] }}</td>
                                <td class="py-1">
                                    @php $width = min(100, max(2, $day['total_eur'] * 100)); @endphp
                                    <div class="bg-blue-500 h-2 rounded" style="width: {{ $width }}%"></div>
                                </td>
                                <td class="py-1 text-right pl-3 font-semibold w-24">€{{ number_format($day['total_eur'], 3, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Costo per step di generazione e modello --}}
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow mb-6">
            <h3 class="font-semibold text-lg mb-4">🧩 Costo per step e modello</h3>
            @if ($data['by_step']->isEmpty())
                <div class="text-sm text-gray-500 italic">Nessuna chiamata AI tracciata nel periodo.</div>
            @else
                <div class="overflow-x-auto -mx-2">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-3">Step</th>
                            <th class="text-left py-2 px-3">Modello</th>
                            <th class="text-right py-2 px-3 whitespace-nowrap">Chiamate</th>
                            <th class="text-right py-2 px-3 whitespace-nowrap">Costo €</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['by_step'] as $row)
                            <tr class="border-b last:border-0">
                                <td class="py-2 px-3">{{ \App\Domain\Generation\Services\AiGenerationSettingsService::steps()[$row['purpose']] ?? $row['purpose'] }}</td>
                                <td class="py-2 px-3 font-mono text-xs whitespace-nowrap">{{ $row['model'] }}</td>
                                <td class="py-2 px-3 text-right whitespace-nowrap">{{ $row['calls'] }}</td>
                                <td class="py-2 px-3 text-right font-semibold whitespace-nowrap">€{{ number_format($row['total_eur'], 3, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

        {{-- Top 10 brand --}}
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
            <h3 class="font-semibold text-lg mb-4">🏆 Top 10 brand per consumo</h3>
            @if ($data['top_brands']->isEmpty())
                <div class="text-sm text-gray-500 italic">Nessun brand con post tracciati nel periodo.</div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 w-10">#</th>
                            <th class="text-left py-2">Brand</th>
                            <th class="text-right py-2 w-32">Costo €</th>
                            <th class="text-left py-2 pl-4 w-32">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['top_brands'] as $i => $brand)
                            <tr class="border-b">
                                <td class="py-2">{{ $i + 1 }}</td>
                                <td class="py-2 font-semibold">{{ $brand['brand_name'] }}</td>
                                <td class="py-2 text-right">€{{ number_format($brand['total_eur'], 3, ',', '.') }}</td>
                                <td class="py-2 pl-4">
                                    @if ($brand['total_eur'] > $data['alert_threshold'])
                                        <span class="px-2 py-0.5 bg-red-100 text-red-800 rounded text-xs">⚠️ Sopra soglia</span>
                                    @else
                                        <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs">OK</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</x-filament-panels::page>

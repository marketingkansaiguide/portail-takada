<x-filament-panels::page>
    <div class="space-y-6">
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 text-sm flex items-center justify-between">
            <div>
                <strong class="font-bold text-base block mb-1">🛏️ {{ __('Planning des Check-ins Hôtels (Dossiers Confirmés)') }}</strong>
                <p>{{ __('Cochez les dossiers souhaités dans la liste ci-dessous, puis cliquez sur') }} <strong>"{{ __('Imprimer la plaquette d’étiquettes') }}"</strong> {{ __('dans la barre d’actions groupées.') }}</p>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

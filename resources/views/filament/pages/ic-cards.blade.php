<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 32px;">

        {{-- Section Récapitulative Totale des Déclinaisons --}}
        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 20px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
            
            {{-- En-tête du bloc --}}
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f3f4f6; padding-bottom: 14px; margin-bottom: 18px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 22px; line-height: 1;">💳</span>
                    <div>
                        <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: #111827;">
                            Cartes IC en attente de validation
                        </h3>
                        <p style="margin: 2px 0 0 0; font-size: 12px; color: #6b7280;">
                            Synthèse complète de toutes les déclinaisons pour les dossiers confirmés
                        </p>
                    </div>
                </div>

                <div style="background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 6px 16px; border-radius: 20px; font-weight: 800; font-size: 13px;">
                    Total général : {{ $this->totalPendingCards }} carte(s)
                </div>
            </div>

            {{-- Grille compacte de toutes les déclinaisons --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px;">
                @foreach ($this->icCardsSummary as $item)
                    @php
                        $hasCount = $item['count'] > 0;
                    @endphp
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-radius: 8px; border: 1px solid {{ $hasCount ? '#fcd34d' : '#f3f4f6' }}; background-color: {{ $hasCount ? '#fffbebfb' : '#fafafa' }}; transition: all 0.15s ease;">
                        <span style="font-size: 13px; font-weight: {{ $hasCount ? '800' : '500' }}; color: {{ $hasCount ? '#111827' : '#9ca3af' }}; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 8px;" title="{{ $item['name'] }}">
                            {{ $item['name'] }}
                        </span>
                        <span style="font-size: 12px; font-weight: 900; padding: 3px 9px; border-radius: 6px; background-color: {{ $hasCount ? '#f59e0b' : '#e5e7eb' }}; color: {{ $hasCount ? '#ffffff' : '#6b7280' }}; flex-shrink: 0;">
                            {{ $item['count'] }}
                        </span>
                    </div>
                @endforeach
            </div>

        </div>

        {{-- Grand Tableau Filament des Prestations --}}
        <div>
            {{ $this->table }}
        </div>

    </div>
</x-filament-panels::page>
{{-- Vue « jour par jour » des prestations (back-office dossier), inspirée du tableau Excel des équipes. --}}
@php
    $fmtPrice = fn ($value) => number_format((float) $value, 0, ',', ' ');
    $locale = app()->getLocale();
@endphp

<div
    class="tk-dbd"
    x-data="{
        mode: (() => { try { return localStorage.getItem('tk-dbd-mode') || 'day' } catch (e) { return 'day' } })(),
        init() { this.apply() },
        setMode(mode) {
            this.mode = mode;
            try { localStorage.setItem('tk-dbd-mode', mode) } catch (e) {}
            this.apply();
        },
        apply() {
            const section = this.$el.closest('.fi-section');
            if (section) section.classList.toggle('tk-dbd-on', this.mode === 'day');
        },
        item(repeater, key) {
            return [...document.querySelectorAll('li.fi-fo-repeater-item')].find((li) =>
                li.getAttribute('x-sortable-item') === key
                && (li.getAttribute('wire:key') || '').includes('.' + repeater + '.')
            );
        },
        open(repeater, key) {
            const li = this.item(repeater, key);
            if (! li) return;
            li.classList.add('tk-dbd-open', 'tk-dbd-flash');
            li.dispatchEvent(new CustomEvent('expand'));
            setTimeout(() => li.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
            setTimeout(() => li.classList.remove('tk-dbd-flash'), 1800);
        },
        act(repeater, key, action) {
            const li = this.item(repeater, key);
            const button = li && [...li.querySelectorAll('.fi-fo-repeater-item-header-end-actions [wire\\:click]')]
                .find((el) => (el.getAttribute('wire:click') || '').includes(`mountAction('${action}'`));
            if (button) button.click(); else this.open(repeater, key);
        },
        closeAll() {
            document.querySelectorAll('.tk-dbd-open').forEach((el) => el.classList.remove('tk-dbd-open'));
        },
    }"
>
    <style>
        .tk-dbd { --tk-dbd-line: var(--gray-200); --tk-dbd-head: var(--gray-50); --tk-dbd-muted: var(--gray-400); }
        html.dark .tk-dbd { --tk-dbd-line: var(--gray-700); --tk-dbd-head: var(--gray-800); --tk-dbd-muted: var(--gray-500); }
        .tk-dbd-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .9rem; }
        .tk-dbd-kpis { display: flex; flex-wrap: wrap; gap: .4rem 1rem; font-size: .8rem; color: var(--gray-500); }
        .tk-dbd-kpis b { color: var(--gray-950); font-weight: 700; }
        html.dark .tk-dbd-kpis b { color: var(--gray-50); }
        .tk-dbd-seg { display: inline-flex; border: 1px solid var(--tk-dbd-line); border-radius: .375rem; overflow: hidden; font-size: .8rem; font-weight: 600; }
        .tk-dbd-seg button { padding: .4rem .85rem; color: var(--gray-600); background: transparent; }
        html.dark .tk-dbd-seg button { color: var(--gray-300); }
        .tk-dbd-seg button.is-on { background: var(--primary-600); color: #fff; }
        .tk-dbd-link { font-size: .75rem; color: var(--gray-500); text-decoration: underline; margin-right: .5rem; }
        .tk-dbd-scroll { overflow-x: auto; }
        .tk-dbd table { width: 100%; border-collapse: collapse; font-size: .8125rem; min-width: 960px; }
        .tk-dbd th { background: var(--tk-dbd-head); text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .5rem .6rem; border: 1px solid var(--tk-dbd-line); font-weight: 600; }
        .tk-dbd td { border: 1px solid var(--tk-dbd-line); padding: .45rem .6rem; vertical-align: middle; }
        .tk-dbd td.tk-j { text-align: center; font-weight: 700; width: 2.6rem; background: var(--tk-dbd-head); }
        .tk-dbd td.tk-d { width: 6.2rem; white-space: nowrap; font-weight: 600; background: var(--tk-dbd-head); }
        .tk-dbd tr.is-we td.tk-d, .tk-dbd tr.is-we td.tk-j { color: var(--primary-700); }
        html.dark .tk-dbd tr.is-we td.tk-d, html.dark .tk-dbd tr.is-we td.tk-j { color: var(--primary-400); }
        .tk-dbd tr.is-day td { border-top: 2px solid var(--gray-300); }
        html.dark .tk-dbd tr.is-day td { border-top-color: var(--gray-600); }
        .tk-dbd tr.is-free td { color: var(--tk-dbd-muted); font-style: italic; }
        .tk-dbd tr.is-item { cursor: pointer; }
        .tk-dbd tr.is-item:hover td:not(.tk-j):not(.tk-d) { background: color-mix(in srgb, var(--primary-500) 5%, transparent); }
        .tk-dbd .tk-name { font-weight: 500; color: var(--gray-950); }
        .tk-dbd td.tk-p { min-width: 15rem; }
        html.dark .tk-dbd .tk-name { color: var(--gray-50); }
        .tk-dbd .is-cancelled .tk-name { text-decoration: line-through; color: var(--tk-dbd-muted); }
        .tk-dbd .tk-opt { font-size: .75rem; color: var(--gray-500); min-width: 7.5rem; }
        .tk-dbd .tk-buy { color: var(--gray-500); }
        .tk-dbd .tk-c { text-align: center; white-space: nowrap; }
        .tk-dbd .tk-r { text-align: right; white-space: nowrap; }
        .tk-dbd .tk-flag { display: inline-block; font-size: .72rem; font-style: normal; color: var(--gray-600); background: var(--tk-accent-soft, #f3f1ec); border: 1px solid var(--tk-accent-border, #e4e0d6); border-radius: .25rem; padding: 0 .4rem; margin-right: .4rem; }
        .tk-dbd .tk-lock { font-size: .72rem; color: var(--gray-500); white-space: nowrap; }
        .tk-dbd .tk-badge { display: inline-flex; align-items: center; padding: .1rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; white-space: nowrap; border: 1px solid; }
        .tk-dbd .tk-success { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .tk-dbd .tk-warning { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .tk-dbd .tk-danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .tk-dbd .tk-info { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .tk-dbd .tk-gray { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
        html.dark .tk-dbd .tk-badge { background: transparent; }
        html.dark .tk-dbd .tk-success { color: #4ade80; border-color: #166534; }
        html.dark .tk-dbd .tk-warning { color: #fbbf24; border-color: #92400e; }
        html.dark .tk-dbd .tk-danger { color: #f87171; border-color: #991b1b; }
        html.dark .tk-dbd .tk-info { color: #60a5fa; border-color: #1e40af; }
        html.dark .tk-dbd .tk-gray { color: #94a3b8; border-color: #334155; }
        .tk-dbd .tk-alert-invoice { display: block; color: #dc2626; font-weight: 700; font-size: .72rem; }
        .tk-dbd .tk-alert-supplier { display: inline-block; margin-top: .15rem; background: #fee2e2; color: #b91c1c; border: 1px solid #f87171; border-radius: 999px; padding: 0 .5rem; font-weight: 700; font-size: .68rem; white-space: nowrap; }
        .tk-dbd .tk-acts { display: inline-flex; gap: .25rem; }
        .tk-dbd .tk-acts button { font-size: .7rem; font-weight: 600; color: var(--gray-600); border: 1px solid var(--tk-dbd-line); border-radius: .25rem; padding: .1rem .35rem; background: transparent; white-space: nowrap; }
        html.dark .tk-dbd .tk-acts button { color: var(--gray-300); }
        .tk-dbd .tk-acts button:hover { border-color: var(--gray-400); }
        .tk-dbd .tk-acts button.is-del { color: #b91c1c; }
        .tk-dbd tfoot td { font-weight: 700; background: var(--tk-dbd-head); }
        .tk-dbd-empty { padding: 1rem; text-align: center; color: var(--gray-500); font-size: .85rem; border: 1px dashed var(--tk-dbd-line); border-radius: .5rem; }
        /* Mode jour par jour : les fiches du repeater restent repliées et masquées, sauf celles ouvertes depuis le tableau ou pas encore enregistrées. */
        .tk-dbd-on .fi-fo-repeater-item[x-sortable-item^="record-"]:not(.tk-dbd-open) { display: none; }
        .fi-fo-repeater-item.tk-dbd-flash { outline: 2px solid var(--primary-500); outline-offset: 2px; transition: outline-color .3s; }
    </style>

    <div class="tk-dbd-bar">
        <div class="tk-dbd-kpis" x-show="mode === 'day'">
            <span><b>{{ count($days) }}</b> {{ __('jours') }}</span>
            <span><b>{{ $total }}</b> {{ __('prestations') }}</span>
            @if ($counts->get('success'))<span><b>{{ $counts->get('success') }}</b> {{ __('confirmées') }}</span>@endif
            @if ($counts->get('warning'))<span><b>{{ $counts->get('warning') }}</b> {{ __('en attente') }}</span>@endif
            @if ($counts->get('info'))<span><b>{{ $counts->get('info') }}</b> {{ __('en cours') }}</span>@endif
            @if ($cancelledCount)<span style="color:#b91c1c"><b style="color:inherit">{{ $cancelledCount }}</b> {{ __('annulées / sans dispo') }}</span>@endif
        </div>
        <div x-show="mode !== 'day'"></div>
        <div style="display:flex;align-items:center">
            <button type="button" class="tk-dbd-link" x-show="mode === 'day'" x-on:click="closeAll()">{{ __('Masquer les fiches ouvertes') }}</button>
            <div class="tk-dbd-seg">
                <button type="button" x-bind:class="{ 'is-on': mode === 'list' }" x-on:click="setMode('list')">{{ __('Liste') }}</button>
                <button type="button" x-bind:class="{ 'is-on': mode === 'day' }" x-on:click="setMode('day')">{{ __('Jour par jour') }}</button>
            </div>
        </div>
    </div>

    <div x-show="mode === 'day'">
        @if (empty($days) && empty($undated))
            <div class="tk-dbd-empty">{{ __('Aucune prestation pour le moment. Renseignez les dates du séjour ou ajoutez une prestation.') }}</div>
        @else
            <div class="tk-dbd-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('J') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Prestation') }}</th>
                            <th>{{ __('Option') }}</th>
                            <th class="tk-c">{{ __('Qté') }}</th>
                            <th>{{ __('Fournisseur') }}</th>
                            <th>{{ __('Statut') }}</th>
                            <th class="tk-r">{{ __('Achat ¥') }}</th>
                            <th class="tk-r">{{ __('Vente ¥') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_merge($days, empty($undated) ? [] : [['number' => null, 'date' => null, 'is_start' => false, 'is_end' => false, 'rows' => $undated]]) as $day)
                            @php
                                $isWeekend = $day['date'] && $day['date']->isWeekend();
                                $flags = [];
                                if ($day['is_start']) $flags[] = '✈️ ' . __('Arrivée');
                                if ($day['is_end']) $flags[] = '✈️ ' . __('Départ');
                                $dayLabel = $day['date'] ? \Illuminate\Support\Str::ucfirst($day['date']->copy()->locale($locale)->isoFormat('ddd DD/MM')) : __('Sans date');
                                $rowCount = max(1, count($day['rows']));
                            @endphp

                            @if (empty($day['rows']))
                                <tr class="is-day is-free {{ $isWeekend ? 'is-we' : '' }}">
                                    <td class="tk-j">{{ $day['number'] }}</td>
                                    <td class="tk-d">{{ $dayLabel }}</td>
                                    <td colspan="8">
                                        @foreach ($flags as $flag)<span class="tk-flag">{{ $flag }}</span>@endforeach
                                        — {{ __('journée libre') }}
                                    </td>
                                </tr>
                            @else
                                @foreach ($day['rows'] as $i => $row)
                                    <tr
                                        class="is-item {{ $i === 0 ? 'is-day' : '' }} {{ $isWeekend ? 'is-we' : '' }} {{ $row['cancelled'] ? 'is-cancelled' : '' }}"
                                        x-on:click="open(@js($row['repeater']), @js($row['key']))"
                                        title="{{ __('Ouvrir la fiche de la prestation') }}"
                                    >
                                        @if ($i === 0)
                                            <td class="tk-j" rowspan="{{ $rowCount }}">{{ $day['number'] ?? '—' }}</td>
                                            <td class="tk-d" rowspan="{{ $rowCount }}">{{ $dayLabel }}</td>
                                        @endif
                                        <td class="tk-p">
                                            @if ($i === 0)
                                                @foreach ($flags as $flag)<span class="tk-flag">{{ $flag }}</span>@endforeach
                                            @endif
                                            <span class="tk-name">{{ $row['name'] }}</span>
                                            @if ($row['internal'])
                                                <span class="tk-lock">🔒 {{ __('interne') }}</span>
                                            @endif
                                        </td>
                                        <td class="tk-opt">{{ $row['options'] }}</td>
                                        <td class="tk-c">{{ $row['quantity'] }}</td>
                                        <td>
                                            {{ $row['supplier'] ?? ($row['internal'] ? '' : '—') }}
                                            @if ($row['awaiting_invoice'])
                                                <span class="tk-alert-invoice">⚠️ {{ __('En attente de facture') }}</span>
                                            @endif
                                            @if ($row['must_select_supplier'])
                                                <span class="tk-alert-supplier">⚠️ {{ __('SÉLECTIONNER UN FOURNISSEUR') }}</span>
                                            @endif
                                        </td>
                                        <td><span class="tk-badge tk-{{ $row['status_tone'] }}">{{ $row['status_name'] }}</span></td>
                                        <td class="tk-r tk-buy">{{ $fmtPrice($row['purchase']) }}</td>
                                        <td class="tk-r">{{ $row['internal'] ? '—' : $fmtPrice($row['price']) }}</td>
                                        <td class="tk-r" x-on:click.stop>
                                            <span class="tk-acts">
                                                @if (! $row['internal'])
                                                    <button type="button" title="{{ __('Aperçu de l\'email fournisseur') }}" x-on:click="act(@js($row['repeater']), @js($row['key']), 'generateSupplierEmail')">{{ __('Mail') }}</button>
                                                    <button type="button" title="{{ __('Ouvrir un nouveau FAX dans Google Sheets') }}" x-on:click="act(@js($row['repeater']), @js($row['key']), 'generateGoogleSheetFax')">{{ __('Fax') }}</button>
                                                @endif
                                                <button type="button" class="is-del" title="{{ __('Supprimer') }}" x-on:click="act(@js($row['repeater']), @js($row['key']), 'delete')">{{ __('Suppr.') }}</button>
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" class="tk-r">{{ __('Totaux (hors annulations)') }}</td>
                            <td class="tk-r tk-buy">{{ $fmtPrice($purchaseTotal) }}</td>
                            <td class="tk-r">{{ $fmtPrice($billable) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>

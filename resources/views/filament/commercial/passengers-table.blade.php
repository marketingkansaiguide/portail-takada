@php($passengers = $folder->folderPassengers()->orderByDesc('is_lead')->orderBy('id')->get())
<div style="width:100%">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
        <span class="tk-c-muted">{{ trans_choice(':count voyageur|:count voyageurs', $passengers->count(), ['count' => $passengers->count()]) }}
            @if ($passengers->count() !== ((int) $folder->pax_adults + (int) $folder->pax_children))
                · <span style="color:#92400e">{{ __('Le dossier annonce :n voyageur(s).', ['n' => (int) $folder->pax_adults + (int) $folder->pax_children]) }}</span>
                <button type="button" style="text-decoration:underline;min-height:28px" wire:click="syncPaxCount">{{ __('Mettre à jour le nombre de voyageurs du dossier') }}</button>
            @endif
        </span>
        <x-filament::button size="sm" wire:click="mountAction('editPassenger')">{{ __('Ajouter un voyageur') }}</x-filament::button>
    </div>
    @if ($passengers->isEmpty())
        <p class="tk-c-muted">{{ __('Aucun voyageur saisi.') }}</p>
    @else
    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
        <thead><tr style="text-align:left;color:#6b7280;border-bottom:1px solid #e5e7eb">
            <th style="padding:6px">{{ __('Nom') }}</th><th style="padding:6px">{{ __('Prénom') }}</th><th style="padding:6px">{{ __('Naissance') }}</th>
            <th style="padding:6px">{{ __('Principal') }}</th><th style="padding:6px">{{ __('Besoins') }}</th><th style="padding:6px"><span class="sr-only">{{ __('Actions') }}</span></th>
        </tr></thead>
        <tbody>
        @foreach ($passengers as $p)
            <tr style="border-bottom:1px solid #f3f4f6">
                <td style="padding:6px"><strong>{{ mb_strtoupper($p->last_name) }}</strong></td>
                <td style="padding:6px">{{ $p->first_name }}</td>
                <td style="padding:6px">{{ $p->birth_date ? \Carbon\Carbon::parse($p->birth_date)->format('d/m/Y') : '—' }}</td>
                <td style="padding:6px">{{ $p->is_lead ? '★' : '' }}</td>
                <td style="padding:6px">@if ($p->dietary_restrictions)<span title="{{ __('Besoins alimentaires') }}">🍽</span>@endif @if ($p->mobility_concerns)<span title="{{ __('Accessibilité / mobilité') }}">♿</span>@endif @if ($p->special_notes)<span title="{{ __('Informations particulières') }}">📝</span>@endif</td>
                <td style="padding:6px;white-space:nowrap;text-align:right">
                    <button type="button" style="text-decoration:underline;min-height:32px" wire:click="mountAction('editPassenger', {id: {{ $p->id }}})">{{ __('Modifier') }}</button> ·
                    <button type="button" style="text-decoration:underline;color:#b91c1c;min-height:32px" wire:click="mountAction('deletePassenger', {id: {{ $p->id }}})">{{ __('Supprimer') }}</button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

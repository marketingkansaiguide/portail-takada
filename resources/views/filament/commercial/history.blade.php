@include('filament.commercial.styles')
@php $rows = \App\Models\FolderHistory::with('user')->where('folder_id', $folder->id)->latest('id')->limit(60)->get(); @endphp
@if ($rows->isEmpty())
    <span class="tk-c-muted">{{ __('Aucune modification enregistrée.') }}</span>
@else
    <table class="tk-c-table">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Auteur') }}</th><th>{{ __('Action') }}</th><th>{{ __('Détails') }}</th></tr></thead>
        <tbody>
        @foreach ($rows as $h)
            <tr><td style="white-space:nowrap">{{ $h->updated_at?->format('d/m/Y H:i') }}</td><td>{{ $h->user?->name ?? __('Système') }}</td><td>{{ $h->action }}</td><td style="white-space:pre-wrap;max-width:32rem">{{ \Illuminate\Support\Str::limit(strip_tags((string) $h->details), 400) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="tk-c-muted" style="font-size:.75rem;margin-top:.3rem">{{ __('Les 60 dernières modifications ; l’historique complet est conservé.') }}</div>
@endif

@extends('emails.layouts.portal')

@section('title', __('Nouveau dossier validé'))
@section('heading', __('Nouveau dossier validé'))

@section('content')
    <p style="margin:0 0 18px;">{{ __('Une agence vient de valider un dossier. Il est prêt à être pris en charge.') }}</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0; background:#f7faf9; border-left:4px solid #096a61; border-radius:0 8px 8px 0;">
        @php($details = [__('Référence') => $folder->reference, __('Dossier') => $folder->folder_name, __('Agence') => $folder->agency->name ?? __('Inconnue'), __('Pax leader') => $folder->lead_traveler_name, __('Prestations') => $folder->folderItems()->count(), __('Montant total') => number_format($folder->total_price, 0, '.', ' ') . ' ¥'])
        @foreach ($details as $label => $value)
            <tr><td style="padding:{{ $loop->first ? '17px 18px 7px' : ($loop->last ? '7px 18px 17px' : '7px 18px') }}; color:#6b7280; font-size:12px; font-weight:bold; width:38%;">{{ $label }}</td><td style="padding:{{ $loop->first ? '17px 18px 7px 0' : ($loop->last ? '7px 18px 17px 0' : '7px 18px 7px 0') }}; color:#1f2937; font-size:14px; font-weight:600;">{{ $value }}</td></tr>
        @endforeach
    </table>
    <div style="margin:28px 0 4px; text-align:center;"><a href="{{ route('filament.admin.resources.folders.edit', ['record' => $folder->id]) }}" style="display:inline-block; padding:13px 23px; border-radius:7px; background:#096a61; color:#fff; text-decoration:none; font-size:14px; font-weight:bold;">{{ __('Ouvrir le dossier') }}</a></div>
@endsection

@extends('emails.layouts.portal')

@section('title', __('Nouveau message sur votre dossier'))
@section('heading', __('Nouveau message'))

@section('content')
    <p style="margin:0 0 18px;">{{ __('Bonjour,') }}</p>
    <p>{{ __('L’équipe Takada vient de vous laisser un nouveau message concernant votre dossier de voyage.') }}</p>
    <div style="margin:22px 0; padding:18px; background:#f7faf9; border-left:4px solid #096a61; border-radius:0 8px 8px 0;"><div style="font-size:17px; color:#1f2937; font-weight:bold;">{{ $folder->folder_name }}</div><div style="margin-top:5px; color:#6b7280; font-size:13px;">{{ __('Pax principal :') }} {{ $folder->lead_traveler_name }}</div></div>
    <p>{{ __('Pour lire ce message et y répondre, accédez à votre portail B2B.') }}</p>
    <div style="margin:28px 0 4px; text-align:center;"><a href="{{ url('/agency-folders/' . $folder->id . '/edit') }}" style="display:inline-block; padding:13px 23px; border-radius:7px; background:#096a61; color:#fff; text-decoration:none; font-size:14px; font-weight:bold;">{{ __('Ouvrir le dossier') }}</a></div>
@endsection

@extends('emails.layouts.portal')

@section('title', $subjectLine)
@section('heading', $heading)

@section('content')
    <p style="margin:0 0 18px;">{{ $intro }}</p>
    @if ($details !== [])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0; background:#f7faf9; border-left:4px solid #096a61; border-radius:0 8px 8px 0;">
            @foreach ($details as $label => $value)
                <tr><td style="padding:{{ $loop->first ? '17px 18px 7px' : ($loop->last ? '7px 18px 17px' : '7px 18px') }}; color:#6b7280; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:.3px; width:38%; vertical-align:top;">{{ $label }}</td><td style="padding:{{ $loop->first ? '17px 18px 7px 0' : ($loop->last ? '7px 18px 17px 0' : '7px 18px 7px 0') }}; color:#1f2937; font-size:14px; font-weight:600;">{{ $value }}</td></tr>
            @endforeach
        </table>
    @endif
    @if (filled($messageText))<div style="margin:22px 0; padding:18px; background:#f7faf9; border-radius:8px; border:1px solid #e4eeeb; color:#374151; white-space:pre-line;">{{ $messageText }}</div>@endif
    @if (filled($actionLabel) && filled($actionUrl))<div style="margin:28px 0 4px; text-align:center;"><a href="{{ $actionUrl }}" style="display:inline-block; padding:13px 23px; border-radius:7px; background:#096a61; color:#ffffff; text-decoration:none; font-size:14px; font-weight:bold;">{{ $actionLabel }}</a></div>@endif
@endsection

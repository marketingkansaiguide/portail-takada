<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>IWA {{ $reference }}</title>
<style>
    {{-- Mise en forme : tout est ici, facile à ajuster. BIZ UDPGothic couvre latin et japonais (dompdf n'a pas de repli par glyphe). --}}
    @font-face { font-family: 'biz'; font-weight: normal; font-style: normal; src: url('{{ $fontDir }}/BIZUDPGothic-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'biz'; font-weight: bold; font-style: normal; src: url('{{ $fontDir }}/BIZUDPGothic-Bold.ttf') format('truetype'); }
    @page { margin: 19mm 18mm 19mm 18mm; }
    body { font-family: 'biz', sans-serif; font-size: 10pt; color: #000; background: #fff; }

    /* Bloc agence : largeur de la colonne « お客様名 » de l'Excel (129pt, élargi à 170pt pour tenir sur une ligne), pointillé 0,75pt */
    .agency { width: 170pt; border: 0.75pt dotted #000; padding: 10pt 2pt; white-space: nowrap; text-align: center; font-weight: bold; font-size: 10pt; line-height: 1.4; }
    .client { margin: 10pt 0 12pt 0; font-size: 10pt; line-height: 1.5; }
    .client .name { font-weight: bold; }

    table.routes { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0 auto; }
    table.routes th, table.routes td { text-align: center; vertical-align: middle; color: #000; }
    /* En-tête : 12pt gras, fond bleu, bordures haute et latérales épaisses (2,25pt) */
    table.routes th { font-size: 12pt; font-weight: bold; background: #C9DAF8; height: 50pt; padding: 4pt;
        border-top: 2.25pt solid #000; border-bottom: 0.75pt solid #000; border-left: 0.75pt solid #000; border-right: 0.75pt solid #000; }
    table.routes th.first { border-left: 2.25pt solid #000; }
    table.routes th.last { border-right: 2.25pt solid #000; }
    /* Données : 10pt, tout centré, aucun gras, bordures fines 0,75pt, hauteur mini 52pt */
    table.routes td { font-size: 10pt; font-weight: normal; height: 52pt; padding: 3pt 4pt; border: 0.75pt solid #000; }
    table.routes tr { page-break-inside: avoid; }
    /* Largeurs relatives de l'Excel : 90 / 168 / 70 / 72 / 72 */
    col.c-date { width: 19%; } col.c-detail { width: 35.6%; } col.c-dep { width: 14.8%; } col.c-arr { width: 15.3%; } col.c-pax { width: 15.3%; }

    .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8pt; color: #444; text-align: center; }
    .footer .page:after { content: counter(page) " / " counter(pages); }
</style>
</head>
<body>
<div class="footer">{{ $generatedAt }} &nbsp;·&nbsp; <span class="page"></span></div>

<div class="agency">{!! nl2br(e($agency)) !!}</div>

<div class="client">
    <span class="name">{{ $traveler }}</span><br>
    {{ $reference }} — {{ $folderName }}<br>
    {{ $startJa }}@if ($endJa !== '') ～ {{ $endJa }}@endif
</div>

<table class="routes">
    <colgroup>
        <col class="c-date"><col class="c-detail"><col class="c-dep"><col class="c-arr"><col class="c-pax">
    </colgroup>
    <thead>
        <tr>
            <th class="first">日程</th>
            <th>列車名/区間/<br>設備/乗車券</th>
            <th>出発時刻</th>
            <th>到着時刻</th>
            <th class="last">人数</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($lines as $l)
        <tr>
            <td>{{ $l['date'] }}</td>
            <td>{!! nl2br(e($l['detail'])) !!}</td>
            <td>{{ $l['dep'] }}</td>
            <td>{{ $l['arr'] }}</td>
            <td>{{ $l['pax'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>

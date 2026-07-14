<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>FAX - {{ $subject }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            font-family: "MS Mincho", "Hiragino Mincho Pro", serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
            color: #000;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
        }
        @media print {
            body { background-color: white; }
            .page { margin: 0; box-shadow: none; border: none; }
        }
        .title {
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 10px;
            margin-bottom: 20px;
        }
        .date {
            text-align: right;
            font-size: 14px;
            margin-bottom: 30px;
        }
        .header-grid {
            display: table;
            width: 100%;
            margin-bottom: 40px;
            font-size: 14px;
            line-height: 1.6;
        }
        .col-left, .col-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .col-right {
            padding-left: 20px;
        }
        .info-label {
            display: inline-block;
            width: 60px;
        }
        .subject {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 40px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .body-text {
            font-size: 14px;
            line-height: 1.8;
            white-space: pre-wrap;
            margin-bottom: 50px;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-top: 50px;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="page">
        <div class="title">FAX</div>
        
        <div class="date">{{ $date }}</div>

        <div class="header-grid">
            <div class="col-left">
                <div>送付先：</div>
                <div style="font-size: 16px; font-weight: bold; margin-top: 5px;">{{ $to_company }}</div>
                <div style="font-size: 16px; margin-bottom: 20px;">{{ $to_contact }}</div>
                
                <div><span class="info-label">TEL：</span>{{ $to_tel }}</div>
                <div><span class="info-label">FAX：</span>{{ $to_fax }}</div>
            </div>
            
            <div class="col-right">
                <div>発信元：</div>
                <div style="margin-top: 5px;">{{ $from_company }}</div>
                <div>{{ $from_address }}</div>
                <div style="margin-top: 10px;">{{ $from_contact }}</div>
                <div>{{ $from_mail }}</div>
                <div><span class="info-label">TEL：</span>{{ $from_tel }}</div>
                <div><span class="info-label">FAX：</span>{{ $from_fax }}</div>
            </div>
        </div>

        <div class="subject">
            件名： {{ $subject }}
        </div>

        <div class="body-text">{!! e($body) !!}</div>

        <div class="footer">
            <div>{{ $writer_name }}</div>
            <div>敬具</div>
        </div>
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routing Slip - {{ $document->tracking_number }}</title>
    <style>
        @page { size: A4; margin: 12mm 15mm; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
            color: #222;
            line-height: 1.5;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 3px double #2d5a27;
        }
        .header .agency {
            font-size: 14px;
            font-weight: 700;
            color: #2d5a27;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .header .sub-agency {
            font-size: 10px;
            color: #555;
            margin-top: 2px;
        }
        .header .slip-title {
            font-size: 18px;
            font-weight: 800;
            margin: 8px 0 6px;
            letter-spacing: 3px;
            color: #1a1a1a;
            text-transform: uppercase;
        }
        .header .tracking {
            font-size: 12px;
            font-weight: 600;
            color: #2d5a27;
            background: #f0f5f0;
            display: inline-block;
            padding: 4px 18px;
            border-radius: 3px;
            letter-spacing: 0.5px;
        }

        .info-box {
            border: 1.5px solid #2d5a27;
            border-radius: 6px;
            margin-bottom: 12px;
            overflow: hidden;
        }
        .info-cell {
            flex: 1;
            padding: 7px 10px;
            border-right: 1px solid #e0e0e0;
            display: flex;
            align-items: baseline;
            gap: 6px;
            min-height: 32px;
        }
        .info-row {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-cell {
            flex: 1;
            padding: 9px 14px;
            border-right: 1px solid #e0e0e0;
            display: flex;
            align-items: baseline;
            gap: 6px;
            min-height: 38px;
        }
        .info-cell:last-child {
            border-right: none;
        }
        .info-label {
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
            white-space: nowrap;
            min-width: 110px;
        }
        .info-value {
            font-size: 13px;
            font-weight: 500;
            color: #1a1a1a;
        }
        .info-cell.wide {
            flex: 2;
        }

        .table-wrapper {
            border: 1.5px solid #2d5a27;
            border-radius: 6px;
            overflow: hidden;
            margin-top: 4px;
        }
        table.routing-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.routing-table thead th {
            background: #2d5a27;
            color: #fff;
            padding: 6px 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: center;
            border-bottom: 2px solid #1e401a;
        }
        table.routing-table thead th:first-child { border-radius: 0; }
        table.routing-table tbody td {
            padding: 6px 8px;
            font-size: 11px;
            border-bottom: 1px solid #e8e8e8;
            vertical-align: top;
            color: #333;
        }
        table.routing-table tbody tr:nth-child(even) td {
            background: #fafafa;
        }
        table.routing-table tbody tr:hover td {
            background: #f0f5f0;
        }
        table.routing-table td.center {
            text-align: center;
        }
        table.routing-table td.date-cell {
            white-space: nowrap;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 11px;
        }

        .remarks-box {
            border: 1.5px solid #2d5a27;
            border-radius: 6px;
            padding: 8px 10px;
            margin-top: 10px;
        }
        .remarks-box .label {
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
            margin-bottom: 4px;
        }

        .footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #ccc;
            font-size: 9px;
            color: #999;
            text-align: center;
            letter-spacing: 0.3px;
        }

        .no-print-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1000;
            display: flex;
            gap: 8px;
        }
        .no-print-btn button {
            padding: 9px 22px;
            background: #2d5a27;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Segoe UI', sans-serif;
            transition: background 0.15s;
        }
        .no-print-btn button:hover { background: #1e401a; }
        .no-print-btn button.close-btn { background: #666; }
        .no-print-btn button.close-btn:hover { background: #444; }

        @media print {
            .no-print-btn { display: none; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="no-print-btn">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()" class="close-btn">Close</button>
    </div>

    <div class="header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
            @if($logoPath)
                <div style="text-align:left;flex-shrink:0;">
                    <img src="{{ asset('storage/' . $logoPath) }}" alt="Logo" style="height:52px;width:auto;">
                </div>
            @else
                <div style="text-align:left;flex-shrink:0;">
                    <div style="width:52px;height:52px;border:2px solid #2d5a27;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#2d5a27;font-weight:700;font-size:9px;text-align:center;">MB</div>
                </div>
            @endif
            <div style="flex:1;text-align:center;">
                <div class="agency">MBLISTTDA</div>
                <div class="sub-agency">Metropolitan Baguio City, La Trinidad, Itogon, Sablan, Tuba, and Tublay Development Authority</div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <img id="qr-img" src="" width="60" height="60" alt="QR" style="border:1px solid #ddd;padding:2px;">
            </div>
        </div>
        <div class="slip-title">Document Routing Slip</div>
        <div class="tracking">Tracking No. {{ $document->tracking_number }}</div>
    </div>

    <div class="info-box">
        <div class="info-row">
            <div class="info-cell">
                <span class="info-label">Document Type:</span>
                <span class="info-value">{{ $document->category ?: ucfirst($document->type) }}</span>
            </div>
            <div class="info-cell">
                <span class="info-label">Date Created:</span>
                <span class="info-value">{{ $document->created_at->format('F j, Y') }}</span>
            </div>
            <div class="info-cell">
                <span class="info-label">Source:</span>
                <span class="info-value">{{ optional($document->sender)->name ?? optional($document->office)->name ?? 'N/A' }}</span>
            </div>
        </div>
        <div class="info-row">
            <div class="info-cell wide">
                <span class="info-label">Subject / Title:</span>
                <span class="info-value">{{ $document->title }}</span>
            </div>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="routing-table">
            <thead>
                <tr>
                    <th style="width:16%;">Date Forwarded</th>
                    <th style="width:22%;">From</th>
                    <th style="width:22%;">To</th>
                    <th style="width:12%;">Time</th>
                    <th style="width:28%;">Actions / Remarks</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $blankRows = 10;
                    $dataRows = count($rows);
                @endphp

                @foreach($rows as $row)
                <tr>
                    <td class="center date-cell">{{ $row->date }}</td>
                    <td>{!! $row->from !!}</td>
                    <td>{!! $row->to !!}</td>
                    <td class="center date-cell">{{ $row->time }}</td>
                    <td>&nbsp;</td>
                </tr>
                @endforeach

                @for($i = 0; $i < max(0, $blankRows - $dataRows); $i++)
                <tr>
                    <td class="center">&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td class="center">&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
                @endfor

                @if($dataRows >= $blankRows)
                    @for($i = 0; $i < 5; $i++)
                    <tr>
                        <td class="center">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td class="center">&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    @endfor
                @endif
            </tbody>
        </table>
    </div>

    @if($document->remarks)
    <div class="remarks-box">
        <div class="label">Remarks</div>
        <div style="font-size:12px;color:#333;">{{ $document->remarks }}</div>
    </div>
    @endif

    <div class="footer">
        MBLISTTDA Document Tracking System &middot; Routing Slip #{{ $document->tracking_number }} &middot; Printed {{ date('m/d/Y h:i A') }}
    </div>

    <script>
    document.getElementById('qr-img').src = 'https://api.qrserver.com/v1/create-qr-code/?size=60x60&color=2d5a27&data=' + encodeURIComponent('{{ $docUrl }}');
    </script>
</body>
</html>

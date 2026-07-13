@php
    $isExternal = ($document->communication_type ?? 'internal') === 'external';
    $mc = $isExternal ? '#CC6B2C' : '#4A7C2E';
    $lc = $isExternal ? '#D97D3E' : '#5E8F42';
    $dc = $isExternal ? '#A84C0E' : '#2E5E1A';
    $bg = $isExternal ? '#FDF0E0' : '#E8F0E0';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routing Slip - {{ $document->tracking_number }}</title>
    <style>
        :root { --mc: {{ $mc }}; --lc: {{ $lc }}; --dc: {{ $dc }}; --bg: {{ $bg }}; }
        @page { size: A4; margin: 15mm 20mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; color: #222; line-height: 1.5; background: #e6e6e6; margin: 0; padding: 40px 0; }
        .paper { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 15mm 20mm; box-shadow: 0 4px 24px rgba(0,0,0,0.15); position: relative; }
        .header { text-align: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 3px double var(--mc); }
        .header .agency { font-size: 14px; font-weight: 700; color: var(--mc); letter-spacing: 1px; text-transform: uppercase; }
        .header .sub-agency { font-size: 10px; color: #555; margin-top: 2px; }
        .header .slip-title { font-size: 18px; font-weight: 800; margin: 8px 0 6px; letter-spacing: 3px; color: #1a1a1a; text-transform: uppercase; }
        .header .tracking { font-size: 12px; font-weight: 600; color: var(--mc); background: var(--bg); display: inline-block; padding: 4px 18px; border-radius: 3px; letter-spacing: 0.5px; }
        .info-box { border: 1.5px solid var(--mc); border-radius: 6px; margin-bottom: 12px; overflow: hidden; }
        .info-row { display: flex; border-bottom: 1px solid #e0e0e0; }
        .info-row:last-child { border-bottom: none; }
        .info-cell { flex: 1; padding: 9px 14px; border-right: 1px solid #e0e0e0; display: flex; align-items: baseline; gap: 6px; min-height: 38px; }
        .info-cell:last-child { border-right: none; }
        .info-label { font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #666; white-space: nowrap; min-width: 110px; }
        .info-value { font-size: 13px; font-weight: 500; color: #1a1a1a; }
        .info-cell.wide { flex: 2; }
        .comm-badge { display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 1px 8px; border-radius: 3px; background: var(--bg); color: var(--mc); }
        .table-wrapper { border: 1.5px solid var(--mc); border-radius: 6px; overflow: hidden; margin-top: 4px; }
        table.routing-table { width: 100%; border-collapse: collapse; }
        table.routing-table thead th { background: var(--mc); color: #fff; padding: 6px 8px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; text-align: center; border-bottom: 2px solid var(--dc); }
        table.routing-table thead th:first-child { border-radius: 0; }
        table.routing-table tbody td { padding: 6px 8px; font-size: 11px; border-bottom: 1px solid #e8e8e8; vertical-align: top; color: #333; }
        table.routing-table tbody tr:nth-child(even) td { background: #fafafa; }
        table.routing-table tbody tr:hover td { background: var(--bg); }
        table.routing-table td.center { text-align: center; }
        table.routing-table td.date-cell { white-space: nowrap; font-family: 'Consolas', 'Courier New', monospace; font-size: 11px; }
        .remarks-box { border: 1.5px solid var(--mc); border-radius: 6px; padding: 8px 10px; margin-top: 10px; }
        .remarks-box .label { font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #666; margin-bottom: 4px; }
        .footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid #ccc; font-size: 9px; color: #999; text-align: center; letter-spacing: 0.3px; }
        .no-print-btn { position: fixed; top: 16px; right: 16px; z-index: 1000; display: flex; gap: 8px; }
        .no-print-btn button { padding: 9px 22px; background: var(--mc); color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; font-family: 'Segoe UI', sans-serif; transition: background 0.15s; }
        .no-print-btn button:hover { background: var(--dc); }
        .no-print-btn button.close-btn { background: #666; }
        .no-print-btn button.close-btn:hover { background: #444; }
        @media print { .no-print-btn { display: none; } body { background: #fff; padding: 0; } .paper { box-shadow: none; width: 100%; min-height: auto; padding: 0; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="no-print-btn">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()" class="close-btn">Close</button>
    </div>

    <div class="paper">
    <div class="header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
            @if($logoPath)
                <div style="text-align:left;flex-shrink:0;">
                    <img src="{{ asset('storage/' . $logoPath) }}" alt="Logo" style="height:52px;width:auto;">
                </div>
            @else
                <div style="text-align:left;flex-shrink:0;">
                    <div style="width:52px;height:52px;border:2px solid var(--mc);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--mc);font-weight:700;font-size:9px;text-align:center;">MB</div>
                </div>
            @endif
            <div style="flex:1;text-align:center;">
                <div style="font-size:10px;font-weight:600;color:#555;letter-spacing:0.5px;text-transform:uppercase;margin-bottom:2px;">Republic of the Philippines</div>
                <div style="font-size:10px;font-weight:600;color:#555;letter-spacing:0.5px;text-transform:uppercase;margin-bottom:6px;">Office of the President</div>
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
                <span class="info-label">Communication:</span>
                <span class="info-value"><span class="comm-badge">{{ ucfirst($document->communication_type ?? 'internal') }}</span></span>
            </div>
            <div class="info-cell">
                <span class="info-label">Date Created:</span>
                <span class="info-value">{{ $document->created_at->format('F j, Y') }}</span>
            </div>
        </div>
        <div class="info-row">
            <div class="info-cell">
                <span class="info-label">Source:</span>
                <span class="info-value">{{ optional($document->sender)->name ?? optional($document->office)->name ?? 'N/A' }}</span>
            </div>
            <div class="info-cell">
                <span class="info-label">Office &amp; Section:</span>
                <span class="info-value">
                    @php
                        $creator = $document->creator ?? $document->sender;
                        $creatorOffice = $creator ? ($creator->office_id ? \App\Models\Office::find($creator->office_id) : null) : null;
                        $creatorSection = $creator && $creator->section_id ? \App\Models\Section::find($creator->section_id) : null;
                    @endphp
                    {{ optional($creatorOffice)->name ?? 'N/A' }}{{ $creatorSection ? ' - ' . $creatorSection->name : '' }}
                </span>
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
                    <td style="font-size:11px;color:#333;">
                        @php
                            $ar = $row->action_requested ?? null;
                            $rn = $row->notes ?? null;
                            if ($rn) {
                                $systemPrefixes = ['Forwarded to ', 'Document created by ', 'Document received', 'Document processed'];
                                foreach ($systemPrefixes as $prefix) {
                                    if (str_starts_with($rn, $prefix)) {
                                        $parts = explode(' - ', $rn, 2);
                                        $rn = isset($parts[1]) ? trim($parts[1]) : null;
                                        break;
                                    }
                                }
                            }
                        @endphp
                        @if($loop->first && $document->remarks)
                            {{ $document->remarks }}
                        @elseif($ar || $rn)
                            @if($ar && $ar !== 'Study/Review')<span style="display:inline-block;background:var(--mc);color:#fff;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:600;">{{ $ar }}</span>@endif
                            @if($rn) <span style="color:#555;">{{ $rn }}</span>@endif
                        @else
                            &nbsp;
                        @endif
                    </td>
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

    <div style="margin-top:12px;padding:10px 14px;border:1px solid #ccc;border-radius:4px;font-size:9px;color:#666;line-height:1.5;text-align:center;">
        <strong>Republic Act No. 6713</strong> — This document is issued in compliance with the <em>Code of Conduct and Ethical Standards for Public Officials and Employees</em>, which mandates transparency, accountability, and the highest standards of professionalism in government service.
    </div>

    <div style="text-align:right;font-size:9px;color:#999;margin-top:6px;font-style:italic;">*This is a system generated document.</div>

    <div class="footer">
        MBLISTTDA Document Tracking System &middot; Routing Slip #{{ $document->tracking_number }} &middot; Printed {{ date('m/d/Y h:i A') }}
    </div>

    <script>
    document.getElementById('qr-img').src = 'https://api.qrserver.com/v1/create-qr-code/?size=60x60&color={{ str_replace('#', '', $mc) }}&data=' + encodeURIComponent('{{ $docUrl }}');
    </script>
</div>
</body>
</html>

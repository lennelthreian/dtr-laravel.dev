@extends('dts.layouts.app')

@section('title', 'DTS Analytics')

@section('content')
<div class="page-header">
    <h1>DTS Analytics Dashboard</h1>
    <a href="{{ route('dts.index') }}" class="btn btn-outline btn-sm">&larr; Back to Dashboard</a>
</div>

<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('dts.analytics') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div style="flex:1;min-width:140px;">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Date From</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" style="width:100%;padding:7px 10px;border:1.5px solid var(--gray-300);border-radius:var(--radius);font-size:13px;">
        </div>
        <div style="flex:1;min-width:140px;">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Date To</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" style="width:100%;padding:7px 10px;border:1.5px solid var(--gray-300);border-radius:var(--radius);font-size:13px;">
        </div>
        <div style="flex:1;min-width:160px;">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Office</label>
            <select name="office_id" style="width:100%;padding:7px 10px;border:1.5px solid var(--gray-300);border-radius:var(--radius);font-size:13px;">
                <option value="">All Offices</option>
                @foreach($offices as $office)
                    <option value="{{ $office->id }}" {{ ($filters['office_id'] ?? '') == $office->id ? 'selected' : '' }}>{{ $office->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1;min-width:130px;">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Status</label>
            <select name="status" style="width:100%;padding:7px 10px;border:1.5px solid var(--gray-300);border-radius:var(--radius);font-size:13px;">
                <option value="">All Statuses</option>
                <option value="pending" {{ ($filters['status'] ?? '') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="received" {{ ($filters['status'] ?? '') == 'received' ? 'selected' : '' }}>Received</option>
                <option value="processed" {{ ($filters['status'] ?? '') == 'processed' ? 'selected' : '' }}>Processed</option>
            </select>
        </div>
        <div style="flex:1;min-width:130px;">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Priority</label>
            <select name="priority" style="width:100%;padding:7px 10px;border:1.5px solid var(--gray-300);border-radius:var(--radius);font-size:13px;">
                <option value="">All Priorities</option>
                <option value="urgent" {{ ($filters['priority'] ?? '') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                <option value="high" {{ ($filters['priority'] ?? '') == 'high' ? 'selected' : '' }}>High</option>
                <option value="normal" {{ ($filters['priority'] ?? '') == 'normal' ? 'selected' : '' }}>Normal</option>
                <option value="low" {{ ($filters['priority'] ?? '') == 'low' ? 'selected' : '' }}>Low</option>
            </select>
        </div>
        <div style="flex:1;min-width:130px;">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Type</label>
            <select name="type" style="width:100%;padding:7px 10px;border:1.5px solid var(--gray-300);border-radius:var(--radius);font-size:13px;">
                <option value="">All Types</option>
                <option value="incoming" {{ ($filters['type'] ?? '') == 'incoming' ? 'selected' : '' }}>Incoming</option>
                <option value="outgoing" {{ ($filters['type'] ?? '') == 'outgoing' ? 'selected' : '' }}>Outgoing</option>
            </select>
        </div>
        <div style="display:flex;gap:6px;">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <a href="{{ route('dts.analytics') }}" class="btn btn-outline btn-sm">Reset</a>
        </div>
    </form>
</div>

<div class="stat-row">
    <div class="stat-card">
        <div class="stat-val">{{ $totalDocuments }}</div>
        <div class="stat-lbl">Total Documents</div>
    </div>
    <div class="stat-card">
        <div class="stat-val">{{ $pendingCount }}</div>
        <div class="stat-lbl">Pending</div>
    </div>
    <div class="stat-card">
        <div class="stat-val">{{ $receivedCount }}</div>
        <div class="stat-lbl">Received</div>
    </div>
    <div class="stat-card">
        <div class="stat-val">{{ $processedCount }}</div>
        <div class="stat-lbl">Processed</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #2196F3;">
        <div class="stat-val">{{ $avgProcessingDays ? round($avgProcessingDays, 1) : '-' }}</div>
        <div class="stat-lbl">Avg. Days to Process</div>
    </div>
</div>

<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
    <div style="flex:2;min-width:0;">
        <div class="card" style="margin-bottom:16px;">
            <h2 style="font-size:15px;margin-bottom:16px;">Documents Created Per Month
                @if(!empty($filters['date_from']) || !empty($filters['date_to']))
                    ({{ $filters['date_from'] ?? 'Start' }} to {{ $filters['date_to'] ?? 'Now' }})
                @else
                    (Last 12 Months)
                @endif
            </h2>
            <canvas id="monthlyChart" height="200" style="width:100%;"></canvas>
        </div>

        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div class="card" style="flex:1;min-width:300px;">
                <h2 style="font-size:15px;margin-bottom:12px;">Documents by Office</h2>
                @if($officeStats->isEmpty())
                    <p style="text-align:center;color:var(--gray-500);padding:16px;">No data.</p>
                @else
                <canvas id="officeChart" height="200"></canvas>
                @endif
            </div>
            <div class="card" style="flex:1;min-width:300px;">
                <h2 style="font-size:15px;margin-bottom:12px;">Documents by Category</h2>
                @if($categoryStats->isEmpty())
                    <p style="text-align:center;color:var(--gray-500);padding:16px;">No data.</p>
                @else
                <canvas id="categoryChart" height="200"></canvas>
                @endif
            </div>
        </div>

        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:16px;">
            <div class="card" style="flex:1;min-width:200px;">
                <h2 style="font-size:15px;margin-bottom:12px;">By Status</h2>
                <canvas id="statusChart" height="150"></canvas>
            </div>
            <div class="card" style="flex:1;min-width:200px;">
                <h2 style="font-size:15px;margin-bottom:12px;">By Priority</h2>
                <canvas id="priorityChart" height="150"></canvas>
            </div>
            <div class="card" style="flex:1;min-width:200px;">
                <h2 style="font-size:15px;margin-bottom:12px;">By Type</h2>
                <canvas id="typeChart" height="150"></canvas>
            </div>
            <div class="card" style="flex:1;min-width:200px;">
                <h2 style="font-size:15px;margin-bottom:12px;">By Communication</h2>
                <canvas id="commChart" height="150"></canvas>
            </div>
        </div>
    </div>

    <div style="flex:1;min-width:280px;">
        <div class="card" style="margin-bottom:16px;">
            <h2 style="font-size:15px;margin-bottom:12px;">Top Senders</h2>
            @if($topSenders->isEmpty())
                <p style="text-align:center;color:var(--gray-500);padding:16px;">No data.</p>
            @else
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th style="text-align:right;">Docs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topSenders as $i => $sender)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $sender->last_name }}, {{ $sender->first_name }}</td>
                        <td style="text-align:right;font-weight:600;">{{ $sender->doc_count }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div class="card">
            <h2 style="font-size:15px;margin-bottom:12px;">Top Recipients</h2>
            @if($topRecipients->isEmpty())
                <p style="text-align:center;color:var(--gray-500);padding:16px;">No data.</p>
            @else
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th style="text-align:right;">Docs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topRecipients as $i => $recipient)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $recipient->last_name }}, {{ $recipient->first_name }}</td>
                        <td style="text-align:right;font-weight:600;">{{ $recipient->doc_count }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
var COLORS = ['#4A7C2E','#5E8F42','#2E5E1A','#8BC34A','#CDDC39','#FF9800','#2196F3','#9C27B0','#E91E63','#00BCD4','#FF5722','#607D8B'];

function drawBarChart(canvasId, labels, data, color) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);

    var w = rect.width, h = rect.height;
    var padding = {top: 20, right: 20, bottom: 40, left: 50};
    var chartW = w - padding.left - padding.right;
    var chartH = h - padding.top - padding.bottom;
    var maxVal = Math.max.apply(null, data) || 1;
    var barW = Math.min(chartW / labels.length * 0.6, 40);
    var gap = chartW / labels.length;

    ctx.fillStyle = '#f5f5f5';
    ctx.fillRect(0, 0, w, h);

    ctx.strokeStyle = '#e0e0e0';
    ctx.lineWidth = 1;
    for (var i = 0; i <= 5; i++) {
        var y = padding.top + chartH - (chartH * i / 5);
        ctx.beginPath();
        ctx.moveTo(padding.left, y);
        ctx.lineTo(w - padding.right, y);
        ctx.stroke();
        ctx.fillStyle = '#999';
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxVal * i / 5), padding.left - 5, y + 3);
    }

    for (var i = 0; i < labels.length; i++) {
        var x = padding.left + gap * i + (gap - barW) / 2;
        var barH = (data[i] / maxVal) * chartH;
        var y = padding.top + chartH - barH;

        ctx.fillStyle = color || COLORS[i % COLORS.length];
        ctx.beginPath();
        ctx.roundRect(x, y, barW, barH, [3, 3, 0, 0]);
        ctx.fill();

        ctx.fillStyle = '#333';
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(data[i], x + barW / 2, y - 5);

        ctx.save();
        ctx.translate(x + barW / 2, padding.top + chartH + 10);
        ctx.rotate(-Math.PI / 4);
        ctx.fillStyle = '#666';
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(labels[i].length > 12 ? labels[i].substr(0, 12) + '...' : labels[i], 0, 0);
        ctx.restore();
    }
}

function drawPieChart(canvasId, labels, data, customColors) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);

    var w = rect.width, h = rect.height;
    var total = data.reduce(function(a, b) { return a + b; }, 0) || 1;
    var cx = w * 0.35, cy = h / 2;
    var r = Math.min(cx - 10, cy - 10, 70);
    var startAngle = -Math.PI / 2;
    var pallete = customColors || COLORS;

    for (var i = 0; i < data.length; i++) {
        var slice = data[i] / total * Math.PI * 2;
        ctx.fillStyle = pallete[i % pallete.length];
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, r, startAngle, startAngle + slice);
        ctx.closePath();
        ctx.fill();
        startAngle += slice;
    }

    var legendX = w * 0.65;
    var legendY = 10;
    ctx.font = '11px sans-serif';
    for (var i = 0; i < labels.length; i++) {
        ctx.fillStyle = pallete[i % pallete.length];
        ctx.fillRect(legendX, legendY + i * 18, 10, 10);
        ctx.fillStyle = '#333';
        ctx.fillText(labels[i] + ' (' + data[i] + ')', legendX + 14, legendY + i * 18 + 9);
    }
}

(function() {
    @if($monthlyStats->isNotEmpty())
    drawBarChart('monthlyChart',
        {!! json_encode($monthlyStats->pluck('month')) !!},
        {!! json_encode($monthlyStats->pluck('total')) !!},
        '#4A7C2E'
    );
    @endif

    @if($officeStats->isNotEmpty())
    drawBarChart('officeChart',
        {!! json_encode($officeStats->map(function($s) { return optional($s->office)->name ?? 'Unassigned'; })->values()) !!},
        {!! json_encode($officeStats->pluck('total')) !!},
        '#2196F3'
    );
    @endif

    @if($categoryStats->isNotEmpty())
    drawBarChart('categoryChart',
        {!! json_encode($categoryStats->pluck('category')) !!},
        {!! json_encode($categoryStats->pluck('total')) !!},
        '#FF9800'
    );
    @endif

    @if($totalDocuments > 0)
    drawPieChart('statusChart',
        ['Pending', 'Received', 'Processed'],
        [{{ $pendingCount }}, {{ $receivedCount }}, {{ $processedCount }}]
    );
    @endif

    @if($priorityStats->isNotEmpty())
    drawPieChart('priorityChart',
        {!! json_encode($priorityStats->pluck('priority')->map(function($p) { return ucfirst($p); })) !!},
        {!! json_encode($priorityStats->pluck('total')) !!},
        {!! json_encode($priorityStats->pluck('priority')->map(function($p) { $colors = ['low'=>'#6c757d','normal'=>'#1976D2','high'=>'#F57C00','urgent'=>'#e74c3c']; return $colors[$p] ?? '#6c757d'; })) !!}
    );
    @endif

    @if($typeStats->isNotEmpty())
    drawPieChart('typeChart',
        {!! json_encode($typeStats->pluck('type')->map(function($t) { return ucfirst($t); })) !!},
        {!! json_encode($typeStats->pluck('total')) !!}
    );
    @endif

    @if($communicationStats->isNotEmpty())
    drawPieChart('commChart',
        {!! json_encode($communicationStats->pluck('communication_type')->map(function($c) { return ucfirst($c); })) !!},
        {!! json_encode($communicationStats->pluck('total')) !!}
    );
    @endif
})();
</script>
@endpush

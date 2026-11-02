@extends('layouts.navigation')
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
    #dMap{height:380px;border-radius:var(--radius);border:1px solid var(--border);z-index:1}
    .tl{position:relative;padding-left:20px}
    .tl .ev{position:relative;padding:0 0 14px 10px;border-left:2px solid var(--border);font-size:13px}
    .tl .ev:before{content:"";position:absolute;left:-6px;top:3px;width:10px;height:10px;border-radius:50%;background:var(--primary)}
    .tl .ev .w{color:var(--muted);font-size:11.5px}
    .proof-img{width:100%;max-height:420px;object-fit:cover;border-radius:12px;border:1px solid var(--border)}
    .prog{display:flex;gap:6px;flex-wrap:wrap}
    .prog span{padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600;background:var(--surface-2);border:1px solid var(--border);color:var(--muted)}
    .prog span.on{background:#dcfce7;border-color:#86efac;color:#166534}
</style>
@endpush
@section('content')
@php
    $stagePill = ['ready' => 'pill-ready', 'on_the_way' => 'pill-way', 'awaiting_payment' => 'pill-pay', 'delivered' => 'pill-done'];
    $symbol = $delivery->currency === 'USD' ? '$' : '₱';
    $cfg = [
        'live' => route('admin.deliveries.live', $delivery),
        'trail' => $trail->map(fn ($p) => [$p->lat, $p->lng])->values()->all(),
        'last' => $delivery->last_lat !== null ? [$delivery->last_lat, $delivery->last_lng] : null,
        'proof' => $delivery->proof_lat !== null ? [$delivery->proof_lat, $delivery->proof_lng] : null,
    ];
@endphp
<a href="{{ route('admin.deliveries.index') }}" class="small text-decoration-none">← All deliveries</a>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2 mb-3">
    <div class="page-title mb-0">{{ $delivery->reference }} <span class="pill {{ $stagePill[$delivery->stage] }}" id="stagePill">{{ $delivery->stage_label }}</span></div>
    <span class="text-muted small" id="seen">@if($delivery->last_seen_at)Last location {{ $delivery->last_seen_at->diffForHumans() }}@endif</span>
</div>

@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

<div class="prog mb-3">
    <span class="on">Dispatched</span>
    <span class="{{ $delivery->trip_started_at ? 'on' : '' }}">Trip started</span>
    <span class="{{ $delivery->scanned_at ? 'on' : '' }}">Item QR scanned</span>
    <span class="{{ $delivery->proof_photo_path ? 'on' : '' }}">Photo + GPS proof</span>
    <span class="{{ $delivery->status === 'delivered' ? 'on' : '' }}">Delivered</span>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-white p-2"><div id="dMap"></div></div>

        <div class="card-white">
            <h6>Proof of delivery</h6>
            @if($delivery->proof_photo_path)
                <img class="proof-img" src="{{ route('admin.deliveries.proof', $delivery) }}" alt="Proof photo">
                <div class="small mt-2">
                    Scanned code: <strong>{{ $delivery->scanned_code }}</strong> · {{ $delivery->proof_taken_at?->timezone($systemSettings['timezone'])->format('M j, Y g:i A') }}
                    @if($delivery->proof_lat !== null)
                        <br>📍 {{ number_format($delivery->proof_lat, 5) }}, {{ number_format($delivery->proof_lng, 5) }}@if($delivery->proof_accuracy) (±{{ round($delivery->proof_accuracy) }} m)@endif
                        · <a target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q={{ $delivery->proof_lat }},{{ $delivery->proof_lng }}">Open in Maps ↗</a>
                    @endif
                </div>
            @else
                <div class="text-muted small">No proof yet. The driver must scan the item QR, take a photo and confirm their location.</div>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-white">
            <h6>Details</h6>
            <table><tbody>
                <tr><td class="text-muted">Item</td><td>{{ $delivery->item->name }} ({{ $delivery->item->code }})</td></tr>
                <tr><td class="text-muted">Quantity</td><td>{{ $delivery->qty }} {{ $delivery->item->unit }}</td></tr>
                <tr><td class="text-muted">Destination</td><td>{{ $delivery->destination }}</td></tr>
                <tr><td class="text-muted">Scheduled</td><td>{{ $delivery->scheduled_at->format('M j, Y g:i A') }}</td></tr>
                <tr><td class="text-muted">Vehicle</td><td>{{ $delivery->vehicle ?: '—' }}</td></tr>
                <tr><td class="text-muted">Payment</td><td>{{ $symbol }}{{ number_format((float) $delivery->amount, 2) }} · {{ $delivery->payment_method === 'paymongo' ? 'Online' : 'COD' }} · {{ ucfirst($delivery->payment_status) }}</td></tr>
            </tbody></table>
        </div>

        <div class="card-white">
            <h6>Driver</h6>
            <div class="mb-2">{{ $delivery->assignee?->name ?? $delivery->driver ?? 'Not assigned' }}@if($delivery->assignee?->phone) · {{ $delivery->assignee->phone }}@endif</div>
            @if($delivery->status !== 'delivered')
                <form method="POST" action="{{ route('admin.deliveries.assign', $delivery) }}" class="d-flex gap-2">
                    @csrf
                    <select name="driver_id" class="form-select" required>
                        <option value="">Choose driver…</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected($delivery->assigned_to === $driver->id)>{{ $driver->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn-blue" type="submit">{{ $delivery->assigned_to ? 'Reassign' : 'Assign' }}</button>
                </form>
            @endif
        </div>

        <div class="card-white">
            <h6>Timeline</h6>
            <div class="tl" id="timeline">
                @foreach($events as $e)
                    <div class="ev">{{ $e->message }}<div class="w">{{ $e->user?->name ?? 'System' }} · {{ $e->created_at->timezone($systemSettings['timezone'])->format('M j, g:i:s A') }}</div></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const CFG = @json($cfg);
    const esc = WL.esc;
    const map = L.map('dMap').setView(CFG.last || CFG.proof || [14.5995, 120.9842], CFG.last || CFG.proof ? 15 : 11);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

    const trail = L.polyline(CFG.trail, { color: '#2563eb', weight: 4, opacity: .75 }).addTo(map);
    let current = CFG.last ? L.circleMarker(CFG.last, { radius: 9, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1 }).addTo(map).bindTooltip('Driver') : null;
    if (CFG.proof) {
        L.circleMarker(CFG.proof, { radius: 9, color: '#fff', weight: 3, fillColor: '#16a34a', fillOpacity: 1 }).addTo(map).bindTooltip('Proof location');
    }
    if (CFG.trail.length > 1) { map.fitBounds(trail.getBounds(), { padding: [30, 30], maxZoom: 16 }); }

    const done = @json($delivery->status === 'delivered');
    const hadProof = @json($delivery->proof_photo_path !== null);
    if (done) return;

    WL.poll(CFG.live, function (data) {
        const d = data.delivery;
        const pill = { on_the_way: 'pill-way', ready: 'pill-ready', awaiting_payment: 'pill-pay', delivered: 'pill-done' }[d.stage];
        const el = document.getElementById('stagePill');
        el.className = 'pill ' + pill;
        el.textContent = d.stage_label;
        document.getElementById('seen').textContent = d.last_seen_human ? 'Last location ' + d.last_seen_human : '';
        trail.setLatLngs(data.trail);
        if (d.lat !== null) {
            if (current) { current.setLatLng([d.lat, d.lng]); }
            else { current = L.circleMarker([d.lat, d.lng], { radius: 9, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1 }).addTo(map).bindTooltip('Driver'); }
        }
        document.getElementById('timeline').innerHTML = data.events.map(function (e) {
            return '<div class="ev">' + esc(e.message) + '<div class="w">' + esc(e.user || 'System') + ' · ' + esc(e.human || '') + '</div></div>';
        }).join('');
        if (d.stage === 'delivered' || (d.has_proof && !hadProof)) { window.location.reload(); }
    }, { interval: 4000 });
})();
</script>
@endpush

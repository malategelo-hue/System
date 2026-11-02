@extends('layouts.navigation')
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
    #liveMap{height:560px;border-radius:var(--radius);border:1px solid var(--border);z-index:1}
    .conn{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:600;padding:5px 11px;border-radius:999px;background:#dcfce7;color:#166534}
    .conn.bad{background:#fee2e2;color:#991b1b}
    .lrow{display:block;padding:11px 12px;border:1px solid var(--border);border-radius:12px;margin-bottom:8px;cursor:pointer;background:var(--surface)}
    .lrow:hover{border-color:#93c5fd}
    .lrow .t{display:flex;justify-content:space-between;gap:8px;font-weight:600;font-size:13px}
    .lrow .s{font-size:12px;color:var(--muted);margin-top:3px}
    .feed{max-height:300px;overflow-y:auto}
    .feed .ev{padding:9px 0;border-bottom:1px solid var(--border);font-size:12.5px}
    .feed .ev .w{color:var(--muted);font-size:11px}
    .mk{width:26px;height:26px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4)}
    .mk.way{background:#2563eb}.mk.ready{background:#f59e0b}.mk.pay{background:#a855f7}.mk.done{background:#16a34a}.mk.stale{filter:grayscale(1);opacity:.7}
    .scroll-list{max-height:260px;overflow-y:auto}
</style>
@endpush
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="page-title mb-0">📍 Live Tracking</div>
    <span class="conn" id="conn">● Connecting…</span>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="label">Ready for pickup</div><div class="num" id="cReady" style="color:#d97706">{{ $snapshot['counts']['ready'] }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="label">On the way</div><div class="num" id="cWay" style="color:#2563eb">{{ $snapshot['counts']['on_the_way'] }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="label">Awaiting payment</div><div class="num" id="cPay" style="color:#7e22ce">{{ $snapshot['counts']['awaiting_payment'] }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="label">Delivered today</div><div class="num" id="cDone" style="color:#16a34a">{{ $snapshot['counts']['delivered_today'] }}</div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div id="liveMap"></div>
        <div class="small text-muted mt-2">Pins update every few seconds while drivers share their location. Grey pins mean no signal for over 2 minutes.</div>
    </div>
    <div class="col-lg-4">
        <div class="card-white">
            <h6>Track an item</h6>
            <div class="input-group mb-2">
                <input type="text" id="itemCode" class="form-control" placeholder="Item code, e.g. ITM-00001">
                <button type="button" class="btn-blue" id="itemGo">Track</button>
            </div>
            <button type="button" class="btn-ghost w-100" id="itemScan">Scan item QR</button>
            <div id="itemScanner" hidden class="mt-2"></div>
            <div id="itemResult" class="mt-3 small"></div>
        </div>
        <div class="card-white">
            <h6>Active deliveries</h6>
            <div id="activeList" class="scroll-list"><div class="text-muted small">Loading…</div></div>
        </div>
        <div class="card-white mb-0">
            <h6>Activity</h6>
            <div id="feed" class="feed"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
@php $cfg = ['live' => route('tracking.live'), 'item' => route('tracking.item'), 'initial' => $snapshot]; @endphp
<script>
(function () {
    const CFG = @json($cfg);
    const $ = function (id) { return document.getElementById(id); };
    const esc = WL.esc;

    const map = L.map('liveMap').setView([14.5995, 120.9842], 11);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

    const markers = {};
    let fitted = false;
    const cls = { on_the_way: 'way', ready: 'ready', awaiting_payment: 'pay', delivered: 'done' };

    function icon(d) {
        return L.divIcon({ className: '', html: '<div class="mk ' + (cls[d.stage] || 'way') + (d.stale && d.stage !== 'delivered' ? ' stale' : '') + '"></div>', iconSize: [26, 26], iconAnchor: [13, 26] });
    }

    function popup(d) {
        return '<strong>' + esc(d.reference) + '</strong><br>' + esc(d.item) + ' × ' + esc(d.qty) + '<br>' +
            '→ ' + esc(d.destination) + '<br>Driver: ' + esc(d.driver || '—') + '<br>' + esc(d.stage_label) +
            (d.last_seen_human ? ' · seen ' + esc(d.last_seen_human) : '') +
            '<br><a href="' + esc(d.url) + '">Open delivery</a>';
    }

    function render(data) {
        $('cReady').textContent = data.counts.ready;
        $('cWay').textContent = data.counts.on_the_way;
        $('cPay').textContent = data.counts.awaiting_payment;
        $('cDone').textContent = data.counts.delivered_today;

        const seen = {};
        const bounds = [];
        data.deliveries.forEach(function (d) {
            const lat = d.stage === 'delivered' && d.proof_lat !== null ? d.proof_lat : d.lat;
            const lng = d.stage === 'delivered' && d.proof_lng !== null ? d.proof_lng : d.lng;
            if (lat === null || lng === null) return;
            seen[d.id] = true;
            bounds.push([lat, lng]);
            if (markers[d.id]) {
                markers[d.id].setLatLng([lat, lng]).setIcon(icon(d)).setPopupContent(popup(d));
            } else {
                markers[d.id] = L.marker([lat, lng], { icon: icon(d) }).addTo(map).bindPopup(popup(d));
            }
        });
        Object.keys(markers).forEach(function (id) {
            if (!seen[id]) { map.removeLayer(markers[id]); delete markers[id]; }
        });
        if (!fitted && bounds.length) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
            fitted = true;
        }

        const active = data.deliveries.filter(function (d) { return d.stage !== 'delivered'; });
        $('activeList').innerHTML = active.length ? active.map(function (d) {
            const pill = { on_the_way: 'pill-way', ready: 'pill-ready', awaiting_payment: 'pill-pay' }[d.stage] || 'pill-muted';
            const where = d.lat !== null ? (d.stale ? 'No signal · ' : 'Live · ') + esc(d.last_seen_human || '') : 'No location yet';
            return '<div class="lrow" data-id="' + d.id + '"><div class="t"><span>' + esc(d.reference) + '</span><span class="pill ' + pill + '">' + esc(d.stage_label) + '</span></div>' +
                '<div class="s">' + esc(d.item) + ' × ' + esc(d.qty) + ' → ' + esc(d.destination) + '</div>' +
                '<div class="s">🚚 ' + esc(d.driver || 'Unassigned') + ' · ' + where + '</div></div>';
        }).join('') : '<div class="text-muted small">No active deliveries.</div>';

        $('feed').innerHTML = data.events.length ? data.events.map(function (e) {
            return '<div class="ev">' + esc(e.message) + '<div class="w">' + esc(e.user || 'System') + ' · ' + esc(e.human || '') + '</div></div>';
        }).join('') : '<div class="text-muted small">No activity yet.</div>';
    }

    $('activeList').addEventListener('click', function (e) {
        const row = e.target.closest('.lrow');
        if (!row) return;
        const m = markers[row.dataset.id];
        if (m) { map.setView(m.getLatLng(), 16); m.openPopup(); }
        else { window.location.href = '/deliveries/' + row.dataset.id; }
    });

    render(CFG.initial);
    WL.poll(CFG.live, render, {
        interval: 4000,
        onState: function (s) {
            const el = $('conn');
            el.className = 'conn' + (s === 'live' ? '' : ' bad');
            el.textContent = { live: '● Live', offline: '● Offline · retrying', error: '● Server error', session: '● Session expired · reload' }[s] || s;
        },
    });

    /* ---------- Item trace ---------- */
    async function trace(code) {
        const out = $('itemResult');
        code = WL.parseCode(code);
        if (!code) return;
        out.textContent = 'Looking up ' + code + '…';
        try {
            const r = await fetch(CFG.item + '?code=' + encodeURIComponent(code), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const j = await r.json();
            if (!r.ok) { out.innerHTML = '<span class="text-danger">' + esc(j.message || 'Not found') + '</span>'; return; }
            out.innerHTML = '<div class="fw-semibold">' + esc(j.item.name) + ' <span class="text-muted">' + esc(j.item.code) + '</span></div>' +
                '<div class="text-muted">' + esc(j.item.location) + ' · ' + esc(j.item.category) + '</div>' +
                '<div class="d-flex gap-2 my-2 flex-wrap"><span class="pill pill-done">In warehouse: ' + j.item.on_hand + '</span>' +
                '<span class="pill pill-way">In transit: ' + j.in_transit_qty + '</span><span class="pill pill-muted">Delivered: ' + j.delivered_qty + '</span></div>' +
                (j.active.length ? j.active.map(function (d) { return '<div>🚚 <a href="' + esc(d.url) + '">' + esc(d.reference) + '</a> · ' + esc(d.stage_label) + ' · ' + esc(d.driver || 'Unassigned') + '</div>'; }).join('') : '') +
                '<div class="mt-2 fw-semibold">Timeline</div><div class="feed" style="max-height:200px">' +
                (j.events.length ? j.events.map(function (e) { return '<div class="ev">' + esc(e.message) + '<div class="w">' + esc(e.human || '') + '</div></div>'; }).join('') : '<div class="text-muted">No events yet.</div>') + '</div>';
        } catch (err) {
            out.innerHTML = '<span class="text-danger">Could not reach the server.</span>';
        }
    }

    $('itemGo').addEventListener('click', function () { trace($('itemCode').value); });
    $('itemCode').addEventListener('keydown', function (e) { if (e.key === 'Enter') trace(this.value); });

    let sc = null;
    $('itemScan').addEventListener('click', async function () {
        const box = $('itemScanner');
        if (sc) { await WL.stopScan(sc); sc = null; box.hidden = true; return; }
        box.hidden = false;
        sc = await WL.startScan('itemScanner', function (code) {
            sc = null; box.hidden = true; $('itemCode').value = code; trace(code);
        }, function (msg) { sc = null; box.hidden = true; $('itemResult').textContent = msg; });
    });
})();
</script>
@endpush

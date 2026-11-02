@extends('layouts.navigation')
@push('head')
<style>
    .dgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px}
    .dcard{display:block;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);text-decoration:none;color:var(--text);transition:transform .08s,border-color .15s}
    .dcard:hover{border-color:#93c5fd;color:var(--text)}
    .dcard.hit{outline:3px solid #f59e0b}
    .dcard .top{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px}
    .dcard .ref{font-weight:700;font-size:13px}
    .dcard .what{font-size:15px;font-weight:600;line-height:1.3}
    .dcard .code{font-size:11.5px;color:var(--muted);margin-top:2px}
    .dcard .row2{display:flex;gap:8px;align-items:flex-start;color:var(--muted);font-size:13px;margin-top:10px;line-height:1.35}
    .dcard .foot{display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding-top:12px;border-top:1px dashed var(--border);font-size:12.5px}
    .scan-card{display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between}
    #quickScanner{max-width:340px;width:100%}
    .empty{text-align:center;color:var(--muted);padding:34px 10px}
</style>
@endpush
@section('content')
@php
    $stagePill = ['ready' => 'pill-ready', 'on_the_way' => 'pill-way', 'awaiting_payment' => 'pill-pay', 'delivered' => 'pill-done'];
    $money = fn ($d) => ($d->currency === 'USD' ? '$' : '₱').number_format((float) $d->amount, $systemSettings['show_decimals'] === '1' ? 2 : 0);
@endphp

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="page-title mb-0">🚚 My Deliveries</div>
</div>

@if($assignedWaitingCount > 0)
    <div class="alert alert-primary" role="status">
        <strong>May {{ $assignedWaitingCount }} delivery na naka-assign sa iyo.</strong>
        Tingnan ang “Assigned to me” sa ibaba para sa detalye at mga susunod na hakbang.
    </div>
@endif
<div id="assignmentLiveNotice"
     data-url="{{ route('logistics.notifications') }}"
     data-known-deliveries="{{ $mine->pluck('id')->toJson() }}"
     aria-live="polite"></div>

@if(session('error'))
    <div class="alert alert-warning" role="alert">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-4"><div class="stat-card"><div class="label">To deliver</div><div class="num" style="color:#2563eb">{{ $pendingOrders }}</div></div></div>
    <div class="col-4"><div class="stat-card"><div class="label">Delivered</div><div class="num" style="color:#16a34a">{{ $deliveredOrders }}</div></div></div>
    <div class="col-4"><div class="stat-card"><div class="label">Unassigned</div><div class="num" style="color:#d97706">{{ $unassigned->count() }}</div></div></div>
</div>

<div class="card-white">
    <div class="scan-card">
        <div>
            <h6 class="mb-1">Scan an item QR to open its delivery</h6>
            <div class="text-muted small">At the pickup or drop-off point, scan the label on the item and your matching delivery opens.</div>
        </div>
        <button type="button" id="quickScanBtn" class="btn-blue">Scan item QR</button>
    </div>
    <div id="quickScanner" class="mt-3" hidden></div>
    <div id="quickScanStatus" class="small text-muted mt-2" role="status"></div>
</div>

<h6 class="mb-2 mt-4">Assigned to me <span class="badge text-bg-primary">{{ $mine->count() }}</span></h6>
<div class="dgrid mb-4" id="mineGrid">
    @forelse($mine as $d)
        <a class="dcard" href="{{ route('logistics.show', $d) }}" data-item-code="{{ $d->item->code }}">
            <div class="top">
                <span class="ref">{{ $d->reference }}</span>
                <span class="pill {{ $stagePill[$d->stage] }}">{{ $d->stage_label }}</span>
            </div>
            <div class="what">{{ $d->item->name }} · {{ $d->qty }} {{ $d->item->unit }}</div>
            <div class="code">{{ $d->item->code }}</div>
            <div class="row2"><span>📍</span><span>{{ $d->destination }}</span></div>
            <div class="foot">
                <span class="text-muted">🕒 {{ $d->scheduled_at->format('M j, g:i A') }}</span>
                <span><strong>{{ $money($d) }}</strong> · {{ $d->payment_method === 'paymongo' ? 'Online' : 'COD' }}</span>
            </div>
        </a>
    @empty
        <div class="card-white empty" style="grid-column:1/-1;margin:0">Nothing assigned to you right now. New dispatches from the admin show up here automatically.</div>
    @endforelse
</div>

@if($unassigned->isNotEmpty())
    <h6 class="mb-2">Unassigned · available to claim</h6>
    <div class="dgrid mb-4">
        @foreach($unassigned as $d)
            <div class="dcard">
                <div class="top">
                    <span class="ref">{{ $d->reference }}</span>
                    <span class="pill pill-muted">Unassigned</span>
                </div>
                <div class="what">{{ $d->item->name }} · {{ $d->qty }} {{ $d->item->unit }}</div>
                <div class="code">{{ $d->item->code }}</div>
                <div class="row2"><span>📍</span><span>{{ $d->destination }}</span></div>
                <div class="foot">
                    <span class="text-muted">🕒 {{ $d->scheduled_at->format('M j, g:i A') }}</span>
                    <form method="POST" action="{{ route('logistics.claim', $d) }}">
                        @csrf
                        <button type="submit" class="btn-orange" style="padding:6px 12px">Claim delivery</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="card-white">
    <h6>Recently delivered</h6>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Dispatch</th><th>Item</th><th>Destination</th><th>Delivered</th><th>Payment</th><th>Proof</th></tr></thead>
            <tbody>
                @forelse($completed as $d)
                    <tr>
                        <td>{{ $d->reference }}</td>
                        <td>{{ $d->item->name }} ({{ $d->qty }} {{ $d->item->unit }})</td>
                        <td>{{ $d->destination }}</td>
                        <td>{{ $d->delivered_at?->timezone($systemSettings['timezone'])->format('M j, g:i A') }}</td>
                        <td>{{ $money($d) }} · {{ $d->payment_method === 'paymongo' ? 'Online' : 'COD' }}</td>
                        <td>@if($d->hasProof())<a href="{{ route('logistics.show', $d) }}">View</a>@else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No completed deliveries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const btn = document.getElementById('quickScanBtn');
    const box = document.getElementById('quickScanner');
    const status = document.getElementById('quickScanStatus');
    const assignmentNotice = document.getElementById('assignmentLiveNotice');
    const knownDeliveries = new Set(JSON.parse(assignmentNotice.dataset.knownDeliveries).map(String));
    let scanner = null;

    btn.addEventListener('click', async function () {
        if (scanner) {
            await WL.stopScan(scanner);
            scanner = null;
            box.hidden = true;
            btn.textContent = 'Scan item QR';
            return;
        }
        box.hidden = false;
        btn.textContent = 'Stop scanning';
        status.textContent = 'Point the camera at the item QR label…';
        scanner = await WL.startScan('quickScanner', function (code) {
            scanner = null;
            box.hidden = true;
            btn.textContent = 'Scan item QR';
            const cards = Array.from(document.querySelectorAll('#mineGrid .dcard[data-item-code]'))
                .filter(function (card) { return card.dataset.itemCode.toLowerCase() === code.toLowerCase(); });
            cards.forEach(function (card) { card.classList.add('hit'); });
            if (cards.length === 1) {
                status.textContent = 'Scanned ' + code + '. Opening your delivery…';
                window.location.href = cards[0].href;
            } else if (cards.length > 1) {
                status.textContent = 'Scanned ' + code + '. You have ' + cards.length + ' deliveries for this item. Pick the right destination.';
            } else {
                status.textContent = 'Scanned ' + code + ', but none of your deliveries use this item.';
            }
        }, function (message) {
            scanner = null;
            box.hidden = true;
            btn.textContent = 'Scan item QR';
            status.textContent = message;
        });
    });

    async function checkAssignedDeliveries() {
        if (document.hidden) {
            return;
        }

        try {
            const response = await fetch(assignmentNotice.dataset.url, {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            });
            if (!response.ok) {
                throw new Error('Could not check assigned deliveries (' + response.status + ').');
            }

            const data = await response.json();
            const newDeliveries = data.deliveries.filter(function (delivery) {
                return !knownDeliveries.has(String(delivery.id));
            });
            data.deliveries.forEach(function (delivery) {
                knownDeliveries.add(String(delivery.id));
            });

            if (!newDeliveries.length) {
                return;
            }

            const alert = document.createElement('div');
            alert.className = 'alert alert-primary';
            alert.setAttribute('role', 'status');

            const message = document.createElement('strong');
            message.textContent = 'May bagong delivery na naka-assign sa iyo: ' +
                newDeliveries.map(function (delivery) { return delivery.reference; }).join(', ') + '. ';
            alert.appendChild(message);

            const refresh = document.createElement('a');
            refresh.href = window.location.href;
            refresh.textContent = 'I-refresh ang listahan';
            refresh.className = 'alert-link';
            alert.appendChild(refresh);
            assignmentNotice.replaceChildren(alert);
        } catch (error) {
            console.error(error);
            const alert = document.createElement('div');
            alert.className = 'alert alert-warning';
            alert.setAttribute('role', 'alert');
            alert.textContent = 'Hindi ma-check ang mga bagong naka-assign na delivery. I-refresh ang page para subukan ulit.';
            assignmentNotice.replaceChildren(alert);
        }
    }

    window.setInterval(checkAssignedDeliveries, 20000);
})();
</script>
@endpush

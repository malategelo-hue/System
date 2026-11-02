@extends('layouts.navigation')
@section('content')
<div class="page-title">📥 Stock In</div>
<p style="color:#666;margin-top:-10px;margin-bottom:15px">Enter items received into your warehouse</p>

@if(session('labels_for'))
    @php $lf = session('labels_for'); @endphp
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
        <span>🏷️ Print QR labels for the items you just received.</span>
        <a class="btn-blue" target="_blank" href="{{ route('inventory.labels', ['item' => $lf['item_id'], 'copies' => $lf['qty']]) }}">Print {{ $lf['qty'] }} label(s)</a>
    </div>
@endif

<div class="card-white">
    <form method="POST" action="{{ route('inventory.stockin.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="item_id">Item <button type="button" class="btn-ghost" id="scanItem" style="padding:2px 10px;margin-left:6px">Scan QR</button></label>
                <div id="stockScanner" hidden class="mb-2" style="max-width:320px"></div>
                <div id="stockScanMsg" class="small text-muted"></div>
                <select id="item_id" name="item_id" class="form-select" required>
                    <option value="">Select an item</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>{{ $item->code }} · {{ $item->name }} ({{ $item->qty }} {{ $item->unit }} on hand)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="qty">Quantity received</label>
                <input id="qty" name="qty" type="number" class="form-control" min="1" step="1" value="{{ old('qty') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="supplier">Supplier / source</label>
                <input id="supplier" name="supplier" type="text" class="form-control" maxlength="255" value="{{ old('supplier') }}">
            </div>
        </div>
        @if($errors->any())
            <div class="alert alert-danger mt-3" role="alert">{{ $errors->first() }}</div>
        @endif
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn-green">Add to inventory</button>
            <a href="{{ route('inventory.stockin') }}" class="btn-red text-decoration-none">Cancel</a>
        </div>
    </form>
</div>

<div class="card-white">
    <h6>📋 Stock-in Records</h6>
    <table>
        <thead><tr><th>#</th><th>Item Name</th><th>Quantity</th><th>Supplier</th><th>Date &amp; Time</th></tr></thead>
        <tbody>
            @forelse($movements as $movement)
                <tr><td>{{ $movement->id }}</td><td>{{ $movement->item_name }}</td><td>{{ $movement->qty }}</td><td>{{ $movement->supplier ?: '—' }}</td><td>{{ $movement->created_at->timezone($systemSettings['timezone'])->format($systemSettings['date_format'].' g:i A') }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No stock-in transactions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $movements->links() }}
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    let sc = null;
    const box = document.getElementById('stockScanner');
    const msg = document.getElementById('stockScanMsg');
    document.getElementById('scanItem').addEventListener('click', async function () {
        if (sc) { await WL.stopScan(sc); sc = null; box.hidden = true; return; }
        box.hidden = false;
        sc = await WL.startScan('stockScanner', function (code) {
            sc = null; box.hidden = true;
            const select = document.getElementById('item_id');
            const option = Array.from(select.options).find(function (o) { return o.text.split(' ')[0].toLowerCase() === code.toLowerCase(); });
            if (option) { select.value = option.value; msg.textContent = 'Selected ' + option.text; }
            else { msg.textContent = 'No item found for ' + code + '.'; }
        }, function (m) { sc = null; box.hidden = true; msg.textContent = m; });
    });
})();
</script>
@endpush
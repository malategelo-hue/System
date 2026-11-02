@extends('layouts.navigation')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="page-title mb-0">📦 Inventory Management</div>
    @if(!isset($item))
        <button class="btn-green" data-bs-toggle="modal" data-bs-target="#addModal">+ Add New Item</button>
    @endif
</div>

@php
    $total = $items->count();
    $threshold = (int) $systemSettings['low_stock_threshold'];
    $inStock = $items->where('qty', '>', $threshold)->count();
    $lowStock = $items->whereBetween('qty', [1, $threshold])->count();
    $outStock = $items->where('qty', '<=', 0)->count();
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="stat-card"><div class="label">Total Items</div><div class="num">{{ $total }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="label">In Stock</div><div class="num" style="color:#28a745">{{ $inStock }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="label">Low Stock</div><div class="num" style="color:#ffc107">{{ $lowStock }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="label">Out of Stock</div><div class="num" style="color:#dc3545">{{ $outStock }}</div></div></div>
</div>

@if(session('labels_for'))
    @php $lf = session('labels_for'); @endphp
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
        <span>🏷️ Print QR labels for the new stock so every item can be scanned.</span>
        <a class="btn-blue" target="_blank" href="{{ route('inventory.labels', ['item' => $lf['item_id'], 'copies' => $lf['qty']]) }}">Print {{ $lf['qty'] }} label(s)</a>
    </div>
@endif

{{-- EDIT FORM (kapag may piniling i-edit) --}}
@if(isset($item))
<div class="card-white">
    <h5>✏️ Edit Item: {{ $item->name }}</h5>
    <form method="POST" action="/inventory/update/{{ $item->id }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-4">
                <label for="edit_item_code">Item Code *</label>
                <input id="edit_item_code" type="text" name="code" class="form-control" value="{{ $item->code }}" required data-code-check="{{ route('inventory.code-check') }}" data-except-item="{{ $item->id }}">
                <div class="form-text" data-code-message aria-live="polite"></div>
                @error('code')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4"><label>Item Name *</label><input type="text" name="name" class="form-control" value="{{ $item->name }}" required></div>
            <div class="col-md-4"><label>Category *</label>
                <select name="category" class="form-select" required>
                    <option value="">Choose...</option>
                    <option {{ $item->category=='Raw Materials'?'selected':'' }}>Raw Materials</option>
                    <option {{ $item->category=='Supplies'?'selected':'' }}>Supplies</option>
                    <option {{ $item->category=='Finished Goods'?'selected':'' }}>Finished Goods</option>
                    <option {{ $item->category=='Equipment'?'selected':'' }}>Equipment</option>
                </select>
            </div>
            <div class="col-md-3"><label>Stock Qty *</label><input type="number" name="qty" class="form-control" value="{{ $item->qty }}" required></div>
            <div class="col-md-3"><label>Unit *</label><input type="text" name="unit" class="form-control" value="{{ $item->unit }}" required></div>
            <div class="col-md-3"><label>Unit Price ({{ $systemSettings['currency'] === 'USD' ? '$' : '₱' }}) *</label><input type="number" step="0.01" name="price" class="form-control" value="{{ $item->price }}" required></div>
            <div class="col-md-3"><label>Location *</label>
                <select name="location" class="form-select" required>
                    <option value="">Choose...</option>
                    <option {{ $item->location=='Warehouse A'?'selected':'' }}>Warehouse A</option>
                    <option {{ $item->location=='Warehouse B'?'selected':'' }}>Warehouse B</option>
                    <option {{ $item->location=='Warehouse C'?'selected':'' }}>Warehouse C</option>
                </select>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn-green">💾 Save Changes</button>
            <a href="/inventory" class="btn-red" style="text-decoration:none;padding:8px 16px;border-radius:4px">Cancel</a>
        </div>
    </form>
</div>
@endif

<div class="card-white">
    <table>
        <thead><tr><th>QR</th><th>Item Code</th><th>Item Name</th><th>Category</th><th>Qty</th><th>Unit</th><th>Price</th><th>Location</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($items as $itm)
            <tr>
                {{-- ✅ QR CODE — auto-generated kada item! --}}
                <td>
                    <span data-qr="{{ $itm->qr_payload }}" data-qr-size="52" style="cursor:pointer"
                         data-bs-toggle="modal" data-bs-target="#qrModal{{ $itm->id }}"></span>
                </td>
                <td>{{ $itm->code }}</td>
                <td>{{ $itm->name }}</td>
                <td>{{ $itm->category }}</td>
                <td>{{ $itm->qty }}</td>
                <td>{{ $itm->unit }}</td>
                <td>{{ $systemSettings['currency'] === 'USD' ? '$' : '₱' }}{{ number_format($itm->price, $systemSettings['show_decimals'] === '1' ? 2 : 0) }}</td>
                <td>{{ $itm->location }}</td>
                <td>
                    @if($itm->qty > $threshold) <span class="badge-good">Good</span>
                    @elseif($itm->qty > 0) <span class="badge-low">Low Stock</span>
                    @else <span class="badge-out">Out of Stock</span>
                    @endif
                </td>
                <td>
                    <button type="button" class="btn-ghost" style="padding:4px 10px;margin-right:6px" data-bs-toggle="modal" data-bs-target="#receive{{ $itm->id }}">📥 Receive</button>
                    <a href="/inventory/edit/{{ $itm->id }}" style="margin-right:8px">✏️ Edit</a>
                    <form method="POST" action="/inventory/delete/{{ $itm->id }}" style="display:inline" onsubmit="return confirm('Delete this item?')">
                        @csrf
                        <button type="submit" style="background:none;border:none;color:#dc3545;cursor:pointer">🗑️ Delete</button>
                    </form>
                </td>
            </tr>

            {{-- QR MODAL — malaking QR kada item --}}
            <div class="modal fade" id="qrModal{{ $itm->id }}" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content p-4 text-center">
                        <h5>{{ $itm->name }}</h5>
                        <p class="text-muted">Code: {{ $itm->code }}</p>
                        <div data-qr="{{ $itm->qr_payload }}" data-qr-size="250" style="margin:10px auto"></div>
                        <a class="btn-green mt-2" target="_blank" href="{{ route('inventory.labels', $itm) }}">🖨️ Print labels</a>
                    </div>
                </div>
            </div>
            @empty
            <tr><td colspan="10" style="text-align:center;color:#999;padding:30px">No items yet — click "Add New Item"</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@foreach($items as $itm)
<div class="modal fade" id="receive{{ $itm->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content p-4">
        <h5>📥 Receive stock</h5>
        <p class="text-muted small">{{ $itm->code }} · {{ $itm->name }} (now {{ $itm->qty }} {{ $itm->unit }})</p>
        <form method="POST" action="{{ route('inventory.receive', $itm) }}">
            @csrf
            <div class="mb-3"><label class="form-label">Quantity received</label><input type="number" name="qty" min="1" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Supplier / source</label><input name="supplier" class="form-control" maxlength="255"></div>
            <div class="d-flex justify-content-end gap-2"><button type="button" class="btn-red" data-bs-dismiss="modal">Cancel</button><button class="btn-green" type="submit">Add to inventory</button></div>
        </form>
    </div></div>
</div>
@endforeach

{{-- ADD MODAL --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content p-4">
            <h5 class="mb-3">➕ Add New Item</h5>
            <form method="POST" action="/inventory/add">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="new_item_code">Item Code {{ $systemSettings['auto_codes'] === '1' ? '(optional)' : '*' }}</label>
                        <input id="new_item_code" type="text" name="code" class="form-control" placeholder="{{ $systemSettings['auto_codes'] === '1' ? 'Leave blank to generate' : 'ITM-00001' }}" data-code-check="{{ route('inventory.code-check') }}" @unless($systemSettings['auto_codes'] === '1') required @endunless>
                        <div class="form-text" data-code-message aria-live="polite"></div>
                        @error('code')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6"><label>Item Name *</label><input type="text" name="name" class="form-control" placeholder="Name of product" required></div>
                    <div class="col-md-6"><label>Category *</label>
                        <select name="category" class="form-select" required>
                            <option value="">Choose...</option>
                            <option>Raw Materials</option>
                            <option>Supplies</option>
                            <option>Finished Goods</option>
                            <option>Equipment</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label>Unit of Measure *</label>
                        <select name="unit" class="form-select" required>
                            <option value="">Choose...</option>
                            <option>pcs</option><option>box</option><option>roll</option>
                            <option>kg</option><option>pack</option><option>set</option>
                        </select>
                    </div>
                    <div class="col-md-4"><label>Initial Stock Qty *</label><input type="number" name="qty" class="form-control" value="0" required></div>
                    <div class="col-md-4"><label>Unit Price ({{ $systemSettings['currency'] === 'USD' ? '$' : '₱' }}) *</label><input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required></div>
                    <div class="col-md-4"><label>Warehouse / Location *</label>
                        <select name="location" class="form-select" required>
                            <option value="">Choose...</option>
                            <option>Warehouse A</option>
                            <option>Warehouse B</option>
                            <option>Warehouse C</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2 justify-content-end">
                    <button type="button" class="btn-red" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-green">💾 Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('[data-code-check]').forEach(input => {
    let timer;
    const message = input.parentElement.querySelector('[data-code-message]');
    input.addEventListener('input', () => {
        clearTimeout(timer);
        input.setCustomValidity('');
        message.textContent = '';

        const code = input.value.trim();
        if (!code) return;

        timer = setTimeout(async () => {
            const url = new URL(input.dataset.codeCheck, window.location.origin);
            url.searchParams.set('code', code);
            if (input.dataset.exceptItem) url.searchParams.set('except', input.dataset.exceptItem);

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Code check failed.');
                const result = await response.json();
                if (result.duplicate) {
                    message.textContent = 'This code already exists. Choose a different item code.';
                    message.className = 'form-text text-danger';
                    input.setCustomValidity('This item code already exists.');
                } else if (result.similar.length) {
                    message.textContent = `Similar item codes found: ${result.similar.join(', ')}.`;
                    message.className = 'form-text text-warning';
                } else {
                    message.textContent = 'Item code is available.';
                    message.className = 'form-text text-success';
                }
            } catch {
                message.textContent = 'Could not check for similar codes. The server will still validate duplicates when saved.';
                message.className = 'form-text text-warning';
            }
        }, 250);
    });
});
</script>
@endpush
@endsection
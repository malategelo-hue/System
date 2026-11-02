@extends('layouts.navigation')
@section('content')
<div class="page-title">📤 Stock Out / Issuance</div>
<p style="color:#666;margin-top:-10px;margin-bottom:15px">Release available inventory and record its recipient.</p>

<div class="card-white">
    <form method="POST" action="{{ route('inventory.stockout.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="item_id">Item</label>
                <select id="item_id" name="item_id" class="form-select" required>
                    <option value="">Select an item</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>{{ $item->code }} · {{ $item->name }} ({{ $item->qty }} {{ $item->unit }} available)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="qty">Quantity to release</label>
                <input id="qty" name="qty" type="number" class="form-control" min="1" step="1" value="{{ old('qty') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="issued_to">Issue to / department</label>
                <input id="issued_to" name="issued_to" type="text" class="form-control" maxlength="255" value="{{ old('issued_to') }}" required>
            </div>
        </div>
        @if($errors->any())
            <div class="alert alert-danger mt-3" role="alert">{{ $errors->first() }}</div>
        @endif
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn-green">Confirm stock-out</button>
            <a href="{{ route('inventory.stockout') }}" class="btn-red text-decoration-none">Cancel</a>
        </div>
    </form>
</div>

<div class="card-white">
    <h6>Stock-out Records</h6>
    <div class="table-responsive">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Quantity</th><th>Issued to</th><th>Date</th></tr></thead>
            <tbody>
                @forelse($movements as $movement)
                    <tr><td>{{ $movement->id }}</td><td>{{ $movement->item_name }}</td><td>{{ $movement->qty }}</td><td>{{ $movement->issued_to ?: '—' }}</td><td>{{ $movement->created_at->timezone($systemSettings['timezone'])->format($systemSettings['date_format'].' g:i A') }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No stock-out transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $movements->links() }}
    </div>
</div>
@endsection
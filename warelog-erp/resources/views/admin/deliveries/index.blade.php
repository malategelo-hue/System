@extends('layouts.navigation')
@section('content')
@php
    $stagePill = ['ready' => 'pill-ready', 'on_the_way' => 'pill-way', 'awaiting_payment' => 'pill-pay', 'delivered' => 'pill-done'];
    $tabs = ['all' => 'All', 'active' => 'Active', 'unassigned' => 'Unassigned', 'delivered' => 'Delivered'];
@endphp
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="page-title mb-0">🚚 Deliveries</div>
    <div class="d-flex gap-2">
        <a href="{{ route('tracking.index') }}" class="btn-ghost">📍 Live map</a>
        <button class="btn-green" type="button" data-bs-toggle="modal" data-bs-target="#dispatchModal">+ New Dispatch</button>
    </div>
</div>

@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($tabs as $key => $label)
        <a href="{{ route('admin.deliveries.index', ['stage' => $key]) }}" class="{{ $filter === $key ? 'btn-blue' : 'btn-ghost' }}">{{ $label }} <span class="opacity-75">({{ $counts[$key] }})</span></a>
    @endforeach
</div>

<div class="card-white">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Dispatch</th><th>Item</th><th>Destination</th><th>Driver</th><th>Scheduled</th><th>Amount</th><th>Status</th><th>Proof</th><th></th></tr></thead>
            <tbody>
                @forelse($deliveries as $d)
                    <tr>
                        <td><strong>{{ $d->reference }}</strong></td>
                        <td>{{ $d->item->name }} ({{ $d->qty }} {{ $d->item->unit }})</td>
                        <td>{{ $d->destination }}</td>
                        <td>{{ $d->assignee?->name ?? $d->driver ?? '—' }}@if(! $d->assigned_to && $d->status !== 'delivered') <span class="pill pill-muted">Unassigned</span>@endif</td>
                        <td>{{ $d->scheduled_at->format('M j, g:i A') }}</td>
                        <td>{{ $d->currency === 'USD' ? '$' : '₱' }}{{ number_format((float) $d->amount, 2) }} · {{ $d->payment_method === 'paymongo' ? 'Online' : 'COD' }}</td>
                        <td><span class="pill {{ $stagePill[$d->stage] }}">{{ $d->stage_label }}</span></td>
                        <td>{{ $d->hasProof() ? '📷 ✓' : '—' }}</td>
                        <td><a href="{{ route('admin.deliveries.show', $d) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No deliveries here yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $deliveries->links() }}</div>
</div>

<div class="modal fade" id="dispatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"><div class="modal-content p-4">
        <h5 class="mb-3">Create delivery dispatch</h5>
        <form method="POST" action="{{ route('admin.deliveries.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-7"><label class="form-label" for="d_item">Item</label>
                    <select id="d_item" name="item_id" class="form-select" required>
                        <option value="">Select available inventory</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->code }} · {{ $item->name }} ({{ $item->qty }} {{ $item->unit }} available)</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5"><label class="form-label" for="d_qty">Quantity</label><input id="d_qty" name="qty" type="number" min="1" class="form-control" required></div>
                <div class="col-12"><label class="form-label" for="d_dest">Destination</label><input id="d_dest" name="destination" class="form-control" maxlength="255" required></div>
                <div class="col-md-6"><label class="form-label" for="d_driver">Assign driver</label>
                    <select id="d_driver" name="driver_id" class="form-select">
                        <option value="">Leave unassigned (drivers can claim)</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->name }}{{ $driver->vehicle ? ' · '.$driver->vehicle : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label" for="d_vehicle">Vehicle</label><input id="d_vehicle" name="vehicle" class="form-control" maxlength="120"></div>
                <div class="col-md-6"><label class="form-label" for="d_sched">Delivery schedule</label><input id="d_sched" name="scheduled_at" type="datetime-local" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label" for="d_pay">Payment at delivery</label>
                    <select id="d_pay" name="payment_method" class="form-select" required>
                        <option value="cod">Cash on Delivery (COD)</option>
                        <option value="paymongo">Online payment (PayMongo)</option>
                    </select>
                </div>
            </div>
            <div class="mt-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn-red" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn-green">Dispatch item</button>
            </div>
        </form>
    </div></div>
</div>
@endsection

@extends('layouts.navigation')
@section('content')

<style>
    .report-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        border: 1px solid #e5e7eb;
    }
    .report-card h6 { font-size: 15px; font-weight: 600; margin-bottom: 5px; }
    .report-card p { font-size: 13px; color: #6b7280; margin-bottom: 12px; }
    .view-panel {
        background: #f9fafb;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
        display: none;
    }
    .view-panel.active { display: block; }
    .btn-green { background: #22c55e; color: white; border: none; padding: 8px 16px; border-radius: 4px; font-weight: 500; }
    .btn-gray { background: #e5e7eb; color: #374151; border: none; padding: 8px 16px; border-radius: 4px; font-weight: 500; }
    .btn-blue { background: #3b82f6; color: white; border: none; padding: 6px 14px; border-radius: 4px; }
    .filter-row { display: flex; gap: 12px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
    .filter-row select, .filter-row input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; }
    .arrow-btn { float: right; background: transparent; border: none; font-size: 18px; cursor: pointer; color: #6b7280; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">📋 Reports & Summaries</h5>
</div>

{{-- FILTERS --}}
<div class="filter-row">
    <label>Report Type:</label>
    <select id="reportType">
        <option>All Reports</option>
        <option>Inventory Only</option>
        <option>Transactions Only</option>
        <option>Reorder Only</option>
    </select>
    
    <label>From:</label>
    <input type="date" id="dateFrom">
    
    <label>To:</label>
    <input type="date" id="dateTo">
    
    <button class="btn-green">📊 Export Excel</button>
    <button class="btn-gray" onclick="window.print()">🖨️ Print</button>
</div>

{{-- REPORT CARDS --}}
<div class="row">
    <div class="col-md-6">
        <div class="report-card">
            <button class="arrow-btn" onclick="toggleView('inventory-value')">→</button>
            <h6>📦 Inventory Value</h6>
            <p>Total Value of all goods in the warehouse.</p>
            <button class="btn-blue" onclick="showView('inventory-value')">View</button>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="report-card">
            <button class="arrow-btn" onclick="toggleView('stock-movement')">→</button>
            <h6>📈 Stock In / Out</h6>
            <p>Summary of all incoming and outgoing items.</p>
            <button class="btn-blue" onclick="showView('stock-movement')">View</button>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="report-card">
            <button class="arrow-btn" onclick="toggleView('reorder-list')">→</button>
            <h6>⚠️ Reorder List</h6>
            <p>Items that are running low or need to be purchased.</p>
            <button class="btn-blue" onclick="showView('reorder-list')">View</button>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="report-card">
            <button class="arrow-btn" onclick="toggleView('delivery-summary')">→</button>
            <h6>🚚 Delivery Summary</h6>
            <p>Number of shipments and deliveries on time.</p>
            <button class="btn-blue" onclick="showView('delivery-summary')">View</button>
        </div>
    </div>
</div>

{{-- VIEW PANELS --}}
<div id="inventory-value" class="view-panel">
    <h5>📦 Inventory Value Report</h5>
    <hr>
    <div class="row g-3 mb-4">
        <div class="col-md-4 text-center p-3 bg-white rounded shadow-sm">
            <div class="h4 fw-bold text-success">{{ $systemSettings['currency'] === 'USD' ? '$' : '₱' }}{{ number_format($totalValue ?? 0, $systemSettings['show_decimals'] === '1' ? 2 : 0) }}</div>
            <small class="text-muted">Total Stock Value</small>
        </div>
        <div class="col-md-4 text-center p-3 bg-white rounded shadow-sm">
            <div class="h4 fw-bold text-primary">{{ $totalProducts ?? 0 }}</div>
            <small class="text-muted">Total Items</small>
        </div>
        <div class="col-md-4 text-center p-3 bg-white rounded shadow-sm">
            <div class="h4 fw-bold text-info">{{ $items->sum('qty') ?? 0 }}</div>
            <small class="text-muted">Total Quantity</small>
        </div>
    </div>
    <table class="table table-bordered">
        <thead class="table-light">
            <tr><th>Code</th><th>Item Name</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach($items ?? [] as $item)
            <tr>
                <td>{{ $item->code }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->qty }}</td>
                <td>{{ $systemSettings['currency'] === 'USD' ? '$' : '₱' }}{{ number_format($item->price, $systemSettings['show_decimals'] === '1' ? 2 : 0) }}</td>
                <td>{{ $systemSettings['currency'] === 'USD' ? '$' : '₱' }}{{ number_format($item->qty * $item->price, $systemSettings['show_decimals'] === '1' ? 2 : 0) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div id="stock-movement" class="view-panel">
    <h5>📈 Stock In / Out Summary</h5>
    <hr>
    <div class="row g-3 mb-4">
        <div class="col-md-6 text-center p-3 bg-white rounded shadow-sm">
            <div class="h4 fw-bold text-success">+{{ $totalStockIn ?? 0 }}</div>
            <small class="text-muted">Total Stock In</small>
        </div>
        <div class="col-md-6 text-center p-3 bg-white rounded shadow-sm">
            <div class="h4 fw-bold text-danger">-{{ $totalStockOut ?? 0 }}</div>
            <small class="text-muted">Total Stock Out</small>
        </div>
    </div>
    <table class="table table-striped">
        <thead>
            <tr><th>Date</th><th>Item</th><th>Type</th><th>Qty</th><th>Reference</th></tr>
        </thead>
        <tbody>
            @foreach($movements ?? [] as $m)
            <tr>
                <td>{{ $m->created_at->timezone($systemSettings['timezone'])->format($systemSettings['date_format']) }}</td>
                <td>{{ $m->item_name }}</td>
                <td>
                    <span class="badge bg-{{ $m->type=='in' ? 'success' : 'danger' }}">
                        Stock {{ $m->type=='in' ? 'In' : 'Out' }}
                    </span>
                </td>
                <td>{{ $m->type=='in' ? '+' : '-' }}{{ $m->qty }}</td>
                <td>#{{ $m->id }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div id="reorder-list" class="view-panel">
    <h5>⚠️ Reorder / Low Stock List</h5>
    <p class="text-muted">Items at or below the low-stock threshold ({{ $systemSettings['low_stock_threshold'] }})</p>
    <hr>
    <table class="table table-bordered">
        <thead class="table-warning">
            <tr><th>Code</th><th>Item Name</th><th>Current Stock</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
            @php $lowItems = ($items ?? collect())->where('qty', '<=', (int) $systemSettings['low_stock_threshold']); @endphp
            @forelse($lowItems as $item)
            <tr>
                <td>{{ $item->code }}</td>
                <td>{{ $item->name }}</td>
                <td class="fw-bold text-danger">{{ $item->qty }}</td>
                <td>
                    @if($item->qty <= 0)
                        <span class="badge bg-danger">OUT OF STOCK</span>
                    @else
                        <span class="badge bg-warning text-dark">LOW STOCK</span>
                    @endif
                </td>
                <td><button class="btn btn-sm btn-primary">Order Now</button></td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-success py-3">✅ All items are sufficiently stocked!</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="delivery-summary" class="view-panel">
    <h5>🚚 Delivery Summary</h5>
    <hr>
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="p-3 bg-light rounded text-center">
                <div class="h4 fw-bold text-primary">{{ $deliverySummary['pending'] ?? 0 }}</div>
                <small>Pending Deliveries</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="p-3 bg-light rounded text-center">
                <div class="h4 fw-bold text-success">{{ $deliverySummary['completed'] ?? 0 }}</div>
                <small>Completed Deliveries</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="p-3 bg-light rounded text-center">
                <div class="h4 fw-bold text-info">{{ $deliverySummary['on_time_rate'] ?? 0 }}%</div>
                <small>On-Time Rate</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="p-3 bg-light rounded text-center">
                <div class="h4 fw-bold text-danger">{{ $deliverySummary['late'] ?? 0 }}</div>
                <small>Late Deliveries</small>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr><th>Dispatch</th><th>Item</th><th>Destination</th><th>Scheduled</th><th>Delivered</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse(($deliveries ?? collect())->take(10) as $delivery)
                    <tr>
                        <td>{{ $delivery->reference }}</td>
                        <td>{{ $delivery->item->name }}</td>
                        <td>{{ $delivery->destination }}</td>
                        <td>{{ $delivery->scheduled_at->timezone($systemSettings['timezone'])->format($systemSettings['date_format'].' g:i A') }}</td>
                        <td>{{ $delivery->delivered_at ? $delivery->delivered_at->timezone($systemSettings['timezone'])->format($systemSettings['date_format'].' g:i A') : '—' }}</td>
                        <td>
                            @if($delivery->status === 'in_transit')
                                <span class="badge bg-primary">In transit</span>
                            @elseif($delivery->delivered_at && $delivery->delivered_at->lessThanOrEqualTo($delivery->scheduled_at))
                                <span class="badge bg-success">Delivered on time</span>
                            @else
                                <span class="badge bg-danger">Delivered late</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No delivery records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function showView(id) {
    document.querySelectorAll('.view-panel').forEach(p => p.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.getElementById(id).scrollIntoView({behavior: 'smooth'});
}
function toggleView(id) {
    const panel = document.getElementById(id);
    panel.classList.toggle('active');
}
</script>
@endsection
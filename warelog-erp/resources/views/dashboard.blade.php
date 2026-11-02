@extends('layouts.navigation')
@section('content')

<style>
    .chart-container {
        height: 320px !important;
        width: 100% !important;
        position: relative !important;
    }
    .stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
    }
    .stat-label {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 6px;
    }
    .stat-value {
        font-size: 26px;
        font-weight: 700;
    }
    .badge-role {
        background: #3b82f6;
        color: white;
        padding: 6px 16px;
        border-radius: 20px;
        font-weight: 600;
    }
    .table-sm td, .table-sm th {
        padding: 10px 12px;
        font-size: 14px;
    }
    h5 { font-size: 16px; font-weight: 600; margin-bottom: 15px; }
</style>

{{-- PAGE HEADER --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">📊 Dashboard Overview</h4>
    <div class="d-flex align-items-center gap-2">
        <span class="badge-role">{{ strtoupper($role) }}</span>
    </div>
</div>

{{-- LIVE DELIVERIES --}}
<div class="hero-banner">
    <div>
        <strong>Live deliveries</strong>
        <div class="small" style="opacity:.8">{{ $liveCounts['on_the_way'] }} on the way · {{ $liveCounts['ready'] }} ready for pickup · {{ $liveCounts['unassigned'] }} unassigned</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('tracking.index') }}" class="btn-ghost">📍 Open live map</a>
        <a href="{{ route('admin.deliveries.index') }}" class="btn-ghost">🚚 Deliveries</a>
    </div>
</div>

{{-- SUCCESS ALERT --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ✅ STATISTIC CARDS --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Total Stock Value</div>
            <div id="totalStockValue" class="stat-value" data-value-url="{{ route('dashboard.total-stock-value') }}" style="color: #22c55e;">{{ $systemSettings['currency'] === 'USD' ? '$' : '₱' }}{{ number_format($totalValue, $systemSettings['show_decimals'] === '1' ? 2 : 0) }}</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-value" style="color: #eab308;">{{ $lowStock ?? 0 }} Items</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Pending Orders</div>
            <div class="stat-value" style="color: #f97316;">{{ $pendingOrders ?? 0 }}</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Total Products</div>
            <div class="stat-value" style="color: #3b82f6;">{{ $totalProducts ?? 0 }} Items</div>
        </div>
    </div>
</div>

@if($systemSettings['low_stock_alerts'] === '1' && $lowStock > 0)
    <div class="alert alert-warning" role="status">
        {{ $lowStock }} item(s) are at or below the low-stock threshold of {{ $systemSettings['low_stock_threshold'] }}.
    </div>
@endif

{{-- 📈 CHART — PAREHONG NAKIKITA ANG STOCK IN & OUT --}}
<div class="row g-3">
    <div class="col-md-7">
        <div class="stat-card">
            <h5 class="mb-2">Stock Movement (Last 6 Months)</h5>
            <div class="chart-container">
                <canvas id="stockChart"></canvas>
            </div>
        </div>
    </div>

    {{-- RECENT TRANSACTIONS --}}
    <div class="col-md-5">
        <div class="stat-card">
            <h5 class="mb-3">Recent Warehouse Transactions</h5>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Type</th>
                        <th>Date &amp; Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentMovements ?? [] as $r)
                    <tr>
                        <td class="text-muted">#{{ $r->id }}</td>
                        <td>{{ $r->item_name }}</td>
                        <td class="fw-bold">{{ $r->qty }}</td>
                        <td>
                            <span class="badge bg-{{ $r->type === 'in' ? 'success' : 'danger' }}">
                                Stock {{ $r->type === 'in' ? 'In' : 'Out' }}
                            </span>
                        </td>
                        <td>{{ $r->created_at->timezone($systemSettings['timezone'])->format($systemSettings['date_format'].' g:i A') }}</td>
                        <td>
                            @if(($r->status ?? 'completed') === 'completed')
                                <span class="text-success">✅ Completed</span>
                            @else
                                <span class="text-warning">⏳ Pending</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No transactions yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- CHART — PAREHONG NAKIKITA ANG STOCK IN & OUT --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
<script>
window.addEventListener('load', function() {
    const ctx = document.getElementById('stockChart');
    if (!ctx) return;

    const months = {!! json_encode($months ?? ['Apr','May','Jun','Jul','Aug','Sep']) !!};
    const stockInData = {!! json_encode($stockInData ?? [0,0,0,0,0,0]) !!};
    const stockOutData = {!! json_encode($stockOutData ?? [0,0,0,0,0,0]) !!};

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Stock In',
                    data: stockInData,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.15)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: '#22c55e',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                },
                {
                    label: 'Stock Out',
                    data: stockOutData,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.15)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                    // ✅ WALANG hidden — NAKIKITA NG LAHAT
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { size: 13, weight: 'bold' },
                        padding: 10,
                        boxWidth: 15,
                        usePointStyle: true
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#e5e7eb' },
                    ticks: { stepSize: 500 }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
});

const totalStockValue = document.getElementById('totalStockValue');
if (totalStockValue) {
    window.setInterval(async () => {
        if (document.hidden) return;

        try {
            const response = await fetch(totalStockValue.dataset.valueUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(`Stock value refresh failed with status ${response.status}.`);
            }

            const data = await response.json();
            totalStockValue.textContent = data.display;
        } catch (error) {
            console.error('Unable to refresh total stock value.', error);
        }
    }, 3000);
}
</script>
@endsection
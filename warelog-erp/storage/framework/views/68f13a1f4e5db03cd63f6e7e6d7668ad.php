<?php $__env->startSection('content'); ?>

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


<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">📊 Dashboard Overview</h4>
    <div class="d-flex align-items-center gap-2">
        <span class="badge-role"><?php echo e(strtoupper($role)); ?></span>
    </div>
</div>


<div class="hero-banner">
    <div>
        <strong>Live deliveries</strong>
        <div class="small" style="opacity:.8"><?php echo e($liveCounts['on_the_way']); ?> on the way · <?php echo e($liveCounts['ready']); ?> ready for pickup · <?php echo e($liveCounts['unassigned']); ?> unassigned</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo e(route('tracking.index')); ?>" class="btn-ghost">📍 Open live map</a>
        <a href="<?php echo e(route('admin.deliveries.index')); ?>" class="btn-ghost">🚚 Deliveries</a>
    </div>
</div>


<?php if(session('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ <?php echo e(session('success')); ?>

        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>


<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Total Stock Value</div>
            <div id="totalStockValue" class="stat-value" data-value-url="<?php echo e(route('dashboard.total-stock-value')); ?>" style="color: #22c55e;"><?php echo e($systemSettings['currency'] === 'USD' ? '$' : '₱'); ?><?php echo e(number_format($totalValue, $systemSettings['show_decimals'] === '1' ? 2 : 0)); ?></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-value" style="color: #eab308;"><?php echo e($lowStock ?? 0); ?> Items</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Pending Orders</div>
            <div class="stat-value" style="color: #f97316;"><?php echo e($pendingOrders ?? 0); ?></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-label">Total Products</div>
            <div class="stat-value" style="color: #3b82f6;"><?php echo e($totalProducts ?? 0); ?> Items</div>
        </div>
    </div>
</div>

<?php if($systemSettings['low_stock_alerts'] === '1' && $lowStock > 0): ?>
    <div class="alert alert-warning" role="status">
        <?php echo e($lowStock); ?> item(s) are at or below the low-stock threshold of <?php echo e($systemSettings['low_stock_threshold']); ?>.
    </div>
<?php endif; ?>


<div class="row g-3">
    <div class="col-md-7">
        <div class="stat-card">
            <h5 class="mb-2">Stock Movement (Last 6 Months)</h5>
            <div class="chart-container">
                <canvas id="stockChart"></canvas>
            </div>
        </div>
    </div>

    
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
                    <?php $__empty_1 = true; $__currentLoopData = $recentMovements ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td class="text-muted">#<?php echo e($r->id); ?></td>
                        <td><?php echo e($r->item_name); ?></td>
                        <td class="fw-bold"><?php echo e($r->qty); ?></td>
                        <td>
                            <span class="badge bg-<?php echo e($r->type === 'in' ? 'success' : 'danger'); ?>">
                                Stock <?php echo e($r->type === 'in' ? 'In' : 'Out'); ?>

                            </span>
                        </td>
                        <td><?php echo e($r->created_at->timezone($systemSettings['timezone'])->format($systemSettings['date_format'].' g:i A')); ?></td>
                        <td>
                            <?php if(($r->status ?? 'completed') === 'completed'): ?>
                                <span class="text-success">✅ Completed</span>
                            <?php else: ?>
                                <span class="text-warning">⏳ Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No transactions yet</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
<script>
window.addEventListener('load', function() {
    const ctx = document.getElementById('stockChart');
    if (!ctx) return;

    const months = <?php echo json_encode($months ?? ['Apr','May','Jun','Jul','Aug','Sep']); ?>;
    const stockInData = <?php echo json_encode($stockInData ?? [0,0,0,0,0,0]); ?>;
    const stockOutData = <?php echo json_encode($stockOutData ?? [0,0,0,0,0,0]); ?>;

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
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\gelom\Desktop\System\warelog-erp\resources\views/dashboard.blade.php ENDPATH**/ ?>
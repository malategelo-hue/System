<?php $__env->startPush('head'); ?>
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
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<?php
    $stagePill = ['ready' => 'pill-ready', 'on_the_way' => 'pill-way', 'awaiting_payment' => 'pill-pay', 'delivered' => 'pill-done'];
    $symbol = $delivery->currency === 'USD' ? '$' : '₱';
    $cfg = [
        'live' => route('admin.deliveries.live', $delivery),
        'trail' => $trail->map(fn ($p) => [$p->lat, $p->lng])->values()->all(),
        'last' => $delivery->last_lat !== null ? [$delivery->last_lat, $delivery->last_lng] : null,
        'proof' => $delivery->proof_lat !== null ? [$delivery->proof_lat, $delivery->proof_lng] : null,
    ];
?>
<a href="<?php echo e(route('admin.deliveries.index')); ?>" class="small text-decoration-none">← All deliveries</a>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2 mb-3">
    <div class="page-title mb-0"><?php echo e($delivery->reference); ?> <span class="pill <?php echo e($stagePill[$delivery->stage]); ?>" id="stagePill"><?php echo e($delivery->stage_label); ?></span></div>
    <span class="text-muted small" id="seen"><?php if($delivery->last_seen_at): ?>Last location <?php echo e($delivery->last_seen_at->diffForHumans()); ?><?php endif; ?></span>
</div>

<?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?>

<div class="prog mb-3">
    <span class="on">Dispatched</span>
    <span class="<?php echo e($delivery->trip_started_at ? 'on' : ''); ?>">Trip started</span>
    <span class="<?php echo e($delivery->scanned_at ? 'on' : ''); ?>">Item QR scanned</span>
    <span class="<?php echo e($delivery->proof_photo_path ? 'on' : ''); ?>">Photo + GPS proof</span>
    <span class="<?php echo e($delivery->status === 'delivered' ? 'on' : ''); ?>">Delivered</span>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-white p-2"><div id="dMap"></div></div>

        <div class="card-white">
            <h6>Proof of delivery</h6>
            <?php if($delivery->proof_photo_path): ?>
                <img class="proof-img" src="<?php echo e(route('admin.deliveries.proof', $delivery)); ?>" alt="Proof photo">
                <div class="small mt-2">
                    Scanned code: <strong><?php echo e($delivery->scanned_code); ?></strong> · <?php echo e($delivery->proof_taken_at?->timezone($systemSettings['timezone'])->format('M j, Y g:i A')); ?>

                    <?php if($delivery->proof_lat !== null): ?>
                        <br>📍 <?php echo e(number_format($delivery->proof_lat, 5)); ?>, <?php echo e(number_format($delivery->proof_lng, 5)); ?><?php if($delivery->proof_accuracy): ?> (±<?php echo e(round($delivery->proof_accuracy)); ?> m)<?php endif; ?>
                        · <a target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q=<?php echo e($delivery->proof_lat); ?>,<?php echo e($delivery->proof_lng); ?>">Open in Maps ↗</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-muted small">No proof yet. The driver must scan the item QR, take a photo and confirm their location.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-white">
            <h6>Details</h6>
            <table><tbody>
                <tr><td class="text-muted">Item</td><td><?php echo e($delivery->item->name); ?> (<?php echo e($delivery->item->code); ?>)</td></tr>
                <tr><td class="text-muted">Quantity</td><td><?php echo e($delivery->qty); ?> <?php echo e($delivery->item->unit); ?></td></tr>
                <tr><td class="text-muted">Destination</td><td><?php echo e($delivery->destination); ?></td></tr>
                <tr><td class="text-muted">Scheduled</td><td><?php echo e($delivery->scheduled_at->format('M j, Y g:i A')); ?></td></tr>
                <tr><td class="text-muted">Vehicle</td><td><?php echo e($delivery->vehicle ?: '—'); ?></td></tr>
                <tr><td class="text-muted">Payment</td><td><?php echo e($symbol); ?><?php echo e(number_format((float) $delivery->amount, 2)); ?> · <?php echo e($delivery->payment_method === 'paymongo' ? 'Online' : 'COD'); ?> · <?php echo e(ucfirst($delivery->payment_status)); ?></td></tr>
            </tbody></table>
        </div>

        <div class="card-white">
            <h6>Driver</h6>
            <div class="mb-2"><?php echo e($delivery->assignee?->name ?? $delivery->driver ?? 'Not assigned'); ?><?php if($delivery->assignee?->phone): ?> · <?php echo e($delivery->assignee->phone); ?><?php endif; ?></div>
            <?php if($delivery->status !== 'delivered'): ?>
                <form method="POST" action="<?php echo e(route('admin.deliveries.assign', $delivery)); ?>" class="d-flex gap-2">
                    <?php echo csrf_field(); ?>
                    <select name="driver_id" class="form-select" required>
                        <option value="">Choose driver…</option>
                        <?php $__currentLoopData = $drivers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $driver): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($driver->id); ?>" <?php if($delivery->assigned_to === $driver->id): echo 'selected'; endif; ?>><?php echo e($driver->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <button class="btn-blue" type="submit"><?php echo e($delivery->assigned_to ? 'Reassign' : 'Assign'); ?></button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card-white">
            <h6>Timeline</h6>
            <div class="tl" id="timeline">
                <?php $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="ev"><?php echo e($e->message); ?><div class="w"><?php echo e($e->user?->name ?? 'System'); ?> · <?php echo e($e->created_at->timezone($systemSettings['timezone'])->format('M j, g:i:s A')); ?></div></div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const CFG = <?php echo json_encode($cfg, 15, 512) ?>;
    const esc = WL.esc;
    const map = L.map('dMap').setView(CFG.last || CFG.proof || [14.5995, 120.9842], CFG.last || CFG.proof ? 15 : 11);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

    const trail = L.polyline(CFG.trail, { color: '#2563eb', weight: 4, opacity: .75 }).addTo(map);
    let current = CFG.last ? L.circleMarker(CFG.last, { radius: 9, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1 }).addTo(map).bindTooltip('Driver') : null;
    if (CFG.proof) {
        L.circleMarker(CFG.proof, { radius: 9, color: '#fff', weight: 3, fillColor: '#16a34a', fillOpacity: 1 }).addTo(map).bindTooltip('Proof location');
    }
    if (CFG.trail.length > 1) { map.fitBounds(trail.getBounds(), { padding: [30, 30], maxZoom: 16 }); }

    const done = <?php echo json_encode($delivery->status === 'delivered', 15, 512) ?>;
    const hadProof = <?php echo json_encode($delivery->proof_photo_path !== null, 15, 512) ?>;
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
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\gelom\Desktop\System\warelog-erp\resources\views/admin/deliveries/show.blade.php ENDPATH**/ ?>

<?php $__env->startSection('content'); ?>
<div class="page-title">📥 Stock In</div>
<p style="color:#666;margin-top:-10px;margin-bottom:15px">Enter items received into your warehouse</p>

<?php if(session('labels_for')): ?>
    <?php $lf = session('labels_for'); ?>
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
        <span>🏷️ Print QR labels for the items you just received.</span>
        <a class="btn-blue" target="_blank" href="<?php echo e(route('inventory.labels', ['item' => $lf['item_id'], 'copies' => $lf['qty']])); ?>">Print <?php echo e($lf['qty']); ?> label(s)</a>
    </div>
<?php endif; ?>

<div class="card-white">
    <form method="POST" action="<?php echo e(route('inventory.stockin.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="item_id">Item <button type="button" class="btn-ghost" id="scanItem" style="padding:2px 10px;margin-left:6px">Scan QR</button></label>
                <div id="stockScanner" hidden class="mb-2" style="max-width:320px"></div>
                <div id="stockScanMsg" class="small text-muted"></div>
                <select id="item_id" name="item_id" class="form-select" required>
                    <option value="">Select an item</option>
                    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($item->id); ?>" <?php if(old('item_id') == $item->id): echo 'selected'; endif; ?>><?php echo e($item->code); ?> · <?php echo e($item->name); ?> (<?php echo e($item->qty); ?> <?php echo e($item->unit); ?> on hand)</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="qty">Quantity received</label>
                <input id="qty" name="qty" type="number" class="form-control" min="1" step="1" value="<?php echo e(old('qty')); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="supplier">Supplier / source</label>
                <input id="supplier" name="supplier" type="text" class="form-control" maxlength="255" value="<?php echo e(old('supplier')); ?>">
            </div>
        </div>
        <?php if($errors->any()): ?>
            <div class="alert alert-danger mt-3" role="alert"><?php echo e($errors->first()); ?></div>
        <?php endif; ?>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn-green">Add to inventory</button>
            <a href="<?php echo e(route('inventory.stockin')); ?>" class="btn-red text-decoration-none">Cancel</a>
        </div>
    </form>
</div>

<div class="card-white">
    <h6>📋 Stock-in Records</h6>
    <table>
        <thead><tr><th>#</th><th>Item Name</th><th>Quantity</th><th>Supplier</th><th>Date &amp; Time</th></tr></thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $movements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $movement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr><td><?php echo e($movement->id); ?></td><td><?php echo e($movement->item_name); ?></td><td><?php echo e($movement->qty); ?></td><td><?php echo e($movement->supplier ?: '—'); ?></td><td><?php echo e($movement->created_at->timezone($systemSettings['timezone'])->format($systemSettings['date_format'].' g:i A')); ?></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No stock-in transactions yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php echo e($movements->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\gelom\Desktop\System\warelog-erp\resources\views/stockin.blade.php ENDPATH**/ ?>
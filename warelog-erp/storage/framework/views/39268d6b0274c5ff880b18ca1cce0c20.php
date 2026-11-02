
<?php $__env->startSection('content'); ?>
<div class="page-title">📤 Stock Out / Issuance</div>
<p style="color:#666;margin-top:-10px;margin-bottom:15px">Release available inventory and record its recipient.</p>

<div class="card-white">
    <form method="POST" action="<?php echo e(route('inventory.stockout.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="item_id">Item</label>
                <select id="item_id" name="item_id" class="form-select" required>
                    <option value="">Select an item</option>
                    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($item->id); ?>" <?php if(old('item_id') == $item->id): echo 'selected'; endif; ?>><?php echo e($item->code); ?> · <?php echo e($item->name); ?> (<?php echo e($item->qty); ?> <?php echo e($item->unit); ?> available)</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="qty">Quantity to release</label>
                <input id="qty" name="qty" type="number" class="form-control" min="1" step="1" value="<?php echo e(old('qty')); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="issued_to">Issue to / department</label>
                <input id="issued_to" name="issued_to" type="text" class="form-control" maxlength="255" value="<?php echo e(old('issued_to')); ?>" required>
            </div>
        </div>
        <?php if($errors->any()): ?>
            <div class="alert alert-danger mt-3" role="alert"><?php echo e($errors->first()); ?></div>
        <?php endif; ?>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn-green">Confirm stock-out</button>
            <a href="<?php echo e(route('inventory.stockout')); ?>" class="btn-red text-decoration-none">Cancel</a>
        </div>
    </form>
</div>

<div class="card-white">
    <h6>Stock-out Records</h6>
    <div class="table-responsive">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Quantity</th><th>Issued to</th><th>Date</th></tr></thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $movements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $movement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr><td><?php echo e($movement->id); ?></td><td><?php echo e($movement->item_name); ?></td><td><?php echo e($movement->qty); ?></td><td><?php echo e($movement->issued_to ?: '—'); ?></td><td><?php echo e($movement->created_at->timezone($systemSettings['timezone'])->format($systemSettings['date_format'].' g:i A')); ?></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No stock-out transactions yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php echo e($movements->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\gelom\Desktop\System\warelog-erp\resources\views/stockout.blade.php ENDPATH**/ ?>
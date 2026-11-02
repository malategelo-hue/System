<?php $__env->startSection('content'); ?>
<?php
    $stagePill = ['ready' => 'pill-ready', 'on_the_way' => 'pill-way', 'awaiting_payment' => 'pill-pay', 'delivered' => 'pill-done'];
    $tabs = ['all' => 'All', 'active' => 'Active', 'unassigned' => 'Unassigned', 'delivered' => 'Delivered'];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="page-title mb-0">🚚 Deliveries</div>
    <div class="d-flex gap-2">
        <a href="<?php echo e(route('tracking.index')); ?>" class="btn-ghost">📍 Live map</a>
        <button class="btn-green" type="button" data-bs-toggle="modal" data-bs-target="#dispatchModal">+ New Dispatch</button>
    </div>
</div>

<?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
    <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('admin.deliveries.index', ['stage' => $key])); ?>" class="<?php echo e($filter === $key ? 'btn-blue' : 'btn-ghost'); ?>"><?php echo e($label); ?> <span class="opacity-75">(<?php echo e($counts[$key]); ?>)</span></a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="card-white">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Dispatch</th><th>Item</th><th>Destination</th><th>Driver</th><th>Scheduled</th><th>Amount</th><th>Status</th><th>Proof</th><th></th></tr></thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $deliveries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><strong><?php echo e($d->reference); ?></strong></td>
                        <td><?php echo e($d->item->name); ?> (<?php echo e($d->qty); ?> <?php echo e($d->item->unit); ?>)</td>
                        <td><?php echo e($d->destination); ?></td>
                        <td><?php echo e($d->assignee?->name ?? $d->driver ?? '—'); ?><?php if(! $d->assigned_to && $d->status !== 'delivered'): ?> <span class="pill pill-muted">Unassigned</span><?php endif; ?></td>
                        <td><?php echo e($d->scheduled_at->format('M j, g:i A')); ?></td>
                        <td><?php echo e($d->currency === 'USD' ? '$' : '₱'); ?><?php echo e(number_format((float) $d->amount, 2)); ?> · <?php echo e($d->payment_method === 'paymongo' ? 'Online' : 'COD'); ?></td>
                        <td><span class="pill <?php echo e($stagePill[$d->stage]); ?>"><?php echo e($d->stage_label); ?></span></td>
                        <td><?php echo e($d->hasProof() ? '📷 ✓' : '—'); ?></td>
                        <td><a href="<?php echo e(route('admin.deliveries.show', $d)); ?>">Open</a></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No deliveries here yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-3"><?php echo e($deliveries->links()); ?></div>
</div>

<div class="modal fade" id="dispatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"><div class="modal-content p-4">
        <h5 class="mb-3">Create delivery dispatch</h5>
        <form method="POST" action="<?php echo e(route('admin.deliveries.store')); ?>">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-7"><label class="form-label" for="d_item">Item</label>
                    <select id="d_item" name="item_id" class="form-select" required>
                        <option value="">Select available inventory</option>
                        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($item->id); ?>"><?php echo e($item->code); ?> · <?php echo e($item->name); ?> (<?php echo e($item->qty); ?> <?php echo e($item->unit); ?> available)</option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-md-5"><label class="form-label" for="d_qty">Quantity</label><input id="d_qty" name="qty" type="number" min="1" class="form-control" required></div>
                <div class="col-12"><label class="form-label" for="d_dest">Destination</label><input id="d_dest" name="destination" class="form-control" maxlength="255" required></div>
                <div class="col-md-6"><label class="form-label" for="d_driver">Assign driver</label>
                    <select id="d_driver" name="driver_id" class="form-select">
                        <option value="">Leave unassigned (drivers can claim)</option>
                        <?php $__currentLoopData = $drivers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $driver): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($driver->id); ?>"><?php echo e($driver->name); ?><?php echo e($driver->vehicle ? ' · '.$driver->vehicle : ''); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\gelom\Desktop\System\warelog-erp\resources\views/admin/deliveries/index.blade.php ENDPATH**/ ?>
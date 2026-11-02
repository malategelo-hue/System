<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="page-title mb-0">🚚 Drivers</div>
</div>
<p class="page-sub">Drivers sign up for an account here, then wait for admin approval. Activate an account before assigning deliveries.</p>

<?php ($pendingCount = $users->where('is_active', false)->count()); ?>
<?php if($pendingCount > 0): ?>
    <div class="alert alert-warning" role="alert">
        <strong><?php echo e($pendingCount); ?> driver<?php echo e($pendingCount > 1 ? 's' : ''); ?> waiting for approval.</strong>
        Click <em>Approve / activate</em> sa ibaba para makapag-login sila at mabigyan ng deliveries.
    </div>
<?php endif; ?>

<?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?>

<div class="card-white">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Vehicle</th><th>Active deliveries</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><strong><?php echo e($u->name); ?></strong></td>
                        <td><?php echo e($u->email); ?></td>
                        <td><?php echo e($u->phone ?: '—'); ?></td>
                        <td><?php echo e($u->vehicle ?: '—'); ?></td>
                        <td><?php echo e($u->active_deliveries_count); ?></td>
                        <td><?php echo e($u->deliveries_count); ?></td>
                        <td><?php if($u->is_active === false): ?><span class="badge-out">Pending / inactive</span><?php else: ?><span class="badge-good">Active</span><?php endif; ?></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn-ghost" style="padding:5px 10px" data-bs-toggle="modal" data-bs-target="#edit<?php echo e($u->id); ?>">Edit</button>
                            <form method="POST" action="<?php echo e(route('admin.users.toggle', $u)); ?>" class="d-inline"><?php echo csrf_field(); ?>
                                <button type="submit" class="btn-ghost" style="padding:5px 10px"><?php echo e($u->is_active === false ? 'Approve / activate' : 'Deactivate'); ?></button>
                            </form>
                            <form method="POST" action="<?php echo e(route('admin.users.destroy', $u)); ?>" class="d-inline" onsubmit="return confirm('Delete this account?')"><?php echo csrf_field(); ?>
                                <button type="submit" class="btn-red" style="padding:5px 10px">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No drivers have signed up yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="modal fade" id="edit<?php echo e($u->id); ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content p-4">
        <h5 class="mb-3">Edit <?php echo e($u->name); ?></h5>
        <form method="POST" action="<?php echo e(route('admin.users.update', $u)); ?>">
            <?php echo csrf_field(); ?>
            <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="<?php echo e($u->name); ?>" required></div>
            <div class="row g-3 mb-3">
                <div class="col-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?php echo e($u->phone); ?>"></div>
                <div class="col-6"><label class="form-label">Vehicle</label><input name="vehicle" class="form-control" value="<?php echo e($u->vehicle); ?>"></div>
            </div>
            <div class="mb-3"><label class="form-label">New password (leave blank to keep)</label><input type="password" name="password" class="form-control" autocomplete="new-password"></div>
            <div class="d-flex justify-content-end gap-2"><button type="button" class="btn-red" data-bs-dismiss="modal">Cancel</button><button class="btn-green" type="submit">Save</button></div>
        </form>
    </div></div>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\gelom\Desktop\System\warelog-erp\resources\views/users/index.blade.php ENDPATH**/ ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="page-title mb-0">📦 Inventory Management</div>
    <?php if(!isset($item)): ?>
        <button class="btn-green" data-bs-toggle="modal" data-bs-target="#addModal">+ Add New Item</button>
    <?php endif; ?>
</div>

<?php
    $total = $items->count();
    $threshold = (int) $systemSettings['low_stock_threshold'];
    $inStock = $items->where('qty', '>', $threshold)->count();
    $lowStock = $items->whereBetween('qty', [1, $threshold])->count();
    $outStock = $items->where('qty', '<=', 0)->count();
?>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="stat-card"><div class="label">Total Items</div><div class="num"><?php echo e($total); ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="label">In Stock</div><div class="num" style="color:#28a745"><?php echo e($inStock); ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="label">Low Stock</div><div class="num" style="color:#ffc107"><?php echo e($lowStock); ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="label">Out of Stock</div><div class="num" style="color:#dc3545"><?php echo e($outStock); ?></div></div></div>
</div>

<?php if(session('labels_for')): ?>
    <?php $lf = session('labels_for'); ?>
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
        <span>🏷️ Print QR labels for the new stock so every item can be scanned.</span>
        <a class="btn-blue" target="_blank" href="<?php echo e(route('inventory.labels', ['item' => $lf['item_id'], 'copies' => $lf['qty']])); ?>">Print <?php echo e($lf['qty']); ?> label(s)</a>
    </div>
<?php endif; ?>


<?php if(isset($item)): ?>
<div class="card-white">
    <h5>✏️ Edit Item: <?php echo e($item->name); ?></h5>
    <form method="POST" action="/inventory/update/<?php echo e($item->id); ?>">
        <?php echo csrf_field(); ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="edit_item_code">Item Code *</label>
                <input id="edit_item_code" type="text" name="code" class="form-control" value="<?php echo e($item->code); ?>" required data-code-check="<?php echo e(route('inventory.code-check')); ?>" data-except-item="<?php echo e($item->id); ?>">
                <div class="form-text" data-code-message aria-live="polite"></div>
                <?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger small"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div class="col-md-4"><label>Item Name *</label><input type="text" name="name" class="form-control" value="<?php echo e($item->name); ?>" required></div>
            <div class="col-md-4"><label>Category *</label>
                <select name="category" class="form-select" required>
                    <option value="">Choose...</option>
                    <option <?php echo e($item->category=='Raw Materials'?'selected':''); ?>>Raw Materials</option>
                    <option <?php echo e($item->category=='Supplies'?'selected':''); ?>>Supplies</option>
                    <option <?php echo e($item->category=='Finished Goods'?'selected':''); ?>>Finished Goods</option>
                    <option <?php echo e($item->category=='Equipment'?'selected':''); ?>>Equipment</option>
                </select>
            </div>
            <div class="col-md-3"><label>Stock Qty *</label><input type="number" name="qty" class="form-control" value="<?php echo e($item->qty); ?>" required></div>
            <div class="col-md-3"><label>Unit *</label><input type="text" name="unit" class="form-control" value="<?php echo e($item->unit); ?>" required></div>
            <div class="col-md-3"><label>Unit Price (<?php echo e($systemSettings['currency'] === 'USD' ? '$' : '₱'); ?>) *</label><input type="number" step="0.01" name="price" class="form-control" value="<?php echo e($item->price); ?>" required></div>
            <div class="col-md-3"><label>Location *</label>
                <select name="location" class="form-select" required>
                    <option value="">Choose...</option>
                    <option <?php echo e($item->location=='Warehouse A'?'selected':''); ?>>Warehouse A</option>
                    <option <?php echo e($item->location=='Warehouse B'?'selected':''); ?>>Warehouse B</option>
                    <option <?php echo e($item->location=='Warehouse C'?'selected':''); ?>>Warehouse C</option>
                </select>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn-green">💾 Save Changes</button>
            <a href="/inventory" class="btn-red" style="text-decoration:none;padding:8px 16px;border-radius:4px">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card-white">
    <table>
        <thead><tr><th>QR</th><th>Item Code</th><th>Item Name</th><th>Category</th><th>Qty</th><th>Unit</th><th>Price</th><th>Location</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $itm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                
                <td>
                    <span data-qr="<?php echo e($itm->qr_payload); ?>" data-qr-size="52" style="cursor:pointer"
                         data-bs-toggle="modal" data-bs-target="#qrModal<?php echo e($itm->id); ?>"></span>
                </td>
                <td><?php echo e($itm->code); ?></td>
                <td><?php echo e($itm->name); ?></td>
                <td><?php echo e($itm->category); ?></td>
                <td><?php echo e($itm->qty); ?></td>
                <td><?php echo e($itm->unit); ?></td>
                <td><?php echo e($systemSettings['currency'] === 'USD' ? '$' : '₱'); ?><?php echo e(number_format($itm->price, $systemSettings['show_decimals'] === '1' ? 2 : 0)); ?></td>
                <td><?php echo e($itm->location); ?></td>
                <td>
                    <?php if($itm->qty > $threshold): ?> <span class="badge-good">Good</span>
                    <?php elseif($itm->qty > 0): ?> <span class="badge-low">Low Stock</span>
                    <?php else: ?> <span class="badge-out">Out of Stock</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button type="button" class="btn-ghost" style="padding:4px 10px;margin-right:6px" data-bs-toggle="modal" data-bs-target="#receive<?php echo e($itm->id); ?>">📥 Receive</button>
                    <a href="/inventory/edit/<?php echo e($itm->id); ?>" style="margin-right:8px">✏️ Edit</a>
                    <form method="POST" action="/inventory/delete/<?php echo e($itm->id); ?>" style="display:inline" onsubmit="return confirm('Delete this item?')">
                        <?php echo csrf_field(); ?>
                        <button type="submit" style="background:none;border:none;color:#dc3545;cursor:pointer">🗑️ Delete</button>
                    </form>
                </td>
            </tr>

            
            <div class="modal fade" id="qrModal<?php echo e($itm->id); ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content p-4 text-center">
                        <h5><?php echo e($itm->name); ?></h5>
                        <p class="text-muted">Code: <?php echo e($itm->code); ?></p>
                        <div data-qr="<?php echo e($itm->qr_payload); ?>" data-qr-size="250" style="margin:10px auto"></div>
                        <a class="btn-green mt-2" target="_blank" href="<?php echo e(route('inventory.labels', $itm)); ?>">🖨️ Print labels</a>
                    </div>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="10" style="text-align:center;color:#999;padding:30px">No items yet — click "Add New Item"</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $itm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="modal fade" id="receive<?php echo e($itm->id); ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content p-4">
        <h5>📥 Receive stock</h5>
        <p class="text-muted small"><?php echo e($itm->code); ?> · <?php echo e($itm->name); ?> (now <?php echo e($itm->qty); ?> <?php echo e($itm->unit); ?>)</p>
        <form method="POST" action="<?php echo e(route('inventory.receive', $itm)); ?>">
            <?php echo csrf_field(); ?>
            <div class="mb-3"><label class="form-label">Quantity received</label><input type="number" name="qty" min="1" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Supplier / source</label><input name="supplier" class="form-control" maxlength="255"></div>
            <div class="d-flex justify-content-end gap-2"><button type="button" class="btn-red" data-bs-dismiss="modal">Cancel</button><button class="btn-green" type="submit">Add to inventory</button></div>
        </form>
    </div></div>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>


<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content p-4">
            <h5 class="mb-3">➕ Add New Item</h5>
            <form method="POST" action="/inventory/add">
                <?php echo csrf_field(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="new_item_code">Item Code <?php echo e($systemSettings['auto_codes'] === '1' ? '(optional)' : '*'); ?></label>
                        <input id="new_item_code" type="text" name="code" class="form-control" placeholder="<?php echo e($systemSettings['auto_codes'] === '1' ? 'Leave blank to generate' : 'ITM-00001'); ?>" data-code-check="<?php echo e(route('inventory.code-check')); ?>" <?php if (! ($systemSettings['auto_codes'] === '1')): ?> required <?php endif; ?>>
                        <div class="form-text" data-code-message aria-live="polite"></div>
                        <?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger small"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="col-md-6"><label>Item Name *</label><input type="text" name="name" class="form-control" placeholder="Name of product" required></div>
                    <div class="col-md-6"><label>Category *</label>
                        <select name="category" class="form-select" required>
                            <option value="">Choose...</option>
                            <option>Raw Materials</option>
                            <option>Supplies</option>
                            <option>Finished Goods</option>
                            <option>Equipment</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label>Unit of Measure *</label>
                        <select name="unit" class="form-select" required>
                            <option value="">Choose...</option>
                            <option>pcs</option><option>box</option><option>roll</option>
                            <option>kg</option><option>pack</option><option>set</option>
                        </select>
                    </div>
                    <div class="col-md-4"><label>Initial Stock Qty *</label><input type="number" name="qty" class="form-control" value="0" required></div>
                    <div class="col-md-4"><label>Unit Price (<?php echo e($systemSettings['currency'] === 'USD' ? '$' : '₱'); ?>) *</label><input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required></div>
                    <div class="col-md-4"><label>Warehouse / Location *</label>
                        <select name="location" class="form-select" required>
                            <option value="">Choose...</option>
                            <option>Warehouse A</option>
                            <option>Warehouse B</option>
                            <option>Warehouse C</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2 justify-content-end">
                    <button type="button" class="btn-red" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-green">💾 Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.querySelectorAll('[data-code-check]').forEach(input => {
    let timer;
    const message = input.parentElement.querySelector('[data-code-message]');
    input.addEventListener('input', () => {
        clearTimeout(timer);
        input.setCustomValidity('');
        message.textContent = '';

        const code = input.value.trim();
        if (!code) return;

        timer = setTimeout(async () => {
            const url = new URL(input.dataset.codeCheck, window.location.origin);
            url.searchParams.set('code', code);
            if (input.dataset.exceptItem) url.searchParams.set('except', input.dataset.exceptItem);

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Code check failed.');
                const result = await response.json();
                if (result.duplicate) {
                    message.textContent = 'This code already exists. Choose a different item code.';
                    message.className = 'form-text text-danger';
                    input.setCustomValidity('This item code already exists.');
                } else if (result.similar.length) {
                    message.textContent = `Similar item codes found: ${result.similar.join(', ')}.`;
                    message.className = 'form-text text-warning';
                } else {
                    message.textContent = 'Item code is available.';
                    message.className = 'form-text text-success';
                }
            } catch {
                message.textContent = 'Could not check for similar codes. The server will still validate duplicates when saved.';
                message.className = 'form-text text-warning';
            }
        }, 250);
    });
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\gelom\Desktop\System\warelog-erp\resources\views/inventory.blade.php ENDPATH**/ ?>
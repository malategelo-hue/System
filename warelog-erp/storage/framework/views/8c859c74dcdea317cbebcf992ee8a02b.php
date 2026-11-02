
<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">⚙️ System Settings</h5>
</div>

<?php if(session('success')): ?>
    <div class="alert alert-success" role="status"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<?php if($errors->any()): ?>
    <div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div>
<?php endif; ?>

<?php if(Auth::user()->role === 'admin'): ?>
    <form method="POST" action="<?php echo e(route('settings.save')); ?>">
        <?php echo csrf_field(); ?>
        <div class="row g-4">
            <div class="col-lg-6">
                <section class="settings-card">
                    <h6 class="mb-3">🏢 Company & Warehouse</h6>
                    <div class="mb-3">
                        <label class="form-label" for="system_name">System name</label>
                        <input id="system_name" name="system_name" class="form-control" maxlength="50" required value="<?php echo e(old('system_name', $systemSettings['system_name'])); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="company_name">Company name</label>
                        <input id="company_name" name="company_name" class="form-control" maxlength="100" required value="<?php echo e(old('company_name', $systemSettings['company_name'])); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="warehouse_name">Warehouse name</label>
                        <input id="warehouse_name" name="warehouse_name" class="form-control" maxlength="50" required value="<?php echo e(old('warehouse_name', $systemSettings['warehouse_name'])); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="address">Address</label>
                        <input id="address" name="address" class="form-control" maxlength="255" value="<?php echo e(old('address', $systemSettings['address'])); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="contact_no">Contact number</label>
                        <input id="contact_no" name="contact_no" type="tel" class="form-control" maxlength="50" value="<?php echo e(old('contact_no', $systemSettings['contact_no'])); ?>">
                    </div>
                </section>
            </div>

            <div class="col-lg-6">
                <section class="settings-card">
                    <h6 class="mb-3">⚙️ Inventory & Display</h6>
                    <div class="mb-3">
                        <label class="form-label" for="currency">Currency</label>
                        <select id="currency" name="currency" class="form-select">
                            <option value="PHP" <?php if(old('currency', $systemSettings['currency']) === 'PHP'): echo 'selected'; endif; ?>>Philippine Peso (₱)</option>
                            <option value="USD" <?php if(old('currency', $systemSettings['currency']) === 'USD'): echo 'selected'; endif; ?>>US Dollar ($)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="low_stock_threshold">Low-stock threshold</label>
                        <input id="low_stock_threshold" name="low_stock_threshold" type="number" class="form-control" min="1" max="1000000" required value="<?php echo e(old('low_stock_threshold', $systemSettings['low_stock_threshold'])); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="date_format">Date format</label>
                        <select id="date_format" name="date_format" class="form-select">
                            <option value="m/d/Y" <?php if(old('date_format', $systemSettings['date_format']) === 'm/d/Y'): echo 'selected'; endif; ?>>MM/DD/YYYY</option>
                            <option value="d/m/Y" <?php if(old('date_format', $systemSettings['date_format']) === 'd/m/Y'): echo 'selected'; endif; ?>>DD/MM/YYYY</option>
                            <option value="Y-m-d" <?php if(old('date_format', $systemSettings['date_format']) === 'Y-m-d'): echo 'selected'; endif; ?>>YYYY-MM-DD</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="timezone">Display timezone</label>
                        <select id="timezone" name="timezone" class="form-select">
                            <?php $__currentLoopData = timezone_identifiers_list(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $timezone): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($timezone); ?>" <?php if(old('timezone', $systemSettings['timezone']) === $timezone): echo 'selected'; endif; ?>><?php echo e($timezone); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <?php $__currentLoopData = [
                        'low_stock_alerts' => 'Low-stock alerts',
                        'auto_codes' => 'Generate a code when item code is blank',
                        'show_decimals' => 'Show currency decimals',
                        'dark_mode' => 'Dark mode',
                        'auto_logout' => 'Automatically log out inactive users',
                        'backup_daily' => 'Back up the database daily',
                        'require_gps_proof' => 'Require GPS location on proof of delivery',
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="toggle-row">
                            <label for="<?php echo e($key); ?>"><?php echo e($label); ?></label>
                            <label class="toggle-switch">
                                <input id="<?php echo e($key); ?>" type="checkbox" name="<?php echo e($key); ?>" value="1" <?php if((bool) old($key, $systemSettings[$key] === '1')): echo 'checked'; endif; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <div class="mt-3">
                        <label class="form-label" for="session_timeout">Automatic logout after</label>
                        <select id="session_timeout" name="session_timeout" class="form-select">
                            <?php $__currentLoopData = [15, 30, 60, 120, 240]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $minutes): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($minutes); ?>" <?php if((int) old('session_timeout', $systemSettings['session_timeout']) === $minutes): echo 'selected'; endif; ?>><?php echo e($minutes); ?> minutes</option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </section>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-3 mt-4">
            <button type="submit" class="btn-save">💾 Save Changes</button>
        </div>
    </form>

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <section class="settings-card">
                <h6 class="mb-3">🔒 Change Password</h6>
                <form method="POST" action="<?php echo e(route('settings.password')); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password</label>
                        <input id="current_password" type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_password">New password</label>
                        <input id="new_password" type="password" name="new_password" class="form-control" autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_password_confirmation">Confirm new password</label>
                        <input id="new_password_confirmation" type="password" name="new_password_confirmation" class="form-control" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn-save">Change Password</button>
                </form>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="settings-card">
                <h6 class="mb-3">🧰 Maintenance</h6>
                <p>Export a copy of the SQLite database or clear Laravel's application caches. Daily backups run at 02:00 when enabled and require the Laravel scheduler to run.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary" href="<?php echo e(route('settings.export')); ?>">Export Database</a>
                    <form method="POST" action="<?php echo e(route('settings.clear-cache')); ?>" onsubmit="return confirm('Clear Laravel application caches?')">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-outline-secondary">Clear Application Cache</button>
                    </form>
                    <form method="POST" action="<?php echo e(route('settings.reset')); ?>" onsubmit="return confirm('Restore all system settings to their defaults?')">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn-reset">Reset Settings</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
<?php else: ?>
    <section class="settings-card">
        <h6>Account</h6>
        <p>Signed in as <?php echo e(Auth::user()->name); ?> (<?php echo e(Auth::user()->email); ?>).</p>
        <a href="<?php echo e(route('profile.edit')); ?>" class="btn btn-primary">Edit Profile & Password</a>
    </section>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\gelom\Desktop\System\warelog-erp\resources\views/settings.blade.php ENDPATH**/ ?>
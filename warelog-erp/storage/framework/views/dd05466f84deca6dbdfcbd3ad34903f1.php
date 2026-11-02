<?php $__env->startPush('head'); ?>
<style>
    .wrap{max-width:760px;margin:0 auto}
    .sum-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .sum-grid .k{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:600}
    .sum-grid .v{font-weight:600;margin-top:2px}
    .steps{display:grid;gap:14px}
    .step{display:flex;gap:14px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;box-shadow:var(--shadow)}
    .step .num{width:32px;height:32px;border-radius:50%;background:var(--surface-2);border:1px solid var(--border);display:grid;place-items:center;font-weight:700;flex:none;color:var(--muted)}
    .step.done{border-color:#86efac}
    .step.done .num{background:#16a34a;border-color:#16a34a;color:#fff}
    .step .body{flex:1;min-width:0}
    .step h6{margin:0 0 4px;font-weight:700}
    .step .hint{font-size:12.5px;color:var(--muted);margin-bottom:10px}
    #proofScanner{max-width:340px;width:100%;margin-top:10px}
    .preview{max-width:100%;max-height:240px;border-radius:12px;border:1px solid var(--border);margin-top:10px;display:none}
    .gps-line{font-size:13px;margin-top:6px}
    .gps-ok{color:#15803d;font-weight:600}
    .gps-bad{color:#b91c1c;font-weight:600}
    .share-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:#dcfce7;color:#166534;font-weight:600;font-size:13px}
    .share-chip.off{background:#fee2e2;color:#991b1b}
    .proof-img{width:100%;max-height:420px;object-fit:cover;border-radius:12px;border:1px solid var(--border)}
    .link-btn{background:none;border:0;padding:0;color:var(--primary);font-size:12.5px;text-decoration:underline;cursor:pointer}
</style>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<?php
    $stagePill = ['ready' => 'pill-ready', 'on_the_way' => 'pill-way', 'awaiting_payment' => 'pill-pay', 'delivered' => 'pill-done'];
    $symbol = $delivery->currency === 'USD' ? '$' : '₱';
    $stage = $delivery->stage;
    $tracking = $isMine && $delivery->trip_started_at && ! $delivery->hasProof() && $delivery->status !== 'delivered';
    $cfg = ['ping' => route('logistics.ping', $delivery), 'requireGps' => $requireGps, 'tracking' => $tracking];
?>
<div class="wrap">
    <a href="<?php echo e(route('logistics')); ?>" class="text-decoration-none small">← All deliveries</a>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2 mb-3">
        <div class="page-title mb-0"><?php echo e($delivery->reference); ?></div>
        <span class="pill <?php echo e($stagePill[$stage]); ?>"><?php echo e($delivery->stage_label); ?></span>
    </div>

    <?php if(session('error')): ?><div class="alert alert-warning"><?php echo e(session('error')); ?></div><?php endif; ?>
    <?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?>

    <div class="card-white">
        <div class="sum-grid">
            <div><div class="k">Item</div><div class="v"><?php echo e($delivery->item->name); ?></div></div>
            <div><div class="k">Quantity</div><div class="v"><?php echo e($delivery->qty); ?> <?php echo e($delivery->item->unit); ?></div></div>
            <div style="grid-column:1/-1">
                <div class="k">Deliver to</div>
                <div class="v"><?php echo e($delivery->destination); ?></div>
                <a class="small" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&query=<?php echo e(urlencode($delivery->destination)); ?>">Open destination in Maps ↗</a>
            </div>
            <div><div class="k">Scheduled</div><div class="v"><?php echo e($delivery->scheduled_at->format('M j, Y g:i A')); ?></div></div>
            <div><div class="k">Collect</div><div class="v"><?php echo e($symbol); ?><?php echo e(number_format((float) $delivery->amount, 2)); ?> · <?php echo e($delivery->payment_method === 'paymongo' ? 'Online (PayMongo)' : 'Cash on delivery'); ?></div></div>
        </div>
    </div>

    
    <?php if($stage === 'delivered'): ?>
        <div class="card-white">
            <h6 class="mb-1">✅ Delivered <?php echo e($delivery->delivered_at?->timezone($systemSettings['timezone'])->format('M j, Y g:i A')); ?></h6>
            <?php if($delivery->hasProof()): ?>
                <div class="text-muted small mb-2">Proof saved: item QR scanned, photo and location recorded.</div>
                <img class="proof-img" src="<?php echo e(route('logistics.proof', $delivery)); ?>" alt="Proof of delivery photo">
                <?php if($delivery->proof_lat !== null): ?>
                    <div class="small mt-2">📍 <?php echo e(number_format($delivery->proof_lat, 5)); ?>, <?php echo e(number_format($delivery->proof_lng, 5)); ?>

                        <?php if($delivery->proof_accuracy): ?> (±<?php echo e(round($delivery->proof_accuracy)); ?> m) <?php endif; ?>
                        · <a target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q=<?php echo e($delivery->proof_lat); ?>,<?php echo e($delivery->proof_lng); ?>">Open in Maps ↗</a></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    
    <?php elseif(! $isMine): ?>
        <div class="card-white">
            <h6>This delivery is not assigned yet</h6>
            <p class="text-muted small">Claim it to start the trip. The admin will see you as the driver.</p>
            <form method="POST" action="<?php echo e(route('logistics.claim', $delivery)); ?>"><?php echo csrf_field(); ?><button class="btn-orange btn-lg" type="submit">Claim this delivery</button></form>
        </div>

    
    <?php elseif(! $delivery->trip_started_at): ?>
        <div class="card-white">
            <h6>Ready for pickup</h6>
            <p class="text-muted small">When you leave the warehouse, start the trip. Your live location is then shared with the admin until the delivery is confirmed.</p>
            <form method="POST" action="<?php echo e(route('logistics.start', $delivery)); ?>" id="startForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="latitude" id="startLat">
                <input type="hidden" name="longitude" id="startLng">
                <input type="hidden" name="accuracy" id="startAcc">
                <button class="btn-blue btn-lg w-100" type="submit" id="startBtn">▶ Start trip</button>
            </form>
        </div>

    
    <?php elseif($delivery->hasProof()): ?>
        <div class="card-white">
            <h6>💳 Waiting for online payment</h6>
            <p class="text-muted small">Proof of delivery is saved. The delivery is marked delivered once PayMongo confirms the payment of <?php echo e($symbol); ?><?php echo e(number_format((float) $delivery->amount, 2)); ?>.</p>
            <form method="POST" action="<?php echo e(route('logistics.pay', $delivery)); ?>"><?php echo csrf_field(); ?><button class="btn-blue btn-lg" type="submit">Open payment checkout</button></form>
        </div>

    
    <?php else: ?>
        <div class="card-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="share-chip off" id="shareChip">📡 Starting live location…</span>
            <span class="small text-muted" id="shareDetail">Keep this screen open while driving.</span>
        </div>

        <form method="POST" action="<?php echo e(route('logistics.verify')); ?>" enctype="multipart/form-data" id="proofForm" class="steps">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="delivery_id" value="<?php echo e($delivery->id); ?>">
            <input type="hidden" name="scanned_item_code" id="scannedCode">
            <input type="hidden" name="latitude" id="proofLat">
            <input type="hidden" name="longitude" id="proofLng">
            <input type="hidden" name="accuracy" id="proofAcc">

            <div class="step" id="step1">
                <div class="num">1</div>
                <div class="body">
                    <h6>Scan the item QR</h6>
                    <div class="hint">Scan the label on the item you are handing over. It must match this delivery.</div>
                    <div id="scanResult" class="mb-2 fw-semibold"></div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn-blue" id="scanBtn">Scan with camera</button>
                        <button type="button" class="btn-ghost" id="scanPhotoBtn">Scan from photo</button>
                    </div>
                    <input type="file" id="scanPhotoInput" accept="image/*" capture="environment" hidden>
                    <div id="proofScanner" hidden></div>
                    <div id="scanFileHost" hidden></div>
                    <div class="mt-2"><button type="button" class="link-btn" id="manualToggle">Can't scan? Type the item code</button></div>
                    <div id="manualBox" class="mt-2" hidden>
                        <div class="input-group" style="max-width:340px">
                            <input type="text" id="manualCode" class="form-control" placeholder="e.g. ITM-00001" autocomplete="off">
                            <button type="button" class="btn-ghost" id="manualUse">Use</button>
                        </div>
                    </div>
                    <div id="scanMsg" class="small text-muted mt-2" role="status"></div>
                </div>
            </div>

            <div class="step" id="step2">
                <div class="num">2</div>
                <div class="body">
                    <h6>Take a photo of the location</h6>
                    <div class="hint">Photograph where you are leaving the item (door, receiver, shelf). The photo is saved as proof.</div>
                    <button type="button" class="btn-blue" id="photoBtn">📷 Take photo</button>
                    <input type="file" name="proof_photo" id="photoInput" accept="image/*" capture="environment" hidden>
                    <img id="photoPreview" class="preview" alt="Proof photo preview">
                </div>
            </div>

            <div class="step" id="step3">
                <div class="num">3</div>
                <div class="body">
                    <h6>Confirm your location</h6>
                    <div class="hint">Your GPS position is stamped on the proof together with the time.</div>
                    <button type="button" class="btn-ghost" id="gpsBtn">📍 Get my location</button>
                    <div class="gps-line" id="gpsLine"></div>
                </div>
            </div>

            <button type="submit" class="btn-green btn-lg w-100" id="confirmBtn" disabled>Confirm delivery</button>
            <div class="small text-muted text-center" id="confirmHint">Complete the 3 steps above to confirm.</div>
        </form>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<?php if($isMine && ! $delivery->hasProof() && $stage !== 'delivered'): ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const CFG = <?php echo json_encode($cfg, 15, 512) ?>;
    const $ = function (id) { return document.getElementById(id); };

    /* ---------- Start trip: attach a GPS fix if we can get one quickly ---------- */
    const startForm = $('startForm');
    if (startForm) {
        startForm.addEventListener('submit', async function (e) {
            if (startForm.dataset.ready) return;
            e.preventDefault();
            $('startBtn').disabled = true;
            $('startBtn').textContent = 'Starting…';
            try {
                const p = await WL.getPosition({ timeout: 6000 });
                $('startLat').value = p.lat;
                $('startLng').value = p.lng;
                $('startAcc').value = Math.round(p.accuracy || 0);
            } catch (err) { /* start anyway, tracking will begin on the next screen */ }
            startForm.dataset.ready = '1';
            startForm.submit();
        });
    }

    const proofForm = $('proofForm');
    if (!proofForm) return;

    /* ---------- Live location sharing (admin sees this on the map) ---------- */
    const chip = $('shareChip');
    const detail = $('shareDetail');
    let lastSent = 0;
    let latest = null;

    function setChip(ok, text) {
        chip.className = 'share-chip' + (ok ? '' : ' off');
        chip.textContent = text;
    }

    function useFix(p) {
        latest = { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy, speed: p.coords.speed };
        const now = Date.now();
        if (now - lastSent >= 6000) {
            lastSent = now;
            WL.postJson(CFG.ping, { latitude: latest.lat, longitude: latest.lng, accuracy: latest.accuracy, speed: latest.speed })
                .then(function (r) {
                    if (r.ok) {
                        setChip(true, '📡 Sharing live location');
                        detail.textContent = 'Last sent ' + new Date().toLocaleTimeString();
                    } else {
                        setChip(false, '📡 Not reaching the server');
                    }
                })
                .catch(function () { setChip(false, '📡 Offline · retrying'); });
        }
        if (!$('proofLat').value) { applyFix(latest); }
    }

    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(useFix, function (err) {
            setChip(false, '📡 Location off');
            detail.textContent = WL.geoMessage(err);
        }, { enableHighAccuracy: true, maximumAge: 4000, timeout: 20000 });
    } else {
        setChip(false, '📡 GPS not supported');
    }

    if ('wakeLock' in navigator) {
        const lock = function () { navigator.wakeLock.request('screen').catch(function () {}); };
        lock();
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') lock(); });
    }

    /* ---------- Proof state ---------- */
    const state = { code: '', photo: false, gps: false };

    function refresh() {
        $('step1').classList.toggle('done', !!state.code);
        $('step2').classList.toggle('done', state.photo);
        $('step3').classList.toggle('done', state.gps);
        const gpsOk = state.gps || !CFG.requireGps;
        const ready = !!state.code && state.photo && gpsOk;
        $('confirmBtn').disabled = !ready;
        $('confirmHint').textContent = ready ? 'Everything is ready. Confirm to save the proof.' : 'Complete the 3 steps above to confirm.';
    }

    /* Step 1: QR */
    function setCode(code) {
        state.code = code;
        $('scannedCode').value = code;
        $('scanResult').textContent = code ? '✓ Scanned: ' + code : '';
        $('scanMsg').textContent = '';
        refresh();
    }

    let scanner = null;
    $('scanBtn').addEventListener('click', async function () {
        const box = $('proofScanner');
        if (scanner) {
            await WL.stopScan(scanner);
            scanner = null;
            box.hidden = true;
            this.textContent = 'Scan with camera';
            return;
        }
        box.hidden = false;
        this.textContent = 'Stop camera';
        $('scanMsg').textContent = 'Point the camera at the item QR label…';
        const btn = this;
        scanner = await WL.startScan('proofScanner', function (code) {
            scanner = null;
            box.hidden = true;
            btn.textContent = 'Scan with camera';
            setCode(code);
        }, function (message) {
            scanner = null;
            box.hidden = true;
            btn.textContent = 'Scan with camera';
            $('scanMsg').textContent = message;
        });
    });

    $('scanPhotoBtn').addEventListener('click', function () { $('scanPhotoInput').click(); });
    $('scanPhotoInput').addEventListener('change', async function () {
        const file = this.files && this.files[0];
        if (!file) return;
        $('scanMsg').textContent = 'Reading QR from photo…';
        try {
            setCode(await WL.scanFile('scanFileHost', file));
        } catch (err) {
            $('scanMsg').textContent = 'No QR code found in that photo. Move closer and try again.';
        }
        this.value = '';
    });

    $('manualToggle').addEventListener('click', function () { $('manualBox').hidden = !$('manualBox').hidden; });
    $('manualUse').addEventListener('click', function () {
        const code = WL.parseCode($('manualCode').value);
        if (code) setCode(code);
    });

    /* Step 2: photo (compressed in the browser before upload) */
    $('photoBtn').addEventListener('click', function () { $('photoInput').click(); });
    $('photoInput').addEventListener('change', async function () {
        const file = this.files && this.files[0];
        if (!file) return;
        const small = await WL.compressImage(file);
        try {
            const transfer = new DataTransfer();
            transfer.items.add(small);
            this.files = transfer.files;
        } catch (err) { /* keep the original file */ }
        const preview = $('photoPreview');
        preview.src = URL.createObjectURL(small);
        preview.style.display = 'block';
        state.photo = true;
        refresh();
    });

    /* Step 3: GPS */
    function applyFix(fix) {
        $('proofLat').value = fix.lat;
        $('proofLng').value = fix.lng;
        $('proofAcc').value = Math.round(fix.accuracy || 0);
        $('gpsLine').innerHTML = '<span class="gps-ok">📍 Location locked</span> · ' + fix.lat.toFixed(5) + ', ' + fix.lng.toFixed(5) + ' (±' + Math.round(fix.accuracy || 0) + ' m)';
        state.gps = true;
        refresh();
    }

    $('gpsBtn').addEventListener('click', async function () {
        $('gpsLine').textContent = 'Getting your location…';
        try {
            applyFix(await WL.getPosition({ maximumAge: 0 }));
        } catch (err) {
            state.gps = false;
            $('gpsLine').innerHTML = '<span class="gps-bad">' + WL.esc(err.message) + '</span>';
            refresh();
        }
    });

    if (latest) { applyFix(latest); }
    refresh();

    /* Always stamp the proof with a fresh GPS fix taken at the moment of confirmation. */
    proofForm.addEventListener('submit', async function (e) {
        if (proofForm.dataset.ready) return;
        e.preventDefault();
        const btn = $('confirmBtn');
        btn.disabled = true;
        btn.textContent = 'Locking your location…';
        try {
            applyFix(await WL.getPosition({ maximumAge: 0, timeout: 10000 }));
        } catch (err) {
            if (CFG.requireGps) {
                state.gps = false;
                $('proofLat').value = '';
                $('proofLng').value = '';
                $('gpsLine').innerHTML = '<span class="gps-bad">' + WL.esc(err.message) + '</span>';
                btn.textContent = 'Confirm delivery';
                refresh();
                return;
            }
        }
        btn.disabled = true;
        btn.textContent = 'Saving proof…';
        proofForm.dataset.ready = '1';
        proofForm.submit();
    });
})();
</script>
<?php endif; ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\gelom\Desktop\System\warelog-erp\resources\views/logistics-show.blade.php ENDPATH**/ ?>
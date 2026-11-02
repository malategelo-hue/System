/*
 * WareLog helpers: QR rendering, QR scanning, GPS, photo compression and live polling.
 * Plain JavaScript on purpose (no build step). Loaded by layouts/navigation.blade.php.
 */
(function () {
    'use strict';

    const WL = (window.WL = {});

    WL.csrf = function () {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    };

    WL.esc = function (value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    /* ---------- QR codes (generated in the browser, nothing is sent to a third party) ---------- */

    WL.parseCode = function (text) {
        return String(text || '').split('|')[0].trim();
    };

    WL.qrSvg = function (text, px) {
        if (typeof window.qrcode !== 'function') {
            throw new Error('QR library not loaded');
        }
        const qr = window.qrcode(0, 'M');
        qr.addData(String(text));
        qr.make();
        const n = qr.getModuleCount();
        const quiet = 2;
        let d = '';
        for (let r = 0; r < n; r++) {
            for (let c = 0; c < n; c++) {
                if (qr.isDark(r, c)) {
                    d += 'M' + (c + quiet) + ',' + (r + quiet) + 'h1v1h-1z';
                }
            }
        }
        const size = n + quiet * 2;
        const dim = px ? ' width="' + px + '" height="' + px + '"' : '';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '"' + dim +
            ' shape-rendering="crispEdges" role="img" aria-label="QR code ' + WL.esc(text) + '">' +
            '<rect width="100%" height="100%" fill="#fff"/><path d="' + d + '" fill="#000"/></svg>';
    };

    WL.renderQr = function (root) {
        (root || document).querySelectorAll('[data-qr]').forEach(function (el) {
            const px = parseInt(el.dataset.qrSize || '120', 10);
            try {
                el.innerHTML = WL.qrSvg(el.dataset.qr, px);
                el.classList.add('qr-box');
            } catch (e) {
                el.textContent = el.dataset.qr;
            }
        });
    };

    /* ---------- QR scanning (camera, photo fallback) ---------- */

    WL.startScan = async function (elementId, onCode, onFail) {
        if (!window.Html5Qrcode) {
            if (onFail) onFail('The camera scanner could not load. Check your connection, or type the code instead.');
            return null;
        }
        const scanner = new window.Html5Qrcode(elementId);
        try {
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 230, height: 230 } },
                async function (text) {
                    try { await scanner.stop(); scanner.clear(); } catch (e) { /* already stopped */ }
                    onCode(WL.parseCode(text));
                },
                function () { /* ignore frames without a QR */ }
            );
            return scanner;
        } catch (e) {
            if (onFail) onFail('Camera unavailable. Camera scanning needs HTTPS (or localhost) and camera permission. Use "Scan from photo" or type the code.');
            return null;
        }
    };

    WL.stopScan = async function (scanner) {
        if (!scanner) return;
        try { await scanner.stop(); scanner.clear(); } catch (e) { /* ignore */ }
    };

    WL.scanFile = async function (elementId, file) {
        if (!window.Html5Qrcode) throw new Error('Scanner not loaded');
        const scanner = new window.Html5Qrcode(elementId);
        try {
            const text = await scanner.scanFile(file, false);
            return WL.parseCode(text);
        } finally {
            try { scanner.clear(); } catch (e) { /* ignore */ }
        }
    };

    /* ---------- GPS ---------- */

    WL.geoMessage = function (err) {
        if (err && err.code === 1) return 'Location permission denied. Allow location access (it needs HTTPS or localhost).';
        if (err && err.code === 2) return 'Location unavailable. Move to an open area and retry.';
        return 'Location request timed out. Try again.';
    };

    WL.getPosition = function (options) {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(new Error('This device does not support GPS location.'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (p) {
                    resolve({ lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy, speed: p.coords.speed });
                },
                function (err) { reject(new Error(WL.geoMessage(err))); },
                Object.assign({ enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }, options || {})
            );
        });
    };

    /* ---------- Photo compression (phone photos are several MB) ---------- */

    WL.compressImage = function (file, maxSide, quality) {
        maxSide = maxSide || 1280;
        quality = quality || 0.8;
        return new Promise(function (resolve) {
            if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                resolve(file);
                return;
            }
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = function () {
                const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
                const w = Math.max(1, Math.round(img.width * scale));
                const h = Math.max(1, Math.round(img.height * scale));
                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                URL.revokeObjectURL(url);
                canvas.toBlob(function (blob) {
                    resolve(blob ? new File([blob], 'proof.jpg', { type: 'image/jpeg' }) : file);
                }, 'image/jpeg', quality);
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    };

    /* ---------- Live updates: short polling (works on any PHP host, no websocket server needed) ---------- */

    WL.poll = function (url, onData, options) {
        options = options || {};
        const interval = options.interval || 4000;
        let stopped = false;
        let timer = null;
        let busy = false;

        function state(name) {
            if (options.onState) options.onState(name);
        }

        async function tick() {
            if (stopped || busy) return;
            busy = true;
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
                const type = response.headers.get('content-type') || '';
                if (response.redirected || type.indexOf('json') === -1) {
                    state('session');
                } else if (response.ok) {
                    onData(await response.json());
                    state('live');
                } else {
                    state('error');
                }
            } catch (e) {
                state('offline');
            }
            busy = false;
            if (!stopped) {
                timer = setTimeout(tick, document.hidden ? interval * 3 : interval);
            }
        }

        tick();

        return {
            stop: function () { stopped = true; clearTimeout(timer); },
            now: function () { clearTimeout(timer); busy = false; tick(); },
        };
    };

    WL.postJson = function (url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': WL.csrf() },
            credentials: 'same-origin',
            body: JSON.stringify(payload || {}),
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        WL.renderQr(document);
    });
})();

<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

        <title><?php echo e(config('app.name', 'Laravel')); ?></title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:600,700|inter:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>

        <style>
            :root {
                --auth-card: #fff;
                --auth-ink: #0f172a;
                --auth-muted: #64748b;
                --auth-label: #475569;
                --auth-accent: #f59e0b;
                --auth-border: #e2e8f0;
                --auth-primary: #2563eb;
                --auth-primary-dark: #1d4ed8;
            }
            .auth-body {
                margin: 0;
                min-height: 100vh;
                font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
                color: var(--auth-ink);
                background:
                    radial-gradient(900px 500px at 15% 10%, rgba(37, 99, 235, .24), transparent 60%),
                    radial-gradient(800px 500px at 90% 90%, rgba(30, 58, 95, .55), transparent 60%),
                    #0b1220;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px 16px;
                box-sizing: border-box;
            }
            .auth-card {
                width: 100%;
                max-width: 410px;
                background: var(--auth-card);
                border-radius: 14px;
                padding: 34px 32px 30px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, .3);
                box-sizing: border-box;
            }
            .auth-eyebrow {
                text-align: center;
                font-size: 12px;
                letter-spacing: .3em;
                text-transform: uppercase;
                color: var(--auth-accent);
                margin: 0 0 6px;
            }
            .auth-title {
                text-align: center;
                font-family: 'Cormorant Garamond', Georgia, 'Times New Roman', serif;
                font-weight: 600;
                font-size: 38px;
                line-height: 1.1;
                margin: 0 0 8px;
                color: var(--auth-ink);
            }
            .auth-sub {
                text-align: center;
                font-size: 14px;
                color: var(--auth-muted);
                margin: 0 0 26px;
            }
            .auth-card label {
                display: block;
                font-size: 11px;
                font-weight: 500;
                letter-spacing: .12em;
                text-transform: uppercase;
                color: var(--auth-label);
                margin-bottom: 6px;
            }
            .auth-card input[type="text"],
            .auth-card input[type="email"],
            .auth-card input[type="password"],
            .auth-card input[type="tel"] {
                display: block;
                width: 100%;
                box-sizing: border-box;
                height: 44px;
                padding: 0 12px;
                font: inherit;
                font-size: 15px;
                color: var(--auth-ink);
                background: #fff;
                border: 1px solid var(--auth-border);
                border-radius: 8px;
                box-shadow: none;
                outline: none;
                transition: border-color .15s, box-shadow .15s;
            }
            .auth-card input:focus {
                border-color: var(--auth-primary);
                box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
            }
            .auth-row {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                margin-bottom: 6px;
            }
            .auth-row label { margin-bottom: 0; }
            .auth-field { margin-bottom: 18px; }
            .auth-card a {
                color: var(--auth-primary);
                text-decoration: underline;
            }
            .auth-card a:hover { color: var(--auth-primary-dark); }
            .auth-forgot { font-size: 13px; font-weight: 600; letter-spacing: 0; text-transform: none; }
            .auth-btn,
            .auth-card button[type="submit"] {
                display: block;
                width: 100%;
                height: 44px;
                margin-top: 22px;
                border: 0;
                border-radius: 10px;
                background: var(--auth-primary);
                color: #fff;
                font: inherit;
                font-size: 15px;
                font-weight: 600;
                letter-spacing: 0;
                text-transform: none;
                cursor: pointer;
                transition: background .15s;
                justify-content: center;
            }
            .auth-btn:hover,
            .auth-card button[type="submit"]:hover { background: var(--auth-primary-dark); }
            .auth-foot {
                text-align: center;
                font-size: 14px;
                color: var(--auth-ink);
                margin: 22px 0 0;
            }
            .auth-error, .auth-card ul.text-red-600 {
                list-style: none;
                margin: 6px 0 0;
                padding: 0;
                font-size: 13px;
                color: #b42318;
            }
            .auth-status, .auth-card .text-green-600 {
                font-size: 13px;
                color: #2f6b3f;
                background: #e9f3ea;
                border: 1px solid #cfe5d2;
                border-radius: 3px;
                padding: 10px 12px;
                margin-bottom: 18px;
            }
            .auth-card .text-gray-600 { color: var(--auth-muted); }
        </style>
    </head>
    <body class="auth-body">
        <div class="auth-card">
            <?php echo e($slot); ?>

        </div>
    </body>
</html>
<?php /**PATH C:\Users\gelom\Desktop\System\warelog-erp\resources\views/layouts/guest.blade.php ENDPATH**/ ?>
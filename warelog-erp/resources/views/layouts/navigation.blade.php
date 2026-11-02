<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $systemSettings = $systemSettings ?? \App\Models\SystemSetting::values();
        $authUser = Auth::user();
        $isAdmin = $authUser && $authUser->role === 'admin';
        $initials = $authUser ? strtoupper(mb_substr($authUser->name, 0, 1)) : '?';
        $ico = function (string $name): string {
            $paths = [
                'grid' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
                'box' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3.3 7.5L12 12.5l8.7-5"/><path d="M12 22V12.5"/>',
                'pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1116 0z"/><circle cx="12" cy="10" r="3"/>',
                'truck' => '<path d="M1 4h14v12H1z"/><path d="M15 8h4l4 4v4h-8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
                'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
                'wallet' => '<path d="M20 12V8H6a2 2 0 010-4h12v4"/><path d="M4 6v12a2 2 0 002 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4z"/>',
                'file' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/>',
                'sliders' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
                'in' => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
                'out' => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
                'logout' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
                'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            ];

            return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
        };
    @endphp
    <title>{{ $systemSettings['system_name'] }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root{
            --bg:#f1f5f9;--surface:#fff;--surface-2:#f8fafc;--text:#0f172a;--muted:#64748b;--border:#e2e8f0;
            --side:#0b1220;--side-text:#94a3b8;--side-active:#1e293b;
            --accent:#f59e0b;--primary:#2563eb;--ok:#16a34a;--warn:#d97706;--bad:#dc2626;
            --radius:14px;--shadow:0 1px 2px rgba(15,23,42,.05),0 6px 20px rgba(15,23,42,.05);
        }
        body.dark-mode{--bg:#0a0f1c;--surface:#111a2e;--surface-2:#0e1628;--text:#e5e7eb;--muted:#94a3b8;--border:#1f2a44;--side:#070b14;--side-active:#16213a;--shadow:0 1px 2px rgba(0,0,0,.4)}
        *{box-sizing:border-box}
        html,body{margin:0;padding:0}
        body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;font-size:14px;-webkit-font-smoothing:antialiased}
        a{color:var(--primary)}
        .app{display:flex;min-height:100vh}

        /* SIDEBAR */
        .sidebar{width:248px;background:var(--side);color:#fff;display:flex;flex-direction:column;flex-shrink:0;position:sticky;top:0;height:100vh;z-index:1040}
        .brand{display:flex;align-items:center;gap:12px;padding:22px 20px 18px}
        .brand-logo{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#f59e0b,#f97316);display:grid;place-items:center;color:#0b1220;flex:none}
        .brand-name{font-weight:700;font-size:15px;line-height:1.2;letter-spacing:.2px}
        .brand-sub{font-size:11px;color:var(--side-text);margin-top:2px;line-height:1.3}
        .nav-label{font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;color:#52607a;padding:14px 22px 6px;font-weight:600}
        .sidebar nav{flex:1;overflow-y:auto;padding:0 12px 12px}
        .sidebar nav a{display:flex;align-items:center;gap:12px;color:var(--side-text);padding:11px 12px;margin:2px 0;border-radius:10px;text-decoration:none;font-weight:500;font-size:13.5px;transition:background .15s,color .15s}
        .sidebar nav a:hover{background:var(--side-active);color:#fff}
        .sidebar nav a.active{background:var(--side-active);color:#fff;box-shadow:inset 3px 0 0 var(--accent)}
        .sidebar nav a svg{flex:none}
        .live-dot{width:8px;height:8px;border-radius:50%;background:#22c55e;margin-left:auto;box-shadow:0 0 0 0 rgba(34,197,94,.7);animation:pulse 1.8s infinite}
        @keyframes pulse{70%{box-shadow:0 0 0 8px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
        .side-foot{padding:14px 16px;border-top:1px solid rgba(255,255,255,.06)}
        .user-chip{display:flex;align-items:center;gap:10px;margin-bottom:10px}
        .avatar{width:34px;height:34px;border-radius:50%;background:#1e293b;color:#fbbf24;display:grid;place-items:center;font-weight:700;flex:none}
        .user-chip .nm{font-weight:600;font-size:13px;line-height:1.2;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .user-chip .rl{font-size:11px;color:var(--side-text)}
        .logout-btn{display:flex;align-items:center;gap:10px;width:100%;background:transparent;border:1px solid rgba(255,255,255,.1);color:var(--side-text);padding:9px 12px;border-radius:10px;font-size:13px;font-weight:500;cursor:pointer}
        .logout-btn:hover{background:var(--side-active);color:#fff}

        /* MAIN */
        .main{flex:1;min-width:0;display:flex;flex-direction:column}
        .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 28px;background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:1030}
        .topbar .ctx{display:flex;flex-direction:column;line-height:1.25}
        .topbar .ctx strong{font-size:14px}
        .topbar .ctx span{font-size:12px;color:var(--muted)}
        .menu-btn{display:none;background:none;border:1px solid var(--border);border-radius:10px;padding:7px;color:var(--text)}
        .portal-badge{display:inline-flex;align-items:center;padding:5px 11px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.07em;white-space:nowrap;background:#fef3c7;color:#92400e}
        .portal-user .portal-badge{background:#dbeafe;color:#1e40af}
        .content{flex:1;padding:26px 28px 40px;width:100%;max-width:1500px}
        .scrim{display:none}

        /* CONTENT PRIMITIVES (kept compatible with the existing pages) */
        .page-title{font-size:22px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px;letter-spacing:-.2px}
        .page-sub{color:var(--muted);margin:-8px 0 18px}
        .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);text-align:left}
        .stat-card .num{font-size:26px;font-weight:700;margin-top:4px;letter-spacing:-.5px}
        .stat-card .label{font-size:12px;color:var(--muted);font-weight:500}
        .card-white{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:18px}
        .card-white h5,.card-white h6{font-weight:600}
        .btn-green,.btn-red,.btn-orange,.btn-blue,.btn-ghost{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid transparent;border-radius:10px;padding:9px 16px;font-size:13.5px;font-weight:600;cursor:pointer;text-decoration:none;transition:filter .15s,transform .05s;line-height:1.2}
        .btn-green{background:#16a34a;color:#fff}.btn-green:hover{background:#15803d;color:#fff}
        .btn-red{background:#fee2e2;color:#b91c1c}.btn-red:hover{background:#fecaca;color:#991b1b}
        .btn-orange{background:#f59e0b;color:#1f2937}.btn-orange:hover{filter:brightness(.95);color:#111}
        .btn-blue{background:#2563eb;color:#fff}.btn-blue:hover{background:#1d4ed8;color:#fff}
        .btn-ghost{background:var(--surface);color:var(--text);border-color:var(--border)}.btn-ghost:hover{background:var(--surface-2);color:var(--text)}
        .btn-green:active,.btn-blue:active,.btn-orange:active{transform:translateY(1px)}
        .btn-green[disabled],.btn-blue[disabled],.btn-orange[disabled]{opacity:.5;cursor:not-allowed}
        .btn-lg{padding:14px 20px;font-size:15px;border-radius:12px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        table th{background:transparent;color:var(--muted);padding:10px 12px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;border-bottom:1px solid var(--border);white-space:nowrap}
        table td{padding:12px;border-bottom:1px solid var(--border);vertical-align:middle}
        table tbody tr:hover td{background:var(--surface-2)}
        .badge-good,.badge-low,.badge-out,.badge-active,.badge-transit,.badge-delivered,.badge-dispatch,.pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:600;white-space:nowrap}
        .badge-good,.badge-active,.badge-delivered,.pill-done{background:#dcfce7;color:#166534}
        .badge-low,.pill-pay{background:#fef3c7;color:#92400e}
        .badge-out{background:#fee2e2;color:#991b1b}
        .badge-transit,.pill-way{background:#dbeafe;color:#1e40af}
        .badge-dispatch,.pill-ready{background:#ffedd5;color:#9a3412}
        .pill-muted{background:var(--surface-2);color:var(--muted);border:1px solid var(--border)}
        .form-control,.form-select{font-size:13.5px;border-radius:10px;border-color:var(--border);padding:.55rem .75rem}
        .form-control:focus,.form-select:focus{border-color:#93c5fd;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
        .form-label,.card-white label{font-weight:500;font-size:12.5px;color:var(--muted);margin-bottom:4px}
        .modal-content{border-radius:16px;border:0}
        .alert{border-radius:12px;border:0;font-size:13.5px}
        .text-muted{color:var(--muted)!important}
        .toggle{width:40px;height:20px;background:#ccc;border-radius:10px;position:relative;display:inline-block;cursor:pointer}
        .toggle.on{background:#28a745}
        .toggle::after{content:'';position:absolute;width:16px;height:16px;background:white;border-radius:50%;top:2px;left:2px;transition:0.2s}
        .toggle.on::after{left:22px}
        .settings-card{background:var(--surface);border-radius:var(--radius);padding:25px;border:1px solid var(--border);height:100%}
        .toggle-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)}
        .toggle-switch{position:relative;width:48px;height:24px;flex:none}
        .toggle-switch input{position:absolute;opacity:0;width:1px;height:1px}
        .toggle-slider{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:24px;transition:.3s}
        .toggle-slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
        .toggle-switch input:checked+.toggle-slider{background:#22c55e}
        .toggle-switch input:checked+.toggle-slider:before{transform:translateX(24px)}
        .toggle-switch input:focus-visible+.toggle-slider{outline:3px solid #2563eb;outline-offset:2px}
        .btn-save{background:#16a34a;color:#fff;border:0;padding:10px 24px;border-radius:10px;font-weight:600}
        .btn-reset{background:#f59e0b;color:#1f2937;border:0;padding:10px 24px;border-radius:10px;font-weight:600}
        .dark-mode .stat-card,.dark-mode .card-white,.dark-mode .settings-card,.dark-mode .report-card,.dark-mode .view-panel{background:var(--surface);color:var(--text);border-color:var(--border)}
        .dark-mode .stat-label,.dark-mode .report-card p{color:var(--muted)!important}
        .dark-mode .form-control,.dark-mode .form-select{background-color:#0b1220;color:var(--text);border-color:#2b3a5c}
        .dark-mode .modal-content{background:var(--surface);color:var(--text)}
        .dark-mode .btn-red{background:#451a1a;color:#fca5a5}
        .dark-mode .pill-muted{background:#0e1628}
        .qr-box{display:inline-block;background:#fff;border-radius:10px;padding:4px;line-height:0}
        .qr-box svg{display:block}
        .hero-banner{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;background:linear-gradient(135deg,#0f172a,#1e3a5f);color:#fff;border-radius:var(--radius);padding:16px 20px;margin-bottom:18px}
        .hero-banner .btn-ghost{background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.2)}

        /* DRIVER BOTTOM TAB BAR (phones) */
        .tabbar{display:none}

        @media (max-width: 991.98px){
            .menu-btn{display:inline-flex}
            .sidebar{position:fixed;left:0;top:0;transform:translateX(-100%);transition:transform .2s;box-shadow:0 0 40px rgba(0,0,0,.4)}
            body.nav-open .sidebar{transform:none}
            body.nav-open .scrim{display:block;position:fixed;inset:0;background:rgba(2,6,23,.5);z-index:1035}
            .topbar{padding:12px 16px}
            .content{padding:16px 14px 96px}
            .content table{display:block;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap}
            .portal-user .menu-btn{display:none}
            .portal-user .sidebar{display:none}
            .portal-user .tabbar{display:flex;position:fixed;bottom:0;left:0;right:0;z-index:1030;background:var(--surface);border-top:1px solid var(--border);padding:6px 6px calc(6px + env(safe-area-inset-bottom))}
            .tabbar a,.tabbar button{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;padding:7px 2px;border:0;background:none;color:var(--muted);font-size:10.5px;font-weight:600;text-decoration:none;border-radius:10px}
            .tabbar a.active{color:var(--primary);background:var(--surface-2)}
        }
    </style>
    @stack('head')
</head>
<body class="{{ $systemSettings['dark_mode'] === '1' ? 'dark-mode ' : '' }}{{ $isAdmin ? 'portal-admin' : 'portal-user' }}">
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-logo">{!! $ico('box') !!}</div>
            <div>
                <div class="brand-name">{{ $systemSettings['system_name'] }}</div>
                <div class="brand-sub">{{ $systemSettings['company_name'] }} · {{ $systemSettings['warehouse_name'] }}</div>
            </div>
        </div>
        <nav>
            @if($isAdmin)
                <div class="nav-label">Overview</div>
                <a href="/dashboard" class="{{ Request::is('dashboard') ? 'active' : '' }}">{!! $ico('grid') !!} Dashboard</a>
                <a href="/tracking" class="{{ Request::is('tracking*') ? 'active' : '' }}">{!! $ico('pin') !!} Live Tracking <span class="live-dot" title="Realtime"></span></a>
                <div class="nav-label">Warehouse</div>
                <a href="/inventory" class="{{ Request::is('inventory', 'inventory/edit/*') ? 'active' : '' }}">{!! $ico('box') !!} Inventory</a>
                <a href="/inventory/stock-in" class="{{ Request::is('inventory/stock-in') ? 'active' : '' }}">{!! $ico('in') !!} Stock In</a>
                <a href="/inventory/stock-out" class="{{ Request::is('inventory/stock-out') ? 'active' : '' }}">{!! $ico('out') !!} Stock Out</a>
                <a href="/deliveries" class="{{ Request::is('deliveries*') ? 'active' : '' }}">{!! $ico('truck') !!} Deliveries</a>
                <a href="/team" class="{{ Request::is('team*') ? 'active' : '' }}">{!! $ico('users') !!} Drivers</a>
                <div class="nav-label">Finance &amp; system</div>
                <a href="/payments" class="{{ Request::is('payments*') ? 'active' : '' }}">{!! $ico('wallet') !!} Cash Wallet</a>
                <a href="/reports" class="{{ Request::is('reports') ? 'active' : '' }}">{!! $ico('file') !!} Reports</a>
                <a href="/settings" class="{{ Request::is('settings') ? 'active' : '' }}">{!! $ico('sliders') !!} Settings</a>
            @else
                <div class="nav-label">My work</div>
                <a href="/logistics" class="{{ Request::is('logistics*') ? 'active' : '' }}">{!! $ico('truck') !!} My Deliveries</a>
            @endif
        </nav>
        <div class="side-foot">
            <div class="user-chip">
                <div class="avatar">{{ $initials }}</div>
                <div style="min-width:0">
                    <div class="nm">{{ $authUser?->name }}</div>
                    <div class="rl">{{ $isAdmin ? 'Administrator' : 'Driver' }}</div>
                </div>
            </div>
            <form method="POST" action="{{ $isAdmin ? route('logout') : route('user.logout') }}">
                @csrf
                <button type="submit" class="logout-btn">{!! $ico('logout') !!} Logout</button>
            </form>
        </div>
    </aside>
    <div class="scrim" onclick="document.body.classList.remove('nav-open')"></div>

    <div class="main">
        <header class="topbar">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="menu-btn" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">{!! $ico('menu') !!}</button>
                <div class="ctx">
                    <strong>{{ $systemSettings['system_name'] }}</strong>
                    <span>{{ $systemSettings['warehouse_name'] }}</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="portal-badge">{{ $isAdmin ? 'ADMIN PORTAL' : 'DRIVER PORTAL' }}</span>
                @if($isAdmin)
                    <a href="/settings" class="text-decoration-none" style="color:var(--muted)" title="Settings">{!! $ico('sliders') !!}</a>
                @endif
            </div>
        </header>

        <main class="content">
            @if(session('success'))
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            @endif
            @yield('content')
        </main>
    </div>

    @unless($isAdmin)
        <nav class="tabbar" aria-label="Driver navigation">
            <a href="/logistics" class="active">{!! $ico('truck') !!}<span>My Deliveries</span></a>
            <form method="POST" action="{{ route('user.logout') }}" style="flex:1;display:flex">@csrf<button type="submit" style="flex:1">{!! $ico('logout') !!}<span>Logout</span></button></form>
        </nav>
    @endunless
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
<script src="{{ asset('js/warelog.js') }}"></script>
@stack('scripts')
</body>
</html>

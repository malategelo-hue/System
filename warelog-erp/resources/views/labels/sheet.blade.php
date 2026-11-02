<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR labels · {{ $item->code }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Inter,Arial,sans-serif;background:#f1f5f9;color:#0f172a}
        .bar{position:sticky;top:0;background:#0f172a;color:#fff;padding:12px 20px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;z-index:5}
        .bar form{display:flex;gap:8px;align-items:center}
        .bar input{width:84px;padding:7px 9px;border-radius:8px;border:0}
        .bar button,.bar a{background:#f59e0b;color:#111827;border:0;border-radius:8px;padding:8px 14px;font-weight:600;text-decoration:none;cursor:pointer;font-size:14px}
        .bar a.ghost{background:transparent;color:#cbd5e1;border:1px solid #334155}
        .sheet{display:grid;grid-template-columns:repeat(auto-fill,minmax(62mm,1fr));gap:4mm;padding:8mm}
        .label{background:#fff;border:1px dashed #94a3b8;border-radius:3mm;padding:3mm;display:flex;gap:3mm;align-items:center;break-inside:avoid;min-height:30mm}
        .label .qr{flex:none;line-height:0}
        .label .meta{min-width:0}
        .label .code{font-weight:800;font-size:14px;letter-spacing:.5px;word-break:break-all}
        .label .name{font-size:11.5px;margin-top:2px;line-height:1.25}
        .label .loc{font-size:10px;color:#475569;margin-top:3px}
        .label .brand{font-size:8.5px;color:#94a3b8;margin-top:3px;text-transform:uppercase;letter-spacing:.1em}
        @media print{
            body{background:#fff}
            .bar{display:none}
            .sheet{padding:0;gap:3mm}
            .label{border:1px dashed #cbd5e1}
        }
    </style>
</head>
<body>
    <div class="bar">
        <div><strong>{{ $item->code }}</strong> · {{ $item->name }}</div>
        <form method="GET" action="">
            <label for="copies">Labels to print</label>
            <input id="copies" type="number" name="copies" min="1" max="200" value="{{ $copies }}">
            <button type="submit">Update</button>
            <button type="button" onclick="window.print()">🖨️ Print</button>
            <a class="ghost" href="javascript:history.back()">Back</a>
        </form>
    </div>

    <div class="sheet">
        @for($i = 0; $i < $copies; $i++)
            <div class="label">
                <div class="qr" data-qr="{{ $item->qr_payload }}" data-qr-size="96"></div>
                <div class="meta">
                    <div class="code">{{ $item->code }}</div>
                    <div class="name">{{ $item->name }}</div>
                    <div class="loc">{{ $item->location }} · {{ $item->unit }}</div>
                    <div class="brand">{{ $systemSettings['company_name'] }}</div>
                </div>
            </div>
        @endfor
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
    <script src="{{ asset('js/warelog.js') }}"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - WareLog ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif}
        body{background:#e9e9e9;display:flex;min-height:100vh}
        .sidebar{width:220px;background:#1e2933;color:white;padding-top:20px;flex-shrink:0}
        .sidebar-logo{padding:0 20px 30px;font-size:18px;font-weight:bold;border-bottom:1px solid #334;padding-bottom:20px}
        .sidebar a{display:block;color:#ccc;padding:14px 20px;text-decoration:none}
        .sidebar a:hover{background:#2c3e50;color:white;border-left:4px solid #28a745}
        .main{flex:1;display:flex;flex-direction:column}
        .top-bar{background:#0f4d5c;color:white;padding:15px 25px;display:flex;justify-content:space-between;align-items:center;font-size:18px;font-weight:bold}
        .content{padding:25px;max-width:900px;margin:0 auto}
        @media (max-width: 767.98px) {
            body{flex-direction:column}
            .sidebar{width:100%;display:flex;flex-wrap:wrap;padding-top:0}
            .sidebar-logo{width:100%;padding:14px 16px;border-bottom:1px solid #334}
            .sidebar a{flex:1 1 140px;padding:12px 14px}
            .main{min-width:0}
            .top-bar{padding:12px 16px;font-size:16px}
            .content{width:100%;padding:16px}
        }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-logo">WARELOG ERP</div>
    <a href="/dashboard">📊 Dashboard</a>
    <a href="/inventory" style="background:#2c3e50;color:white;border-left:4px solid #28a745">📦 Inventory</a>
    <a href="/inventory/stock-in">📥 Stock In</a>
    <a href="/inventory/stock-out">📤 Stock Out</a>
    <a href="/locations">📍 Locations</a>
    <a href="/logistics">🚚 Logistics</a>
    <a href="/reports">📋 Reports</a>
    <a href="/settings">⚙️ Settings</a>
</div>
<div class="main">
    <div class="top-bar"><span>✏️ Edit Item</span><span>👤 Admin</span></div>
    <div class="content">
        <a href="/inventory" class="btn btn-link px-0 mb-3">← Back to Inventory</a>
        <div class="card">
            <div class="card-body">
                <h4 class="mb-4">Edit Item: {{ $item->code }}</h4>
                <form action="{{ route('inventory.update', $item->id) }}" method="POST">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Item Code *</label>
                            <input type="text" name="code" value="{{ $item->code }}" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Item Name *</label>
                            <input type="text" name="name" value="{{ $item->name }}" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Category *</label>
                            <select name="category" class="form-select" required>
                                <option value="Raw Materials" @selected($item->category=='Raw Materials')>Raw Materials</option>
                                <option value="Supplies" @selected($item->category=='Supplies')>Supplies</option>
                                <option value="Finished Goods" @selected($item->category=='Finished Goods')>Finished Goods</option>
                                <option value="Equipment" @selected($item->category=='Equipment')>Equipment</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Quantity *</label>
                            <input type="number" name="qty" value="{{ $item->qty }}" class="form-control" required min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Unit *</label>
                            <input type="text" name="unit" value="{{ $item->unit }}" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Unit Price *</label>
                            <input type="number" name="price" value="{{ $item->price }}" step="0.01" min="0" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Location *</label>
                            <select name="location" class="form-select" required>
                                <option value="Warehouse A" @selected($item->location=='Warehouse A')>Warehouse A</option>
                                <option value="Warehouse B" @selected($item->location=='Warehouse B')>Warehouse B</option>
                                <option value="Warehouse C" @selected($item->location=='Warehouse C')>Warehouse C</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-success">✅ Save Changes</button>
                        <a href="/inventory" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
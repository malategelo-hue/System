@extends('layouts.navigation')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="page-title mb-0">📍 Warehouse & Locations</div>
    <button class="btn-green">+ Add New Locations</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="stat-card"><div class="label">Total Locations</div><div class="num">3</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="label">Active</div><div class="num" style="color:#28a745">3</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="label">Total Items Stored</div><div class="num">1,245</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="label">Total Capacity</div><div class="num">5,000 slots</div></div></div>
</div>

<div class="card-white">
    <div class="d-flex gap-2 mb-3">
        <input type="text" class="form-control" placeholder="Search location name / code...">
        <button class="btn-blue">Search</button>
    </div>
    <table>
        <thead><tr><th>Code</th><th>Location</th><th>Full Address</th><th>Contact Person</th><th>Items Count</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
            <tr><td>WH-A</td><td>Warehouse A-Main</td><td>San Jose del Monte, Bulacan</td><td>Dhonelyn Pacuan</td><td>680</td><td><span class="badge-active">Active</span></td><td><a href="#">View / Edit</a><br><a href="#" style="color:#dc3545">Disable</a></td></tr>
            <tr><td>WH-B</td><td>Warehouse B-Storage</td><td>Calumpit, Bulacan</td><td>Nilfa Khayezelle Paglinawan</td><td>450</td><td><span class="badge-active">Active</span></td><td><a href="#">View / Edit</a><br><a href="#" style="color:#dc3545">Disable</a></td></tr>
            <tr><td>WH-C</td><td>Warehouse C-Finished Goods</td><td>Valenzuela, Metro Manila</td><td>Isabel Salenga</td><td>150</td><td><span class="badge-active">Active</span></td><td><a href="#">View / Edit</a><br><a href="#" style="color:#dc3545">Disable</a></td></tr>
        </tbody>
    </table>
</div>
@endsection
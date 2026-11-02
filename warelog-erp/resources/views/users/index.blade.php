@extends('layouts.navigation')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="page-title mb-0">🚚 Drivers</div>
</div>
<p class="page-sub">Drivers sign up for an account here, then wait for admin approval. Activate an account before assigning deliveries.</p>

@php($pendingCount = $users->where('is_active', false)->count())
@if($pendingCount > 0)
    <div class="alert alert-warning" role="alert">
        <strong>{{ $pendingCount }} driver{{ $pendingCount > 1 ? 's' : '' }} waiting for approval.</strong>
        Click <em>Approve / activate</em> sa ibaba para makapag-login sila at mabigyan ng deliveries.
    </div>
@endif

@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

<div class="card-white">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Vehicle</th><th>Active deliveries</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($users as $u)
                    <tr>
                        <td><strong>{{ $u->name }}</strong></td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->phone ?: '—' }}</td>
                        <td>{{ $u->vehicle ?: '—' }}</td>
                        <td>{{ $u->active_deliveries_count }}</td>
                        <td>{{ $u->deliveries_count }}</td>
                        <td>@if($u->is_active === false)<span class="badge-out">Pending / inactive</span>@else<span class="badge-good">Active</span>@endif</td>
                        <td class="text-nowrap">
                            <button type="button" class="btn-ghost" style="padding:5px 10px" data-bs-toggle="modal" data-bs-target="#edit{{ $u->id }}">Edit</button>
                            <form method="POST" action="{{ route('admin.users.toggle', $u) }}" class="d-inline">@csrf
                                <button type="submit" class="btn-ghost" style="padding:5px 10px">{{ $u->is_active === false ? 'Approve / activate' : 'Deactivate' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="d-inline" onsubmit="return confirm('Delete this account?')">@csrf
                                <button type="submit" class="btn-red" style="padding:5px 10px">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No drivers have signed up yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach($users as $u)
<div class="modal fade" id="edit{{ $u->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content p-4">
        <h5 class="mb-3">Edit {{ $u->name }}</h5>
        <form method="POST" action="{{ route('admin.users.update', $u) }}">
            @csrf
            <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $u->name }}" required></div>
            <div class="row g-3 mb-3">
                <div class="col-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ $u->phone }}"></div>
                <div class="col-6"><label class="form-label">Vehicle</label><input name="vehicle" class="form-control" value="{{ $u->vehicle }}"></div>
            </div>
            <div class="mb-3"><label class="form-label">New password (leave blank to keep)</label><input type="password" name="password" class="form-control" autocomplete="new-password"></div>
            <div class="d-flex justify-content-end gap-2"><button type="button" class="btn-red" data-bs-dismiss="modal">Cancel</button><button class="btn-green" type="submit">Save</button></div>
        </form>
    </div></div>
</div>
@endforeach

@endsection

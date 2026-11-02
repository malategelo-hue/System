@extends('layouts.navigation')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">👛 Admin Cash Wallet</h4>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<div class="alert alert-info" role="note">
    Cash Wallet is an in-system record, not a bank account or e-wallet. The wallet balance below includes COD cash only after an admin confirms receiving it. COD collected by drivers stays pending until remitted. Confirmed online payments are shown separately and settle to the PayMongo merchant account.
</div>

<div class="row g-3 mb-4">
    @forelse($totals as $total)
        @php($currencySymbol = $total->currency === 'USD' ? '$' : ($total->currency === 'PHP' ? '₱' : $total->currency.' '))
        <div class="col-md-4">
            <div class="stat-card h-100">
                <div class="label">Cash Wallet balance · {{ $total->currency }}</div>
                <div class="num text-success">{{ $currencySymbol }}{{ number_format((float) $total->cash_wallet, 2) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card h-100">
                <div class="label">COD cash with drivers · {{ $total->currency }}</div>
                <div class="num text-warning">{{ $currencySymbol }}{{ number_format((float) $total->cod_pending, 2) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card h-100">
                <div class="label">Online payments at PayMongo · {{ $total->currency }}</div>
                <div class="num text-primary">{{ $currencySymbol }}{{ number_format((float) $total->online_received, 2) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card h-100">
                <div class="label">Total payments collected · {{ $total->currency }}</div>
                <div class="num">{{ $currencySymbol }}{{ number_format((float) $total->total_collected, 2) }}</div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="stat-card text-muted">No payments have been recorded yet.</div>
        </div>
    @endforelse
</div>

<div class="card-white">
    <h5>Cash Wallet transactions</h5>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Collected</th>
                    <th>Dispatch</th>
                    <th>Item / Destination</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Wallet status</th>
                    <th>Cash receipt</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    @php($currencySymbol = $payment->currency === 'USD' ? '$' : ($payment->currency === 'PHP' ? '₱' : $payment->currency.' '))
                    <tr>
                        <td>{{ $payment->collected_at->format('M j, Y g:i A') }}</td>
                        <td>{{ $payment->delivery->reference }}</td>
                        <td>{{ $payment->delivery->item->name }} · {{ $payment->delivery->destination }}</td>
                        <td>{{ $payment->payment_method === 'paymongo' ? 'PayMongo' : 'Cash on Delivery' }}</td>
                        <td>{{ $currencySymbol }}{{ number_format((float) $payment->amount, 2) }}</td>
                        <td>
                            @if($payment->status === 'received')
                                <span class="badge bg-success">Received</span>
                            @else
                                <span class="badge bg-warning text-dark">COD with driver</span>
                            @endif
                        </td>
                        <td>
                            @if($payment->payment_method === 'paymongo')
                                PayMongo merchant account (online)
                            @elseif($payment->remitted_at)
                                {{ $payment->remitted_at->format('M j, Y g:i A') }}
                                @if($payment->remittedBy)
                                    <span class="d-block small text-muted">Received by {{ $payment->remittedBy->name }}</span>
                                @endif
                            @else
                                <form method="POST" action="{{ route('payments.receive-cod', $payment) }}">
                                    @csrf
                                    <button type="submit" class="btn-green">Add cash to wallet</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Transactions appear here after COD collection or PayMongo confirmation.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $payments->links() }}</div>
</div>
@endsection

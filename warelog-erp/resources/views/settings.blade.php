@extends('layouts.navigation')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">⚙️ System Settings</h5>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

@if(Auth::user()->role === 'admin')
    <form method="POST" action="{{ route('settings.save') }}">
        @csrf
        <div class="row g-4">
            <div class="col-lg-6">
                <section class="settings-card">
                    <h6 class="mb-3">🏢 Company & Warehouse</h6>
                    <div class="mb-3">
                        <label class="form-label" for="system_name">System name</label>
                        <input id="system_name" name="system_name" class="form-control" maxlength="50" required value="{{ old('system_name', $systemSettings['system_name']) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="company_name">Company name</label>
                        <input id="company_name" name="company_name" class="form-control" maxlength="100" required value="{{ old('company_name', $systemSettings['company_name']) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="warehouse_name">Warehouse name</label>
                        <input id="warehouse_name" name="warehouse_name" class="form-control" maxlength="50" required value="{{ old('warehouse_name', $systemSettings['warehouse_name']) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="address">Address</label>
                        <input id="address" name="address" class="form-control" maxlength="255" value="{{ old('address', $systemSettings['address']) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="contact_no">Contact number</label>
                        <input id="contact_no" name="contact_no" type="tel" class="form-control" maxlength="50" value="{{ old('contact_no', $systemSettings['contact_no']) }}">
                    </div>
                </section>
            </div>

            <div class="col-lg-6">
                <section class="settings-card">
                    <h6 class="mb-3">⚙️ Inventory & Display</h6>
                    <div class="mb-3">
                        <label class="form-label" for="currency">Currency</label>
                        <select id="currency" name="currency" class="form-select">
                            <option value="PHP" @selected(old('currency', $systemSettings['currency']) === 'PHP')>Philippine Peso (₱)</option>
                            <option value="USD" @selected(old('currency', $systemSettings['currency']) === 'USD')>US Dollar ($)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="low_stock_threshold">Low-stock threshold</label>
                        <input id="low_stock_threshold" name="low_stock_threshold" type="number" class="form-control" min="1" max="1000000" required value="{{ old('low_stock_threshold', $systemSettings['low_stock_threshold']) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="date_format">Date format</label>
                        <select id="date_format" name="date_format" class="form-select">
                            <option value="m/d/Y" @selected(old('date_format', $systemSettings['date_format']) === 'm/d/Y')>MM/DD/YYYY</option>
                            <option value="d/m/Y" @selected(old('date_format', $systemSettings['date_format']) === 'd/m/Y')>DD/MM/YYYY</option>
                            <option value="Y-m-d" @selected(old('date_format', $systemSettings['date_format']) === 'Y-m-d')>YYYY-MM-DD</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="timezone">Display timezone</label>
                        <select id="timezone" name="timezone" class="form-select">
                            @foreach(timezone_identifiers_list() as $timezone)
                                <option value="{{ $timezone }}" @selected(old('timezone', $systemSettings['timezone']) === $timezone)>{{ $timezone }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach([
                        'low_stock_alerts' => 'Low-stock alerts',
                        'auto_codes' => 'Generate a code when item code is blank',
                        'show_decimals' => 'Show currency decimals',
                        'dark_mode' => 'Dark mode',
                        'auto_logout' => 'Automatically log out inactive users',
                        'backup_daily' => 'Back up the database daily',
                        'require_gps_proof' => 'Require GPS location on proof of delivery',
                    ] as $key => $label)
                        <div class="toggle-row">
                            <label for="{{ $key }}">{{ $label }}</label>
                            <label class="toggle-switch">
                                <input id="{{ $key }}" type="checkbox" name="{{ $key }}" value="1" @checked((bool) old($key, $systemSettings[$key] === '1'))>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    @endforeach
                    <div class="mt-3">
                        <label class="form-label" for="session_timeout">Automatic logout after</label>
                        <select id="session_timeout" name="session_timeout" class="form-select">
                            @foreach([15, 30, 60, 120, 240] as $minutes)
                                <option value="{{ $minutes }}" @selected((int) old('session_timeout', $systemSettings['session_timeout']) === $minutes)>{{ $minutes }} minutes</option>
                            @endforeach
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
                <form method="POST" action="{{ route('settings.password') }}">
                    @csrf
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
                    <a class="btn btn-outline-primary" href="{{ route('settings.export') }}">Export Database</a>
                    <form method="POST" action="{{ route('settings.clear-cache') }}" onsubmit="return confirm('Clear Laravel application caches?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Clear Application Cache</button>
                    </form>
                    <form method="POST" action="{{ route('settings.reset') }}" onsubmit="return confirm('Restore all system settings to their defaults?')">
                        @csrf
                        <button type="submit" class="btn-reset">Reset Settings</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
@else
    <section class="settings-card">
        <h6>Account</h6>
        <p>Signed in as {{ Auth::user()->name }} ({{ Auth::user()->email }}).</p>
        <a href="{{ route('profile.edit') }}" class="btn btn-primary">Edit Profile & Password</a>
    </section>
@endif
@endsection

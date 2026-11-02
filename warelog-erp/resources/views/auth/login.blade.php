<x-guest-layout>
    <p class="auth-eyebrow">Welcome back</p>
    <h1 class="auth-title">Login</h1>
    <p class="auth-sub">Sign in to manage deliveries and warehouse stock</p>

    <!-- Session Status -->
    @if (session('status'))
        <div class="auth-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <!-- Email Address -->
        <div class="auth-field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            <x-input-error :messages="$errors->get('email')" class="auth-error" />
        </div>

        <!-- Password -->
        <div class="auth-field">
            <div class="auth-row">
                <label for="password">Password</label>
                @if (Route::has('password.request'))
                    <a class="auth-forgot" href="{{ route('password.request') }}">Forgot password?</a>
                @endif
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password">
            <x-input-error :messages="$errors->get('password')" class="auth-error" />
        </div>

        <button type="submit" class="auth-btn">Login</button>
    </form>

    <p class="auth-foot">
        No account yet? <a href="{{ route('register') }}">Register here</a>
    </p>
</x-guest-layout>

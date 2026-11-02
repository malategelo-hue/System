<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vehicle' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'vehicle' => $data['vehicle'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'is_active' => false,
        ]);

        return redirect()->route('login')->with(
            'status',
            'Registration submitted. An administrator must approve your account before you can sign in.',
        );
    }
}

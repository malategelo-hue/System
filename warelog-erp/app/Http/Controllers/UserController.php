<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Admin-only management and approval of driver accounts.
 */
class UserController extends Controller
{
    public function index()
    {
        $this->admin();

        return view('users.index', [
            'users' => User::where('role', 'user')->withCount([
                'deliveries',
                'deliveries as active_deliveries_count' => fn ($query) => $query->where('status', 'in_transit'),
            ])->orderBy('is_active')->orderBy('name')->get(),
            'systemSettings' => SystemSetting::values(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->admin();
        abort_unless($user->role === 'user', 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vehicle' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', Password::defaults()],
        ]);

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'vehicle' => $data['vehicle'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return redirect()->route('admin.users.index')->with('success', "{$user->name} updated.");
    }

    public function toggle(User $user): RedirectResponse
    {
        $this->admin();
        abort_unless($user->role === 'user', 404);

        $user->update(['is_active' => ! ($user->is_active ?? true)]);

        return redirect()->route('admin.users.index')->with(
            'success',
            $user->is_active ? "{$user->name} reactivated." : "{$user->name} deactivated and signed out.",
        );
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->admin();
        abort_unless($user->role === 'user', 404);

        if ($user->deliveries()->exists()) {
            return redirect()->route('admin.users.index')->withErrors([
                'user' => "{$user->name} has delivery history. Deactivate the account instead of deleting it.",
            ]);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Account deleted.');
    }

    private function admin(): User
    {
        $user = Auth::user();
        abort_if($user->role !== 'admin', 403, 'Admin access only');

        return $user;
    }
}

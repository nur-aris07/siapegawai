<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSecurityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AdminSecurityController extends Controller
{
    public function edit(): View
    {
        return view('admin.security.edit');
    }

    public function changePassword(Request $request, AdminSecurityService $security): RedirectResponse
    {
        $input = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)->letters()->numbers()],
        ]);

        $security->changeOwnPassword($request->user(), $input['current_password'], $input['password']);

        // Current browser session is revoked too; other admin sessions are blocked
        // by EnsureAdminSessionVersion the next time they access admin routes.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Kata sandi berhasil diubah. Silakan masuk kembali.');
    }
}

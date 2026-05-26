<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return Inertia::render('Admin/AdminLogin');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff_id' => ['required','string'],
            'password' => ['required','string'],
        ]);

        // Attempt by matric_number field (staff id) and password
        $credentials = ['matric_number' => $data['staff_id'], 'password' => $data['password']];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Ensure user has Admin or Security role
            $user = Auth::user();
            if (! in_array($user->role, ['Admin','Security'])) {
                Auth::logout();
                return back()->withErrors(['staff_id' => 'Unauthorized for admin access.']);
            }

            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return back()->withErrors(['staff_id' => 'Invalid credentials.']);
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

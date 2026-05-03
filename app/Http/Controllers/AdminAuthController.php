<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    /**
     * Show admin login page
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/admin/dashboard');
        }

        return view('admin.login');
    }

    /**
     * Handle admin login
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::guard('admin')->attempt($validated, $request->filled('remember'))) {
            $request->session()->regenerate();
            return response()->json(['success' => true, 'message' => 'Logged in successfully']);
        }

        return response()->json(['message' => 'Invalid credentials'], 422);
    }

    /**
     * Handle admin logout
     */
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    /**
     * Get current admin user
     */
    public function user()
    {
        if (!Auth::guard('admin')->check()) {
            return response()->json(['user' => null]);
        }

        // load roles and permissions to return to frontend
        $user = Auth::guard('admin')->user()->load('roles', 'permissions');

        return response()->json(['user' => $user]);
    }
}

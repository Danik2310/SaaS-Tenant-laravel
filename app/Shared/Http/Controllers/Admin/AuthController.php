<?php

namespace App\Shared\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Admin Authentication
 *
 * APIs for admin login, logout, and session management.
 */
class AuthController extends Controller
{
    /**
     * Show login page.
     *
     * @response 200 view rendered
     * @response 302 Redirect to dashboard if already authenticated.
     */
    public function showLogin()
    {
        return view('admin.login');
    }

    /**
     * Admin login.
     *
     * Authenticates an admin user with email and password.
     *
     * @bodyParam email string required Admin email. Example: admin@example.com
     * @bodyParam password string required Admin password. Example: s3cret
     * @bodyParam remember boolean optional Remember session. Example: true
     *
     * @response 200 {"success":true,"message":"Logged in successfully"}
     * @response 422 {"message":"Invalid credentials"}
     * @response 429 Too many attempts.
     */
    public function login(AdminLoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();

        return response()->json(['success' => true, 'message' => 'Logged in successfully']);
    }

    /**
     * Admin logout.
     *
     * Invalidates the session and logs out the admin user.
     *
     * @authenticated
     *
     * @response 200 {"success":true,"message":"Logged out successfully"}
     */
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }
}

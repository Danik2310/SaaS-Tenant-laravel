<?php

namespace App\Shared\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\AdminUserResource;
use App\Shared\Support\JwtCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Admin Authentication
 *
 * APIs for admin login, logout, and session management.
 */
class AdminAuthController extends Controller
{
    /**
     * Show login page.
     *
     * Redirects to dashboard if already authenticated.
     *
     * @response 200 view rendered
     * @response 302 Redirect to dashboard if authenticated.
     */
    public function showLogin()
    {
        if (Auth::guard('admin')->check()) {
            return redirect('/admin/dashboard');
        }

        return view('admin.login');
    }

    /**
     * Admin login.
     *
     * Authenticates an admin user with email and password.
     * Rate limited to 5 attempts per minute per email+IP.
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
        $token = $request->authenticate();

        $request->session()->regenerate();

        return response()->json(['success' => true, 'message' => 'Logged in successfully'])
            ->withCookie(JwtCookie::make($token));
    }

    /**
     * Admin logout.
     *
     * Invalidates the session and logs out the admin user.
     *
     * @authenticated
     *
     * @response 200 {"success":true,"message":"Logged out"}
     */
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'message' => 'Logged out'])
            ->withCookie(JwtCookie::forget());
    }

    /**
     * Get current admin user.
     *
     * Returns the authenticated admin user with roles and permissions.
     *
     * @authenticated
     *
     * @responseField user object The authenticated admin user with roles.
     * @responseField permissions string[] List of all permission names.
     */
    public function user()
    {
        if (! Auth::guard('admin')->check()) {
            return response()->json(['user' => null]);
        }

        $user = Auth::guard('admin')->user()->load('roles.permissions', 'permissions');

        $allPermissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions)
            ->merge($user->permissions)
            ->unique('id')
            ->pluck('name')
            ->values()
            ->toArray();

        return response()->json([
            'user' => new AdminUserResource($user),
            'permissions' => $allPermissions,
        ]);
    }
}

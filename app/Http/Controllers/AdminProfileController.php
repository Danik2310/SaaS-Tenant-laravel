<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\DeleteAccountRequest;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Http\Resources\AdminUserResource;
use Illuminate\Support\Facades\Hash;

/**
 * @group Admin Profile
 *
 * APIs for managing the authenticated admin user's own profile.
 */
class AdminProfileController extends Controller
{
    /**
     * Get profile of authenticated admin user.
     *
     * @authenticated
     *
     * @responseField data object The admin user resource.
     */
    public function show()
    {
        return response()->json([
            'data' => new AdminUserResource(auth('admin')->user()),
        ]);
    }

    /**
     * Update admin profile information.
     *
     * @authenticated
     *
     * @bodyParam name string required The admin name.
     * @bodyParam email string required The admin email.
     *
     * @responseField message string Success message.
     * @responseField data object The updated admin user resource.
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = auth('admin')->user();
        $user->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => new AdminUserResource($user->fresh()),
        ]);
    }

    /**
     * Update admin password.
     *
     * @authenticated
     *
     * @bodyParam current_password string required The current password.
     * @bodyParam new_password string required The new password (min 8 chars, mixed case, numbers, symbols).
     * @bodyParam new_password_confirmation string required Confirmation of new password.
     *
     * @responseField message string Success message.
     *
     * @response 422 {"message":"Current password is incorrect"}
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = auth('admin')->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(
                ['message' => 'Current password is incorrect'],
                422
            );
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Delete admin account.
     *
     * Requires password confirmation before deletion.
     *
     * @authenticated
     *
     * @bodyParam password string required Current password for confirmation.
     *
     * @responseField message string Success message.
     *
     * @response 422 {"message":"Password is incorrect"}
     */
    public function deleteAccount(DeleteAccountRequest $request)
    {
        $user = auth('admin')->user();
        $validated = $request->validated();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json(
                ['message' => 'Password is incorrect'],
                422
            );
        }

        $user->delete();
        auth('admin')->logout();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }
}

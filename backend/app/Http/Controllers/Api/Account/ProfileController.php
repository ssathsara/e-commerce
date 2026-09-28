<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\AuthUserResource;
use App\Services\Auth\AuthService;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->loadCount('orders');

        return $this->success([
            ...(new AuthUserResource($user))->resolve($request),
            'stats' => [
                'orders' => $user->orders_count,
                'addresses' => $user->addresses()->count(),
                'wishlist' => $user->wishlist()->withCount('items')->first()?->items_count ?? 0,
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        // Changing the email requires verifying the new address.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
            AuditLog::info('account.email_changed', ['user_id' => $user->id]);
        }

        return $this->success(new AuthUserResource($user), 'Profile updated.');
    }

    public function changePassword(ChangePasswordRequest $request, AuthService $auth): JsonResponse
    {
        $auth->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
            $request,
        );

        return $this->message('Password changed. Other devices have been signed out.');
    }
}

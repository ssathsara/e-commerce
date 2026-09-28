<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated(), $request);

        return $this->created(new AuthUserResource($user), 'Your account has been created.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->boolean('remember'),
            $request,
        );

        return $this->success(new AuthUserResource($user), 'Signed in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request);

        return $this->message('Signed out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new AuthUserResource($request->user()));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->sendResetLink($request->validated('email'));

        return $this->message('If an account exists for that email, a password reset link has been sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword($request->validated() + ['password_confirmation' => $request->input('password_confirmation')]);

        return $this->message('Your password has been reset. You can now sign in.');
    }

    public function sendVerificationEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->message('Your email address is already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->message('A new verification link has been sent to your email address.');
    }

    /**
     * Route is protected by the "signed:relative" middleware; this additionally checks
     * that the link belongs to the signed-in user.
     */
    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->id !== $id || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json(['success' => false, 'message' => 'This verification link is invalid.'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $this->success(new AuthUserResource($user), 'Your email address has been verified.');
    }
}

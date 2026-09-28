<?php

namespace App\Services\Auth;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Support\AuditLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum SPA authentication: the session guard ("web") issues an HttpOnly session
 * cookie, and Sanctum's stateful middleware authenticates subsequent API calls with it.
 */
class AuthService
{
    public function __construct(private readonly CartService $carts) {}

    public function register(array $data, Request $request): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
            ]);

            $user->assignRole(RoleName::Customer->value);

            return $user;
        });

        // Sends the verification email (MustVerifyEmail) and welcome email.
        event(new Registered($user));

        $this->startSession($user, $request, remember: false);

        AuditLog::info('auth.registered', ['user_id' => $user->id]);

        return $user;
    }

    public function login(string $email, string $password, bool $remember, Request $request): User
    {
        $credentials = ['email' => strtolower($email), 'password' => $password];

        if (! Auth::guard('web')->validate($credentials)) {
            AuditLog::warning('auth.login_failed', ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = User::query()->where('email', $credentials['email'])->firstOrFail();

        if (! $user->is_active) {
            AuditLog::warning('auth.login_blocked_inactive', ['user_id' => $user->id]);

            throw new BusinessRuleException('Your account has been deactivated. Please contact support.', 403);
        }

        $this->startSession($user, $request, $remember);

        AuditLog::info('auth.login', ['user_id' => $user->id]);

        return $user;
    }

    public function logout(Request $request): void
    {
        $userId = $request->user()?->id;

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        AuditLog::info('auth.logout', ['user_id' => $userId]);
    }

    /**
     * Always reports success so the endpoint cannot be used to discover registered emails.
     */
    public function sendResetLink(string $email): void
    {
        $status = Password::sendResetLink(['email' => strtolower($email)]);

        if ($status !== Password::RESET_LINK_SENT) {
            AuditLog::warning('auth.reset_link_not_sent', ['status' => $status]);
        }
    }

    public function resetPassword(array $data): void
    {
        $status = Password::reset(
            [
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'] ?? null,
                'token' => $data['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $this->terminateOtherSessions($user);

                event(new PasswordReset($user));
                AuditLog::info('auth.password_reset', ['user_id' => $user->id]);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword, Request $request): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }

        $user->forceFill(['password' => $newPassword])->save();

        $this->terminateOtherSessions($user, $request->hasSession() ? $request->session()->getId() : null);

        AuditLog::info('auth.password_changed', ['user_id' => $user->id]);
    }

    private function startSession(User $user, Request $request, bool $remember): void
    {
        Auth::guard('web')->login($user, $remember);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->carts->mergeGuestCart($request->header('X-Cart-Token'), $user);
    }

    /** Signs the user out of every other device after a credential change. */
    private function terminateOtherSessions(User $user, ?string $keepSessionId = null): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))
                ->delete();
        }

        $user->tokens()->delete();
    }
}

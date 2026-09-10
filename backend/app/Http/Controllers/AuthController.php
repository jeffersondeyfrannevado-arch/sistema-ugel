<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\MfaChallengeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Notifications\PasswordResetNotification;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MfaChallengeService $mfaChallengeService
    ) {}

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['nullable', Rule::in([User::ROLE_USER, User::ROLE_SUB_ADMIN, User::ROLE_CUSTOM, User::ROLE_SUPER_ADMIN])],
            'custom_permissions' => 'nullable|array',
            'mfa_enabled' => 'nullable|boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->user()?->hasPermission('admin.users.create')
                ? ($request->input('role') ?: User::ROLE_USER)
                : User::ROLE_USER,
            'is_active' => true,
            'custom_permissions' => $request->user()?->hasPermission('admin.users.create')
                ? ($request->input('custom_permissions') ?: [])
                : [],
            'mfa_enabled' => (bool) ($request->user()?->hasPermission('admin.users.create')
                ? $request->boolean('mfa_enabled')
                : false),
        ]);

        $this->auditLogService->record($request->user(), 'auth.registered', $user, [
            'role' => $user->role,
        ], $request);

        return response()->json($this->buildTokenResponse($user), Response::HTTP_CREATED);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        try {
            $user = User::where('email', $request->email)->first();
            $rateLimitKey = $this->rateLimitKey($request);

            if ($user && $user->isLocked()) {
                $this->auditLogService->record($user, 'auth.login.locked', $user, [], $request);

                return response()->json([
                    'message' => 'La cuenta esta bloqueada temporalmente por multiples intentos fallidos.',
                    'locked_until' => $user->locked_until?->toIso8601String(),
                ], Response::HTTP_LOCKED);
            }

            if (RateLimiter::tooManyAttempts($rateLimitKey, (int) config('admin.security.max_login_attempts', 5))) {
                $seconds = RateLimiter::availableIn($rateLimitKey);

                return response()->json([
                    'message' => 'Demasiados intentos. Intenta nuevamente en unos minutos.',
                    'retry_after' => $seconds,
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }

            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($rateLimitKey, config('admin.security.lockout_minutes', 15) * 60);

                if ($user) {
                    $user->increment('failed_login_attempts');
                    if ($user->failed_login_attempts >= (int) config('admin.security.max_login_attempts', 5)) {
                        $user->forceFill([
                            'locked_until' => now()->addMinutes((int) config('admin.security.lockout_minutes', 15)),
                        ])->save();
                    }
                }

                $this->auditLogService->record($user, 'auth.login.failed', $user?->email ?? $request->email, [], $request);

                return response()->json([
                    'message' => 'Credenciales inválidas',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!$user->is_active) {
                $this->auditLogService->record($user, 'auth.login.inactive', $user, [], $request);

                return response()->json([
                    'message' => 'Tu cuenta se encuentra desactivada. Contacta al administrador.'
                ], Response::HTTP_FORBIDDEN);
            }

            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();

            RateLimiter::clear($rateLimitKey);

            if ($user->requiresMfa()) {
                return response()->json($this->mfaChallengeService->issue($user, $request));
            }

            Auth::login($user);

            return response()->json($this->buildTokenResponse($user, $request));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error en el servidor al autenticar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyMfa(Request $request)
    {
        $request->validate([
            'challenge_id' => 'required|integer|exists:login_challenges,id',
            'code' => 'required|string|min:4|max:12',
        ]);

        $user = $this->mfaChallengeService->verify(
            (int) $request->integer('challenge_id'),
            (string) $request->input('code'),
            $request
        );

        return response()->json($this->buildTokenResponse($user, $request));
    }

    public function resendMfa(Request $request)
    {
        $request->validate([
            'challenge_id' => 'required|integer|exists:login_challenges,id',
        ]);

        return response()->json(
            $this->mfaChallengeService->resend((int) $request->integer('challenge_id'), $request)
        );
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()?->delete();
        $this->auditLogService->record($user, 'auth.logout', $user, [], $request);

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (!$user || !$token) {
            return response()->json([
                'message' => 'Sesion no valida para renovacion.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $abilities = $token->abilities;
        $token->delete();
        $newToken = $user->createToken('auth_token', $abilities);

        $this->auditLogService->record($user, 'auth.token_refreshed', $user, [], $request);

        return response()->json([
            'access_token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->tokenExpiry()?->toIso8601String(),
        ]);
    }

    public function currentUser(Request $request)
    {
        return response()->json($this->serializeUser($request->user()));
    }

    private function buildTokenResponse(User $user, ?Request $request = null): array
    {
        $token = $user->createToken('auth_token', $user->permissions())->plainTextToken;

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request?->ip(),
        ])->save();

        $this->auditLogService->record($user, 'auth.login.success', $user, [
            'permissions' => $user->permissions(),
        ], $request);

        return [
            'user' => $this->serializeUser($user->fresh()),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $this->tokenExpiry()?->toIso8601String(),
        ];
    }

    private function serializeUser(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->normalizedRole(),
            'is_active' => (bool) $user->is_active,
            'mfa_enabled' => (bool) $user->mfa_enabled,
            'custom_permissions' => $user->custom_permissions ?? [],
            'permissions' => $user->permissions(),
            'failed_login_attempts' => (int) $user->failed_login_attempts,
            'locked_until' => $user->locked_until?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
        ];
    }

    private function rateLimitKey(Request $request): string
    {
        return strtolower((string) $request->input('email')) . '|' . $request->ip();
    }

    private function tokenExpiry(): ?\Illuminate\Support\Carbon
    {
        $expiration = config('sanctum.expiration');

        return $expiration ? now()->addMinutes((int) $expiration) : null;
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
        ]);

        $email = strtolower(trim($request->email));
        $user = User::where('email', $email)->first();

        if (!$user || !$user->is_active) {
            return response()->json([
                'message' => 'El usuario no está activo o no existe.',
            ], Response::HTTP_FORBIDDEN);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresMinutes = 15;

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($code),
                'created_at' => now()
            ]
        );

        // Dynamically adjust SMTP configuration based on user type (similar to MFA challenge service)
        $username = null;
        $password = null;

        if ($email === 'jeffersondeyfrannevado@gmail.com') {
            $username = env('MAIL_USERNAME_SUPER_ADMIN');
            $password = env('MAIL_PASSWORD_SUPER_ADMIN');
        } elseif ($email === 'sanjuanmiraflores67@gmail.com') {
            $username = env('MAIL_USERNAME_SUB_ADMIN');
            $password = env('MAIL_PASSWORD_SUB_ADMIN');
        } elseif ($email === 'en1774121@gmail.com') {
            $username = env('MAIL_USERNAME_CUSTOM_ADMIN');
            $password = env('MAIL_PASSWORD_CUSTOM_ADMIN');
        }

        if ($username && $password) {
            config([
                'mail.mailers.smtp.username' => $username,
                'mail.mailers.smtp.password' => $password,
                'mail.from.address' => $username,
            ]);
            Mail::forgetMailers();
        }

        $user->notify(new PasswordResetNotification($code, $expiresMinutes));

        $this->auditLogService->record($user, 'auth.password.reset_requested', $user, [], $request);

        return response()->json([
            'message' => 'Se envio el codigo de recuperacion al correo registrado.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
            'code' => 'required|string|min:6|max:6',
            'password' => 'required|string|min:8',
        ]);

        $email = strtolower(trim($request->email));
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record) {
            return response()->json([
                'message' => 'No se ha solicitado una recuperación de contraseña para este correo.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Expire token after 15 minutes
        if (now()->subMinutes(15)->gt($record->created_at)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'message' => 'El código de recuperación ha expirado.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!Hash::check($request->code, $record->token)) {
            return response()->json([
                'message' => 'Código de recuperación inválido.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->password = Hash::make($request->password);
            $user->failed_login_attempts = 0;
            $user->locked_until = null;
            $user->save();

            DB::table('password_reset_tokens')->where('email', $email)->delete();

            $this->auditLogService->record($user, 'auth.password.reset_success', $user, [], $request);

            return response()->json([
                'message' => 'Contraseña restablecida exitosamente.',
            ]);
        }

        return response()->json([
            'message' => 'Error al restablecer la contraseña.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

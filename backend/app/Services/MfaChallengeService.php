<?php

namespace App\Services;

use App\Models\LoginChallenge;
use App\Models\User;
use App\Notifications\MfaCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MfaChallengeService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function issue(User $user, Request $request): array
    {
        LoginChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $length = max(4, (int) config('admin.mfa.code_length', 6));
        $expiresMinutes = max(1, (int) config('admin.mfa.expires_minutes', 10));
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        // Registrar codigo MFA en laravel.log y app/latest_mfa.json para consulta inmediata via https://sistema-ugel-api.onrender.com/
        try {
            $logDir = storage_path('logs');
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $appDir = storage_path('app');
            if (!is_dir($appDir)) {
                @mkdir($appDir, 0755, true);
            }

            $logEntry = "[" . now() . "] MFA CODE para {$user->email}: [ {$code} ]\n";
            @file_put_contents(storage_path('logs/laravel.log'), $logEntry, FILE_APPEND);
            @file_put_contents(storage_path('app/latest_mfa.json'), json_encode([
                'email' => $user->email,
                'code' => $code,
                'time' => now()->format('Y-m-d H:i:s'),
            ]));

            // Persistir en cache de base de datos para consulta persistente
            try {
                \Illuminate\Support\Facades\Cache::put('latest_mfa_code', [
                    'email' => $user->email,
                    'code' => $code,
                    'time' => now()->format('Y-m-d H:i:s'),
                ], now()->addMinutes($expiresMinutes));
            } catch (\Throwable $cacheEx) {}

            \Illuminate\Support\Facades\Log::info("=========================================");
            \Illuminate\Support\Facades\Log::info("MFA CODE para {$user->email}: [ {$code} ]");
            \Illuminate\Support\Facades\Log::info("=========================================");
        } catch (\Throwable $e) {}

        $challenge = LoginChallenge::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'channel' => 'email',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addMinutes($expiresMinutes),
        ]);

        // Dynamically adjust SMTP configuration based on user type
        $email = strtolower(trim($user->email));
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

        try {
            $user->notify(new MfaCodeNotification($code, $expiresMinutes));
        } catch (\Throwable $mailErr) {
            \Illuminate\Support\Facades\Log::warning("MFA Email delivery warning (log fallback active): " . $mailErr->getMessage());
        }

        $this->auditLogService->record($user, 'auth.mfa.challenge_created', $user, [
            'challenge_id' => $challenge->id,
        ], $request);

        $response = [
            'mfa_required' => true,
            'challenge_id' => $challenge->id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'mensaje' => 'Se envio un codigo MFA al correo registrado.',
        ];

        if (config('app.debug') || app()->environment('local')) {
            $response['debug_code'] = $code;
        }

        return $response;
    }

    public function verify(int $challengeId, string $code, Request $request): User
    {
        $challenge = LoginChallenge::query()->with('user')->findOrFail($challengeId);

        if ($challenge->used_at !== null) {
            throw new HttpException(422, 'El codigo MFA ya fue utilizado.');
        }

        if ($challenge->isExpired()) {
            throw new HttpException(422, 'El codigo MFA ha expirado.');
        }

        if (!Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');
            $this->auditLogService->record($challenge->user, 'auth.mfa.challenge_failed', $challenge->user, [
                'challenge_id' => $challenge->id,
                'attempts' => $challenge->attempts + 1,
            ], $request);

            throw new HttpException(422, 'Codigo MFA invalido.');
        }

        $challenge->forceFill([
            'used_at' => now(),
        ])->save();

        $this->auditLogService->record($challenge->user, 'auth.mfa.challenge_verified', $challenge->user, [
            'challenge_id' => $challenge->id,
        ], $request);

        return $challenge->user;
    }

    public function resend(int $challengeId, Request $request): array
    {
        $challenge = LoginChallenge::query()->with('user')->findOrFail($challengeId);

        if ($challenge->used_at !== null) {
            throw new HttpException(422, 'El codigo MFA ya fue utilizado.');
        }

        return $this->issue($challenge->user, $request);
    }
}

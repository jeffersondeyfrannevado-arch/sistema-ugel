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

        $user->notify(new MfaCodeNotification($code, $expiresMinutes));

        $this->auditLogService->record($user, 'auth.mfa.challenge_created', $user, [
            'challenge_id' => $challenge->id,
        ], $request);

        return [
            'mfa_required' => true,
            'challenge_id' => $challenge->id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'mensaje' => 'Se envio un codigo MFA al correo registrado.',
        ];
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

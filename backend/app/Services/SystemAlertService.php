<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SystemAlertService
{
    public function reportException(Throwable $exception, ?Request $request = null): void
    {
        if (!config('admin.alerts.enabled') || app()->environment('testing')) {
            return;
        }

        $payload = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'ip' => $request?->ip(),
            'user_id' => $request?->user()?->id,
        ];

        $this->notify('Fallo del sistema', $payload);
    }

    public function notify(string $title, array $context = []): void
    {
        try {
            Log::channel(config('admin.alerts.log_channel'))
                ->error($title, $context);

            $this->sendEmails($title, $context);
            $this->sendSlack($title, $context);
        } catch (Throwable $notificationException) {
            Log::error('No se pudo emitir una alerta del sistema', [
                'message' => $notificationException->getMessage(),
            ]);
        }
    }

    public function notifyAdmins(string $title, array $context = []): void
    {
        $adminEmails = User::query()
            ->whereIn('role', ['super_admin', 'sub_admin'])
            ->where('is_active', true)
            ->pluck('email')
            ->filter()
            ->all();

        $this->sendEmails($title, $context, $adminEmails);
    }

    private function sendEmails(string $title, array $context, array $overrideRecipients = []): void
    {
        $recipients = $overrideRecipients !== []
            ? $overrideRecipients
            : config('admin.alerts.emails', []);

        if ($recipients === []) {
            return;
        }

        $body = $title . PHP_EOL . PHP_EOL . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        foreach ($recipients as $recipient) {
            Mail::raw($body, function ($message) use ($recipient, $title) {
                $message->to($recipient)->subject($title);
            });
        }
    }

    private function sendSlack(string $title, array $context): void
    {
        $webhook = config('admin.alerts.slack_webhook');
        if (!$webhook) {
            return;
        }

        Http::timeout(5)->post($webhook, [
            'text' => $title . PHP_EOL . json_encode($context, JSON_UNESCAPED_UNICODE),
        ]);
    }
}

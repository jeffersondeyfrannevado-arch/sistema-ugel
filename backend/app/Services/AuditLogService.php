<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        ?User $actor,
        string $action,
        Model|string|null $subject = null,
        array $metadata = [],
        ?Request $request = null
    ): AuditLog {
        $subjectType = null;
        $subjectId = null;
        $targetLabel = null;

        if ($subject instanceof Model) {
            $subjectType = $subject::class;
            $subjectId = $subject->getKey();
            $targetLabel = method_exists($subject, 'getAttribute')
                ? (string) ($subject->getAttribute('name')
                    ?? $subject->getAttribute('title')
                    ?? $subject->getKey())
                : (string) $subject->getKey();
        } elseif (is_string($subject) && $subject !== '') {
            $targetLabel = $subject;
        }

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'target_label' => $targetLabel,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}

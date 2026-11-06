<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes append-only audit events. Callers must never pass secrets in metadata.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $event, Model $subject, ?User $actor, ?string $reason = null, array $metadata = []): AuditEvent
    {
        $request = app()->runningInConsole() ? null : request();

        return AuditEvent::create([
            'actor_id' => $actor?->id,
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}

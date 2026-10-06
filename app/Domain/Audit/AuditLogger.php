<?php

namespace App\Domain\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes audit entries for the current request.
 *
 * Callers pass only identifiers and field names in $properties: never
 * passwords, tokens or clinical text.
 */
class AuditLogger
{
    /** @param array<string, mixed> $properties */
    public function log(string $action, ?Model $subject = null, array $properties = [], ?int $userId = null): AuditLog
    {
        $request = request();
        $userAgent = $request->userAgent();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 250, ''),
        ]);
    }
}

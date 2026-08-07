<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuditService
{
    public function record(
        string $event,
        Model|string $entity,
        array $oldValues = [],
        array $newValues = [],
        ?Request $request = null
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => auth()->id(),
            'event' => $event,
            'entity_type' => $entity instanceof Model ? $entity::class : $entity,
            'entity_id' => $entity instanceof Model ? (string) $entity->getKey() : null,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => (string) Str::uuid(),
        ]);
    }
}

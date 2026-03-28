<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Call;
use App\Models\CallMessage;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;

class ActivityLogger
{
    public function log(
        Tenant|int $tenant,
        string $eventType,
        string $title,
        ?string $description = null,
        ?User $user = null,
        ?Call $call = null,
        ?CallMessage $callMessage = null,
        array $metadata = [],
        ?CarbonInterface $happenedAt = null,
    ): ActivityLog {
        return ActivityLog::create([
            'tenant_id' => $tenant instanceof Tenant ? $tenant->id : $tenant,
            'user_id' => $user?->id,
            'call_id' => $call?->id,
            'call_message_id' => $callMessage?->id,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'metadata' => $this->filterMetadata($metadata),
            'happened_at' => $happenedAt ?? now(),
        ]);
    }

    private function filterMetadata(array $metadata): array
    {
        return array_filter($metadata, fn ($value) => $value !== null && $value !== '');
    }
}

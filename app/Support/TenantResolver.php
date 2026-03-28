<?php

namespace App\Support;

use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;

class TenantResolver
{
    public function forUser(?User $user, array $with = []): ?Tenant
    {
        if (! $user?->tenant_id) {
            return null;
        }

        return $user->tenant()->with($with)->first();
    }

    public function resolvePhoneNumber(?string $phoneNumber): ?PhoneNumber
    {
        $normalized = $this->normalizePhoneNumber($phoneNumber);

        if (! $normalized) {
            return null;
        }

        return PhoneNumber::with('tenant.agentConfig')
            ->where('is_active', true)
            ->where('phone_number', $normalized)
            ->first();
    }

    public function normalizePhoneNumber(?string $phoneNumber): ?string
    {
        if (! filled($phoneNumber)) {
            return null;
        }

        $normalized = preg_replace('/(?!^\+)[^\d]/', '', trim($phoneNumber));

        return filled($normalized) ? $normalized : null;
    }

    public function primaryPhoneNumber(?Tenant $tenant): ?PhoneNumber
    {
        if (! $tenant) {
            return null;
        }

        return $tenant->phoneNumbers()
            ->orderByDesc('is_primary')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();
    }
}

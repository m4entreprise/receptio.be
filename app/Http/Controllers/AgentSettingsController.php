<?php

namespace App\Http\Controllers;

use App\Models\AgentConfig;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AgentSettingsController extends Controller
{
    public function __construct(private readonly TenantResolver $tenantResolver) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_name' => ['required', 'string', 'max:255'],
            'agent_name' => ['required', 'string', 'max:255'],
            'welcome_message' => ['required', 'string', 'max:1000'],
            'after_hours_message' => ['required', 'string', 'max:1000'],
            'faq_content' => ['nullable', 'string', 'max:5000'],
            'transfer_phone_number' => ['nullable', 'string', 'max:30'],
            'notification_email' => ['required', 'email', 'max:255'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'business_days' => ['array'],
            'business_days.*' => ['string'],
        ]);

        $tenant = $request->user()->tenant ?? Tenant::firstOrCreate(
            ['slug' => str($validated['tenant_name'])->slug()->toString()],
            [
                'name' => $validated['tenant_name'],
                'locale' => 'fr-BE',
                'timezone' => 'Europe/Brussels',
            ],
        );

        if (! $request->user()->tenant_id) {
            $request->user()->forceFill(['tenant_id' => $tenant->id])->save();
        }

        $normalizedPhoneNumber = $this->tenantResolver->normalizePhoneNumber($validated['phone_number'] ?? null);

        if ($normalizedPhoneNumber && PhoneNumber::where('phone_number', $normalizedPhoneNumber)->where('tenant_id', '!=', $tenant->id)->exists()) {
            throw ValidationException::withMessages([
                'phone_number' => 'Ce numéro est déjà rattaché à un autre tenant.',
            ]);
        }

        $tenant->update([
            'name' => $validated['tenant_name'],
            'slug' => str($validated['tenant_name'])->slug()->toString(),
        ]);

        AgentConfig::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'agent_name' => $validated['agent_name'],
                'welcome_message' => $validated['welcome_message'],
                'after_hours_message' => $validated['after_hours_message'],
                'faq_content' => $validated['faq_content'] ?? null,
                'transfer_phone_number' => $validated['transfer_phone_number'] ?: null,
                'notification_email' => $validated['notification_email'],
                'opens_at' => $validated['opens_at'] ?? null,
                'closes_at' => $validated['closes_at'] ?? null,
                'business_days' => $validated['business_days'] ?? [],
            ],
        );

        if ($normalizedPhoneNumber) {
            $this->syncPrimaryPhoneNumber($tenant, $normalizedPhoneNumber);
        }

        return back()->with('success', 'Configuration mise à jour.');
    }

    private function syncPrimaryPhoneNumber(Tenant $tenant, string $phoneNumber): void
    {
        $existingForTenant = $tenant->phoneNumbers()->where('phone_number', $phoneNumber)->first();
        $currentPrimary = $this->tenantResolver->primaryPhoneNumber($tenant);

        $phoneRecord = $existingForTenant ?? $currentPrimary ?? new PhoneNumber([
            'tenant_id' => $tenant->id,
            'provider' => 'twilio',
        ]);

        $phoneRecord->fill([
            'tenant_id' => $tenant->id,
            'provider' => 'twilio',
            'label' => 'Ligne principale',
            'phone_number' => $phoneNumber,
            'is_active' => true,
            'is_primary' => true,
        ]);

        $phoneRecord->save();

        $tenant->phoneNumbers()
            ->whereKeyNot($phoneRecord->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}

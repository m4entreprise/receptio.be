<?php

namespace App\Http\Controllers;

use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly TenantResolver $tenantResolver) {}

    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenantResolver->forUser($request->user(), ['agentConfig', 'phoneNumbers', 'calls.message']);
        $primaryPhoneNumber = $this->tenantResolver->primaryPhoneNumber($tenant);

        $agentConfig = $tenant?->agentConfig;
        $recentCalls = collect();
        $totalCalls = 0;
        $missedCalls = 0;

        if ($tenant) {
            $totalCalls = $tenant->calls()->count();
            $missedCalls = $tenant->calls()->where('status', 'voicemail_received')->count();
            $recentCalls = $tenant->calls()
                ->with('message')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($call) => [
                    'id' => $call->id,
                    'status' => $call->status,
                    'from_number' => $call->from_number,
                    'to_number' => $call->to_number,
                    'started_at' => $call->started_at?->toIso8601String(),
                    'summary' => $call->summary,
                    'message' => $call->message ? [
                        'caller_name' => $call->message->caller_name,
                        'caller_number' => $call->message->caller_number,
                        'message_text' => $call->message->message_text,
                        'recording_url' => $call->message->recording_url,
                    ] : null,
                ])
                ->values();
        }

        return Inertia::render('Dashboard', [
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'locale' => $tenant->locale,
                'timezone' => $tenant->timezone,
            ] : null,
            'stats' => [
                'total_calls' => $totalCalls,
                'missed_calls' => $missedCalls,
                'open_hours' => $agentConfig?->opens_at && $agentConfig?->closes_at ? sprintf('%s - %s', substr($agentConfig->opens_at, 0, 5), substr($agentConfig->closes_at, 0, 5)) : 'Non défini',
                'transfer_enabled' => filled($agentConfig?->transfer_phone_number),
            ],
            'settings' => [
                'agent_name' => $agentConfig?->agent_name ?? 'Receptio',
                'welcome_message' => $agentConfig?->welcome_message ?? "Bonjour, vous êtes bien chez {$tenant?->name}.",
                'after_hours_message' => $agentConfig?->after_hours_message ?? 'Nous sommes actuellement indisponibles. Laissez-nous un message et nous vous rappellerons rapidement.',
                'faq_content' => $agentConfig?->faq_content ?? '',
                'transfer_phone_number' => $agentConfig?->transfer_phone_number ?? '',
                'notification_email' => $agentConfig?->notification_email ?? $request->user()->email,
                'opens_at' => $agentConfig?->opens_at ? substr($agentConfig->opens_at, 0, 5) : null,
                'closes_at' => $agentConfig?->closes_at ? substr($agentConfig->closes_at, 0, 5) : null,
                'business_days' => $agentConfig?->business_days ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'phone_number' => $primaryPhoneNumber?->phone_number ?? '',
            ],
            'recentCalls' => $recentCalls,
            'webhooks' => [
                'incoming' => route('webhooks.twilio.voice.incoming', absolute: true),
            ],
        ]);
    }
}

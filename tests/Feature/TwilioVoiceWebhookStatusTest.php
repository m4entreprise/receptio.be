<?php

use App\Models\AgentConfig;
use App\Models\Call;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createTwilioWorkspace(): array
{
    $tenant = Tenant::create([
        'name' => 'Acme Reception',
        'slug' => 'acme-reception',
        'locale' => 'fr-BE',
        'timezone' => 'Europe/Brussels',
    ]);

    AgentConfig::create([
        'tenant_id' => $tenant->id,
        'agent_name' => 'Sophie',
        'welcome_message' => 'Bonjour, vous êtes bien chez Acme.',
        'after_hours_message' => 'Nous sommes fermés pour le moment.',
        'transfer_phone_number' => '+32470000000',
        'notification_email' => 'ops@example.com',
        'opens_at' => '09:00',
        'closes_at' => '18:00',
        'business_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    $phoneNumber = PhoneNumber::create([
        'tenant_id' => $tenant->id,
        'provider' => 'twilio',
        'label' => 'Ligne principale',
        'phone_number' => '+3220000000',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    return [$tenant, $phoneNumber, $user];
}

test('twilio status callback updates a call with final transfer outcome', function () {
    [$tenant, $phoneNumber] = createTwilioWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_TRANSFER_PARENT',
        'direction' => 'inbound',
        'status' => 'transferring',
        'from_number' => '+32471234567',
        'to_number' => '+3220000000',
        'started_at' => now()->subSeconds(75),
        'metadata' => ['source' => 'feature_test'],
    ]);

    $response = $this->post(route('webhooks.twilio.voice.status'), [
        'CallSid' => 'CA_TRANSFER_PARENT',
        'CallStatus' => 'completed',
        'CallDuration' => '75',
        'DialCallStatus' => 'completed',
        'DialCallDuration' => '42',
        'DialCallSid' => 'CA_TRANSFER_CHILD',
        'CallbackSource' => 'call-progress-events',
        'SequenceNumber' => '1',
    ]);

    $response->assertNoContent();

    $call->refresh();

    expect($call->status)->toBe('transferred')
        ->and($call->ended_at)->not->toBeNull()
        ->and(data_get($call->metadata, 'call_duration_seconds'))->toBe(75)
        ->and(data_get($call->metadata, 'dial_call_duration_seconds'))->toBe(42)
        ->and(data_get($call->metadata, 'last_twilio_call_status'))->toBe('completed')
        ->and(data_get($call->metadata, 'last_twilio_dial_call_status'))->toBe('completed')
        ->and(data_get($call->metadata, 'last_status_callback.dial_call_sid'))->toBe('CA_TRANSFER_CHILD')
        ->and(data_get($call->metadata, 'status_events.0.callback_source'))->toBe('call-progress-events');
});

test('calls dashboard uses final twilio status and duration metadata', function () {
    [$tenant, $phoneNumber, $user] = createTwilioWorkspace();

    Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_DASHBOARD_CALL',
        'direction' => 'inbound',
        'status' => 'transferred',
        'from_number' => '+32479876543',
        'to_number' => '+3220000000',
        'started_at' => now()->subMinutes(3),
        'ended_at' => now()->subMinutes(2),
        'summary' => 'Transfert réussi vers la ligne humaine.',
        'metadata' => [
            'call_duration_seconds' => 65,
            'dial_call_duration_seconds' => 48,
        ],
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.calls'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/Calls', false)
        ->where('calls.0.status', 'transferred')
        ->where('calls.0.status_label', 'Transféré')
        ->where('calls.0.tone', 'success')
        ->where('calls.0.duration_seconds', 65)
        ->where('calls.0.summary', 'Transfert réussi vers la ligne humaine.')
    );
});

test('twilio status callback falls back to voicemail when transfer is busy', function () {
    [$tenant, $phoneNumber] = createTwilioWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_TRANSFER_BUSY',
        'direction' => 'inbound',
        'status' => 'transferring',
        'from_number' => '+32470001111',
        'to_number' => '+3220000000',
        'started_at' => now()->subSeconds(40),
    ]);

    $response = $this->post(route('webhooks.twilio.voice.status'), [
        'CallSid' => 'CA_TRANSFER_BUSY',
        'DialCallStatus' => 'busy',
        'DialCallDuration' => '0',
        'DialCallSid' => 'CA_TRANSFER_BUSY_CHILD',
        'CallStatus' => 'in-progress',
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/xml');
    $response->assertSee('Merci de laisser un message après le bip.', false);

    $call->refresh();

    expect($call->status)->toBe('voicemail_prompted')
        ->and($call->ended_at)->toBeNull()
        ->and(data_get($call->metadata, 'transfer_failure_status'))->toBe('busy')
        ->and(data_get($call->metadata, 'fallback_target'))->toBe('voicemail')
        ->and($call->summary)->toContain('ligne occupée')
        ->and($call->summary)->toContain('Bascule vers messagerie');
});

test('calls dashboard exposes transfer failure metadata after voicemail fallback', function () {
    [$tenant, $phoneNumber, $user] = createTwilioWorkspace();

    Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_FALLBACK_DASHBOARD',
        'direction' => 'inbound',
        'status' => 'voicemail_prompted',
        'from_number' => '+32479990000',
        'to_number' => '+3220000000',
        'started_at' => now()->subMinute(),
        'summary' => 'Transfert humain échoué : pas de réponse. Bascule vers messagerie.',
        'metadata' => [
            'transfer_failure_status' => 'no-answer',
            'fallback_target' => 'voicemail',
            'call_duration_seconds' => 21,
            'status_events' => [
                [
                    'received_at' => now()->subSeconds(15)->toIso8601String(),
                    'call_status' => 'in-progress',
                    'dial_call_status' => 'no-answer',
                    'callback_source' => 'call-progress-events',
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.calls'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/Calls', false)
        ->where('calls.0.status', 'voicemail_prompted')
        ->where('calls.0.transfer_failure_status', 'no-answer')
        ->where('calls.0.fallback_target', 'voicemail')
        ->where('calls.0.duration_seconds', 21)
        ->where('calls.0.recent_status_events.0.dial_call_status', 'no-answer')
        ->where('calls.0.recent_status_events.0.callback_source', 'call-progress-events')
    );
});

<?php

use App\Models\AgentConfig;
use App\Models\Call;
use App\Models\CallTurn;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use Carbon\Carbon;

function createConversationWorkspace(): array
{
    $tenant = Tenant::create([
        'name' => 'Acme Conversation',
        'slug' => 'acme-conversation',
        'locale' => 'fr-BE',
        'timezone' => 'Europe/Brussels',
    ]);

    $agentConfig = AgentConfig::create([
        'tenant_id' => $tenant->id,
        'agent_name' => 'Sophie',
        'welcome_message' => 'Bonjour, vous êtes bien chez Acme. Comment puis-je vous aider ?',
        'after_hours_message' => 'Nous sommes fermés pour le moment.',
        'faq_content' => 'Horaires: du lundi au vendredi de 9h à 18h.',
        'conversation_enabled' => true,
        'conversation_prompt' => 'Tu réponds brièvement, en français, et tu escalades en cas de doute.',
        'max_clarification_turns' => 2,
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
        'is_primary' => true,
    ]);

    return [$tenant, $agentConfig, $phoneNumber];
}

test('internal bootstrap exposes conversation runtime context', function () {
    config()->set('services.realtime.internal_token', 'test-internal-token');

    [$tenant, $agentConfig, $phoneNumber] = createConversationWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_BOOTSTRAP',
        'direction' => 'inbound',
        'status' => 'in_progress',
        'channel' => Call::CHANNEL_CONVERSATION_AI,
        'conversation_status' => 'active',
        'from_number' => '+32471234567',
        'to_number' => '+3220000000',
        'started_at' => now()->subSeconds(20),
    ]);

    CallTurn::create([
        'call_id' => $call->id,
        'speaker' => 'caller',
        'text' => 'Bonjour, quels sont vos horaires ?',
        'sequence' => 1,
    ]);

    $response = $this
        ->withHeader('Authorization', 'Bearer test-internal-token')
        ->get(route('internal.realtime.calls.bootstrap', $call->external_sid));

    $response->assertOk()
        ->assertJsonPath('call.external_sid', 'CA_BOOTSTRAP')
        ->assertJsonPath('tenant.id', $tenant->id)
        ->assertJsonPath('agent.agent_name', $agentConfig->agent_name)
        ->assertJsonPath('agent.conversation_enabled', true)
        ->assertJsonPath('agent.max_clarification_turns', 2)
        ->assertJsonPath('turns.0.text', 'Bonjour, quels sont vos horaires ?');
});

test('internal turn persistence rebuilds the call transcript', function () {
    config()->set('services.realtime.internal_token', 'test-internal-token');

    [$tenant, $agentConfig, $phoneNumber] = createConversationWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_TURN',
        'direction' => 'inbound',
        'status' => 'in_progress',
        'channel' => Call::CHANNEL_CONVERSATION_AI,
        'from_number' => '+32471234567',
        'to_number' => '+3220000000',
        'started_at' => now()->subSeconds(10),
    ]);

    $response = $this
        ->withHeader('Authorization', 'Bearer test-internal-token')
        ->post(route('internal.realtime.calls.turns.store', $call->external_sid), [
            'speaker' => 'caller',
            'text' => 'Je voudrais parler à quelqu’un.',
            'confidence' => 0.94,
            'sequence' => 1,
            'meta' => ['source' => 'twilio'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('turn.sequence', 1)
        ->assertJsonPath('turn.speaker', 'caller');

    $call->refresh();

    expect($call->transcript)->toBe('Caller: Je voudrais parler à quelqu’un.')
        ->and($call->conversation_status)->toBe('active');
});

test('incoming webhook opens twilio conversation relay when the runtime is enabled', function () {
    config()->set('services.twilio.conversation_relay_url', 'wss://relay.example/ws');
    Carbon::setTestNow('2026-03-24 10:30:00');

    [$tenant, $agentConfig, $phoneNumber] = createConversationWorkspace();

    $response = $this->post(route('webhooks.twilio.voice.incoming'), [
        'CallSid' => 'CA_CONVERSATION_INCOMING',
        'From' => '+32471234567',
        'To' => $phoneNumber->phone_number,
    ]);

    $response->assertOk();
    $response->assertSee('<ConversationRelay', false);
    $response->assertSee('wss://relay.example/ws', false);
    $response->assertSee('welcomeGreeting=', false);

    $call = Call::where('external_sid', 'CA_CONVERSATION_INCOMING')->firstOrFail();

    expect($call->channel)->toBe(Call::CHANNEL_CONVERSATION_AI)
        ->and($call->status)->toBe('in_progress')
        ->and($call->conversation_status)->toBe('routing');

    Carbon::setTestNow();
});

test('conversation relay callback can hand off the call to a human transfer', function () {
    [$tenant, $agentConfig, $phoneNumber] = createConversationWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_CONVERSATION_TRANSFER',
        'direction' => 'inbound',
        'status' => 'in_progress',
        'channel' => Call::CHANNEL_CONVERSATION_AI,
        'conversation_status' => 'active',
        'from_number' => '+32471234567',
        'to_number' => '+3220000000',
        'started_at' => now()->subSeconds(35),
    ]);

    $response = $this->post(route('webhooks.twilio.voice.status'), [
        'CallSid' => 'CA_CONVERSATION_TRANSFER',
        'SessionStatus' => 'ended',
        'SessionId' => 'VX_TRANSFER',
        'HandoffData' => json_encode([
            'action' => 'transfer',
            'reason' => 'caller_requested_human',
            'summary' => 'Le caller veut un humain.',
        ], JSON_THROW_ON_ERROR),
    ]);

    $response->assertOk();
    $response->assertSee('<Dial', false);
    $response->assertSee($agentConfig->transfer_phone_number, false);

    $call->refresh();

    expect($call->status)->toBe('transferring')
        ->and($call->conversation_status)->toBe('transfer_requested')
        ->and($call->escalation_reason)->toBe('caller_requested_human')
        ->and($call->summary)->toBe('Le caller veut un humain.');
});

test('conversation relay failure falls back to voicemail', function () {
    [$tenant, $agentConfig, $phoneNumber] = createConversationWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_CONVERSATION_FAILED',
        'direction' => 'inbound',
        'status' => 'in_progress',
        'channel' => Call::CHANNEL_CONVERSATION_AI,
        'conversation_status' => 'active',
        'from_number' => '+32479998888',
        'to_number' => '+3220000000',
        'started_at' => now()->subSeconds(18),
    ]);

    $response = $this->post(route('webhooks.twilio.voice.status'), [
        'CallSid' => 'CA_CONVERSATION_FAILED',
        'SessionStatus' => 'failed',
        'SessionId' => 'VX_FAILED',
    ]);

    $response->assertOk();
    $response->assertSee('Merci de laisser un message après le bip.', false);

    $call->refresh();

    expect($call->status)->toBe('voicemail_prompted')
        ->and($call->conversation_status)->toBe('fallback_requested')
        ->and(data_get($call->metadata, 'fallback_target'))->toBe('voicemail');
});

<?php

use App\Mail\VoicemailReceivedMail;
use App\Models\ActivityLog;
use App\Models\AgentConfig;
use App\Models\Call;
use App\Models\CallMessage;
use App\Models\CallTurn;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

function createPhaseFourWorkspace(): array
{
    $tenant = Tenant::create([
        'name' => 'Tenant Phase 4',
        'slug' => 'tenant-phase-4',
        'locale' => 'fr-BE',
        'timezone' => 'Europe/Brussels',
    ]);

    AgentConfig::create([
        'tenant_id' => $tenant->id,
        'agent_name' => 'Sophie',
        'welcome_message' => 'Bonjour, vous etes bien chez Tenant Phase 4.',
        'after_hours_message' => 'Nous sommes fermes.',
        'notification_email' => 'ops-phase4@example.com',
        'transfer_phone_number' => '+32470009999',
    ]);

    $phoneNumber = PhoneNumber::create([
        'tenant_id' => $tenant->id,
        'provider' => 'twilio',
        'label' => 'Ligne principale',
        'phone_number' => '+3221111111',
        'is_active' => true,
        'is_primary' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    return compact('tenant', 'phoneNumber', 'user');
}

test('message workflow can schedule a callback and create activity logs', function () {
    ['tenant' => $tenant, 'phoneNumber' => $phoneNumber, 'user' => $user] = createPhaseFourWorkspace();

    $assignee = User::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Operatrice 2',
    ]);

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'status' => 'voicemail_received',
        'from_number' => '+32470001010',
        'to_number' => $phoneNumber->phone_number,
        'summary' => 'Le client attend un rappel.',
    ]);

    $message = CallMessage::create([
        'tenant_id' => $tenant->id,
        'call_id' => $call->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Client rappel',
        'caller_number' => '+32470001010',
        'message_text' => 'Merci de me rappeler demain matin.',
    ]);

    $this->actingAs($user)
        ->patch(route('dashboard.messages.update', $message->id), [
            'status' => CallMessage::STATUS_IN_PROGRESS,
            'assigned_to_user_id' => $assignee->id,
            'callback_due_at' => '2026-03-30 09:30:00',
        ])
        ->assertRedirect();

    $message->refresh();

    expect($message->status)->toBe(CallMessage::STATUS_IN_PROGRESS)
        ->and($message->assigned_to_user_id)->toBe($assignee->id)
        ->and($message->callback_due_at?->format('Y-m-d H:i:s'))->toBe('2026-03-30 09:30:00');

    expect(ActivityLog::where('call_message_id', $message->id)->where('event_type', 'message_status_updated')->exists())->toBeTrue()
        ->and(ActivityLog::where('call_message_id', $message->id)->where('event_type', 'message_assigned')->exists())->toBeTrue()
        ->and(ActivityLog::where('call_message_id', $message->id)->where('event_type', 'callback_scheduled')->exists())->toBeTrue();
});

test('twilio recording sends a voicemail notification email and records activity logs', function () {
    Mail::fake();

    ['tenant' => $tenant, 'phoneNumber' => $phoneNumber] = createPhaseFourWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE4_RECORDING',
        'direction' => 'inbound',
        'status' => 'voicemail_prompted',
        'from_number' => '+32470002020',
        'to_number' => $phoneNumber->phone_number,
        'started_at' => now()->subMinute(),
    ]);

    $response = $this->post(route('webhooks.twilio.voice.recording'), [
        'CallSid' => 'CA_PHASE4_RECORDING',
        'From' => '+32470002020',
        'RecordingUrl' => 'https://example.test/phase4-recording.mp3',
        'RecordingDuration' => '33',
    ]);

    $response->assertOk();

    $message = CallMessage::where('call_id', $call->id)->first();

    expect($message)->not->toBeNull();
    expect(ActivityLog::where('call_id', $call->id)->where('event_type', 'message_received')->exists())->toBeTrue()
        ->and(ActivityLog::where('call_id', $call->id)->where('event_type', 'notification_email_sent')->exists())->toBeTrue();

    Mail::assertSent(VoicemailReceivedMail::class, function (VoicemailReceivedMail $mail) use ($call, $message) {
        return $mail->call->is($call) && $mail->callMessage->is($message);
    });
});

test('call detail exposes callback planning and activity history', function () {
    ['tenant' => $tenant, 'phoneNumber' => $phoneNumber, 'user' => $user] = createPhaseFourWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE4_DETAIL',
        'direction' => 'inbound',
        'status' => 'voicemail_received',
        'from_number' => '+32470003030',
        'to_number' => $phoneNumber->phone_number,
        'started_at' => now()->subMinutes(15),
        'ended_at' => now()->subMinutes(14),
        'summary' => 'Le client demande un rappel detaille.',
    ]);

    $message = CallMessage::create([
        'tenant_id' => $tenant->id,
        'call_id' => $call->id,
        'status' => CallMessage::STATUS_IN_PROGRESS,
        'caller_name' => 'Client detail',
        'caller_number' => '+32470003030',
        'message_text' => 'Pouvez-vous me rappeler apres 15h ?',
        'callback_due_at' => now()->addDay()->setTime(15, 0),
        'assigned_to_user_id' => $user->id,
    ]);

    ActivityLog::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'call_id' => $call->id,
        'call_message_id' => $message->id,
        'event_type' => 'callback_scheduled',
        'title' => 'Rappel planifie',
        'description' => 'Le rappel est programme pour demain apres-midi.',
        'happened_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.calls.show', $call->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/CallDetail')
            ->where('call.message.callback_due_at', $message->callback_due_at?->toIso8601String())
            ->where('activityFeed.0.title', 'Rappel planifie'));
});

test('call detail exposes conversation transcript, resolution and relay events', function () {
    ['tenant' => $tenant, 'phoneNumber' => $phoneNumber, 'user' => $user] = createPhaseFourWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE4_CONVERSATION',
        'direction' => 'inbound',
        'status' => 'transferred',
        'channel' => Call::CHANNEL_CONVERSATION_AI,
        'conversation_status' => 'completed',
        'resolution_type' => 'transferred',
        'conversation_summary' => 'Le caller a demande un humain pour finaliser son dossier.',
        'escalation_reason' => 'caller_requested_human',
        'from_number' => '+32470003031',
        'to_number' => $phoneNumber->phone_number,
        'started_at' => now()->subMinutes(8),
        'ended_at' => now()->subMinutes(6),
        'summary' => 'Le caller a demande un humain pour finaliser son dossier.',
        'transcript' => "Caller: Bonjour, je veux parler a quelqu'un.\nAssistant: Je vous transfere.",
        'metadata' => [
            'conversation_relay' => [
                'events' => [
                    [
                        'received_at' => now()->subMinutes(7)->toIso8601String(),
                        'session_id' => 'VX_PHASE4',
                        'session_status' => 'ended',
                        'handoff_data' => [
                            'action' => 'transfer',
                            'reason' => 'caller_requested_human',
                            'summary' => 'Le caller veut un humain.',
                            'target_phone_number' => '+32470009999',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    CallTurn::create([
        'call_id' => $call->id,
        'speaker' => 'caller',
        'text' => "Bonjour, je veux parler a quelqu'un.",
        'confidence' => 0.98,
        'sequence' => 1,
    ]);

    CallTurn::create([
        'call_id' => $call->id,
        'speaker' => 'assistant',
        'text' => 'Je vous transfere immediatement.',
        'confidence' => 0.99,
        'sequence' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.calls.show', $call->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/CallDetail')
            ->where('call.channel', Call::CHANNEL_CONVERSATION_AI)
            ->where('call.resolution_type', 'transferred')
            ->where('call.escalation_reason', 'caller_requested_human')
            ->where('call.conversation_summary', 'Le caller a demande un humain pour finaliser son dossier.')
            ->where('call.turns.0.text', "Bonjour, je veux parler a quelqu'un.")
            ->where('call.turns.1.speaker', 'assistant')
            ->where('call.conversation_relay_events.0.handoff_action', 'transfer')
            ->where('call.conversation_relay_events.0.handoff_target_phone_number', '+32470009999'));
});

test('overview exposes conversation resolution metrics', function () {
    ['tenant' => $tenant, 'phoneNumber' => $phoneNumber, 'user' => $user] = createPhaseFourWorkspace();

    $answeredCall = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE4_OVERVIEW_1',
        'direction' => 'inbound',
        'status' => 'completed',
        'channel' => Call::CHANNEL_CONVERSATION_AI,
        'conversation_status' => 'completed',
        'resolution_type' => 'answered',
        'conversation_summary' => 'La demande a ete resolue sans humain.',
        'from_number' => '+32470004001',
        'to_number' => $phoneNumber->phone_number,
        'started_at' => now()->subMinutes(12),
        'ended_at' => now()->subMinutes(11),
        'summary' => 'Resolution directe par l IA.',
    ]);

    $transferredCall = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE4_OVERVIEW_2',
        'direction' => 'inbound',
        'status' => 'transferred',
        'channel' => Call::CHANNEL_CONVERSATION_AI,
        'conversation_status' => 'completed',
        'resolution_type' => 'transferred',
        'conversation_summary' => 'Le caller a ete transfere.',
        'escalation_reason' => 'caller_requested_human',
        'from_number' => '+32470004002',
        'to_number' => $phoneNumber->phone_number,
        'started_at' => now()->subMinutes(10),
        'ended_at' => now()->subMinutes(9),
        'summary' => 'Transfert humain demande.',
    ]);

    $fallbackCall = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE4_OVERVIEW_3',
        'direction' => 'inbound',
        'status' => 'voicemail_prompted',
        'channel' => Call::CHANNEL_CONVERSATION_AI,
        'conversation_status' => 'fallback_requested',
        'conversation_summary' => 'Le runtime a demande une prise de message.',
        'escalation_reason' => 'caller_requested_human',
        'from_number' => '+32470004003',
        'to_number' => $phoneNumber->phone_number,
        'started_at' => now()->subMinutes(8),
        'summary' => 'Fallback vers message vocal.',
    ]);

    Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE4_OVERVIEW_4',
        'direction' => 'inbound',
        'status' => 'voicemail_received',
        'from_number' => '+32470004004',
        'to_number' => $phoneNumber->phone_number,
        'started_at' => now()->subMinutes(6),
        'ended_at' => now()->subMinutes(5),
        'summary' => 'Appel classique hors runtime conversationnel.',
    ]);

    CallTurn::create([
        'call_id' => $answeredCall->id,
        'speaker' => 'caller',
        'text' => 'Quels sont vos horaires ?',
        'sequence' => 1,
    ]);

    CallTurn::create([
        'call_id' => $answeredCall->id,
        'speaker' => 'assistant',
        'text' => 'Nous sommes ouverts de 9h a 18h.',
        'sequence' => 2,
        'meta' => [
            'decision' => 'answer',
            'source' => 'sidecar.openai',
            'decision_provider' => 'openai',
            'decision_model' => 'gpt-5.4-mini',
            'decision_latency_ms' => 420,
        ],
    ]);

    CallTurn::create([
        'call_id' => $transferredCall->id,
        'speaker' => 'caller',
        'text' => 'Je veux parler a quelqu un.',
        'sequence' => 1,
    ]);

    CallTurn::create([
        'call_id' => $transferredCall->id,
        'speaker' => 'assistant',
        'text' => 'Pouvez-vous preciser ?',
        'sequence' => 2,
        'meta' => [
            'decision' => 'clarify',
            'source' => 'sidecar.heuristic',
        ],
    ]);

    CallTurn::create([
        'call_id' => $transferredCall->id,
        'speaker' => 'caller',
        'text' => 'J ai besoin d un humain.',
        'sequence' => 3,
    ]);

    CallTurn::create([
        'call_id' => $transferredCall->id,
        'speaker' => 'assistant',
        'text' => 'Je vous transfere.',
        'sequence' => 4,
        'meta' => [
            'decision' => 'transfer',
            'source' => 'sidecar.heuristic',
        ],
    ]);

    CallTurn::create([
        'call_id' => $fallbackCall->id,
        'speaker' => 'caller',
        'text' => 'Je dois laisser un message ?',
        'sequence' => 1,
    ]);

    CallTurn::create([
        'call_id' => $fallbackCall->id,
        'speaker' => 'assistant',
        'text' => 'Je vais prendre un message pour l equipe.',
        'sequence' => 2,
        'meta' => [
            'decision' => 'fallback',
            'source' => 'sidecar.heuristic_fallback',
            'decision_provider' => 'openai',
            'decision_model' => 'gpt-5.4-mini',
            'decision_latency_ms' => 12000,
            'decision_error' => 'OpenAI decision request timed out after 12000ms.',
            'decision_error_code' => 'openai_timeout',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Overview')
            ->where('conversationMetrics.0.label', 'Appels IA')
            ->where('conversationMetrics.0.value', 3)
            ->where('conversationMetrics.1.label', 'Résolus sans humain')
            ->where('conversationMetrics.1.value', 1)
            ->where('conversationMetrics.2.label', 'Transferts IA')
            ->where('conversationMetrics.2.value', 1)
            ->where('conversationMetrics.3.label', 'Taux de résolution')
            ->where('conversationMetrics.3.value', '67%')
            ->where('conversationMetrics.3.tone', 'warning')
            ->where('conversationMetrics.4.label', 'Fallbacks IA')
            ->where('conversationMetrics.4.value', 1)
            ->where('conversationAnalytics.0.label', 'Tours moyens')
            ->where('conversationAnalytics.0.value', '2.7')
            ->where('conversationAnalytics.1.label', 'Clarifications moyennes')
            ->where('conversationAnalytics.1.value', '0.3')
            ->where('conversationAnalytics.2.label', 'Appels avec OpenAI')
            ->where('conversationAnalytics.2.value', '33%')
            ->where('conversationAnalytics.3.label', 'Escalade dominante')
            ->where('conversationAnalytics.3.value', 'Caller Requested Human')
            ->where('conversationReliabilityMetrics.0.label', 'Tentatives OpenAI')
            ->where('conversationReliabilityMetrics.0.value', 2)
            ->where('conversationReliabilityMetrics.1.label', 'Reussite OpenAI')
            ->where('conversationReliabilityMetrics.1.value', '50%')
            ->where('conversationReliabilityMetrics.2.label', 'Timeouts OpenAI')
            ->where('conversationReliabilityMetrics.2.value', 1)
            ->where('conversationReliabilityMetrics.3.label', 'Latence moyenne OpenAI')
            ->where('conversationReliabilityMetrics.3.value', '6210 ms')
            ->where('conversationReliabilityMetrics.4.label', 'Fallbacks OpenAI')
            ->where('conversationReliabilityMetrics.4.value', 1));
});

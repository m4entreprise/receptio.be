<?php

use App\Jobs\ProcessVoicemailInsights;
use App\Mail\VoicemailReceivedMail;
use App\Models\ActivityLog;
use App\Models\AgentConfig;
use App\Models\Call;
use App\Models\CallMessage;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;
use App\Support\VoicemailInsights\GeneratesVoicemailInsights;
use App\Support\VoicemailInsights\HeuristicVoicemailInsightGenerator;
use App\Support\VoicemailInsights\OpenAiVoicemailInsightGenerator;
use App\Support\VoicemailInsights\VoicemailInsights;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function createPhaseFiveWorkspace(): array
{
    $tenant = Tenant::create([
        'name' => 'Tenant Phase 5',
        'slug' => 'tenant-phase-5',
        'locale' => 'fr-BE',
        'timezone' => 'Europe/Brussels',
    ]);

    AgentConfig::create([
        'tenant_id' => $tenant->id,
        'agent_name' => 'Sophie',
        'welcome_message' => 'Bonjour, vous etes bien chez Tenant Phase 5.',
        'after_hours_message' => 'Nous sommes fermes.',
        'notification_email' => 'ops-phase5@example.com',
        'transfer_phone_number' => '+32470008888',
    ]);

    $phoneNumber = PhoneNumber::create([
        'tenant_id' => $tenant->id,
        'provider' => 'twilio',
        'label' => 'Ligne principale',
        'phone_number' => '+3222222222',
        'is_active' => true,
        'is_primary' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    return compact('tenant', 'phoneNumber', 'user');
}

test('recording webhook queues voicemail insights processing', function () {
    Queue::fake();
    Mail::fake();

    ['tenant' => $tenant, 'phoneNumber' => $phoneNumber] = createPhaseFiveWorkspace();

    Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE5_RECORDING',
        'direction' => 'inbound',
        'status' => 'voicemail_prompted',
        'from_number' => '+32470004040',
        'to_number' => $phoneNumber->phone_number,
        'started_at' => now()->subMinute(),
    ]);

    $this->post(route('webhooks.twilio.voice.recording'), [
        'CallSid' => 'CA_PHASE5_RECORDING',
        'From' => '+32470004040',
        'RecordingUrl' => 'https://example.test/phase5-recording.mp3',
        'RecordingDuration' => '27',
    ])->assertOk();

    $message = CallMessage::first();

    expect($message)->not->toBeNull()
        ->and($message->transcription_status)->toBe(CallMessage::TRANSCRIPTION_STATUS_PENDING);

    Queue::assertPushed(ProcessVoicemailInsights::class);
    Mail::assertSent(VoicemailReceivedMail::class);
});

test('processing voicemail insights enriches the message and call', function () {
    ['tenant' => $tenant, 'phoneNumber' => $phoneNumber] = createPhaseFiveWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE5_PROCESS',
        'direction' => 'inbound',
        'status' => 'voicemail_received',
        'from_number' => '+32470005050',
        'to_number' => $phoneNumber->phone_number,
        'summary' => 'Message vocal recu depuis le webhook Twilio.',
    ]);

    $message = CallMessage::create([
        'tenant_id' => $tenant->id,
        'call_id' => $call->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Client analyse',
        'caller_number' => '+32470005050',
        'message_text' => 'Message vocal reçu.',
        'recording_url' => 'https://example.test/phase5-recording.mp3',
        'transcription_status' => CallMessage::TRANSCRIPTION_STATUS_PENDING,
    ]);

    app()->instance(GeneratesVoicemailInsights::class, new class implements GeneratesVoicemailInsights
    {
        public function generate(Call $call, CallMessage $message): VoicemailInsights
        {
            return new VoicemailInsights(
                transcript: 'Bonjour, je souhaite un devis rapidement pour demain.',
                transcriptionStatus: CallMessage::TRANSCRIPTION_STATUS_COMPLETED,
                provider: 'fake-ai',
                summary: 'Demande de devis urgente pour demain.',
                intent: 'commercial',
                urgency: 'high',
                error: null,
            );
        }
    });

    dispatch_sync(new ProcessVoicemailInsights($call->id));

    $message->refresh();
    $call->refresh();

    expect($message->message_text)->toBe('Bonjour, je souhaite un devis rapidement pour demain.')
        ->and($message->ai_summary)->toBe('Demande de devis urgente pour demain.')
        ->and($message->ai_intent)->toBe('commercial')
        ->and($message->urgency_level)->toBe('high')
        ->and($message->transcript_provider)->toBe('fake-ai')
        ->and($call->transcript)->toBe('Bonjour, je souhaite un devis rapidement pour demain.')
        ->and($call->summary)->toBe('Demande de devis urgente pour demain.');

    expect(ActivityLog::where('call_message_id', $message->id)->where('event_type', 'voicemail_insights_generated')->exists())->toBeTrue();
});

test('messages page exposes voicemail insights in the dashboard', function () {
    ['tenant' => $tenant, 'phoneNumber' => $phoneNumber, 'user' => $user] = createPhaseFiveWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE5_PAGE',
        'direction' => 'inbound',
        'status' => 'voicemail_received',
        'from_number' => '+32470006060',
        'to_number' => $phoneNumber->phone_number,
        'summary' => 'Demande de support prioritaire.',
    ]);

    CallMessage::create([
        'tenant_id' => $tenant->id,
        'call_id' => $call->id,
        'status' => CallMessage::STATUS_IN_PROGRESS,
        'caller_name' => 'Client dashboard',
        'caller_number' => '+32470006060',
        'message_text' => 'J ai un probleme critique sur mon installation.',
        'recording_url' => 'https://example.test/phase5-page.mp3',
        'transcription_status' => CallMessage::TRANSCRIPTION_STATUS_COMPLETED,
        'transcript_provider' => 'fake-ai',
        'ai_summary' => 'Incident critique a traiter rapidement.',
        'ai_intent' => 'support',
        'urgency_level' => 'high',
        'transcription_processed_at' => now(),
        'automation_processed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.messages'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Messages')
            ->where('messages.0.ai_summary', 'Incident critique a traiter rapidement.')
            ->where('messages.0.ai_intent', 'support')
            ->where('messages.0.urgency_level', 'high')
            ->where('messages.0.transcription_status', CallMessage::TRANSCRIPTION_STATUS_COMPLETED));
});

test('openai insight generator uses separate transcription and text models', function () {
    ['tenant' => $tenant, 'phoneNumber' => $phoneNumber] = createPhaseFiveWorkspace();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE5_OPENAI',
        'direction' => 'inbound',
        'status' => 'voicemail_received',
        'from_number' => '+32470007070',
        'to_number' => $phoneNumber->phone_number,
    ]);

    $message = CallMessage::create([
        'tenant_id' => $tenant->id,
        'call_id' => $call->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Client openai',
        'caller_number' => '+32470007070',
        'message_text' => 'Message vocal reçu.',
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE123',
        'transcription_status' => CallMessage::TRANSCRIPTION_STATUS_PENDING,
    ]);

    Http::fake([
        'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE123.mp3' => Http::response('fake audio', 200),
        'https://api.openai.com/v1/audio/transcriptions' => Http::response([
            'text' => 'Bonjour, je souhaite un devis rapidement pour demain.',
        ], 200),
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'summary' => 'Demande de devis urgente pour demain.',
                        'intent' => 'commercial',
                        'urgency' => 'high',
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ], 200),
    ]);

    $generator = new OpenAiVoicemailInsightGenerator(
        http: app(HttpFactory::class),
        heuristic: app(HeuristicVoicemailInsightGenerator::class),
        apiKey: 'test-key',
        transcriptionModel: 'gpt-4o-mini-transcribe',
        textModel: 'gpt-5.4-mini',
    );

    $insights = $generator->generate($call, $message);

    expect($insights->transcript)->toBe('Bonjour, je souhaite un devis rapidement pour demain.')
        ->and($insights->summary)->toBe('Demande de devis urgente pour demain.')
        ->and($insights->intent)->toBe('commercial')
        ->and($insights->urgency)->toBe('high')
        ->and($insights->provider)->toBe('openai:gpt-4o-mini-transcribe+gpt-5.4-mini');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/audio/transcriptions'
        && str_contains($request->body(), 'gpt-4o-mini-transcribe'));

    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
        && $request['model'] === 'gpt-5.4-mini');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Recordings/RE123.mp3');
});

<?php

use App\Mail\VoicemailReceivedMail;
use App\Models\ActivityLog;
use App\Models\AgentConfig;
use App\Models\Call;
use App\Models\CallMessage;
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

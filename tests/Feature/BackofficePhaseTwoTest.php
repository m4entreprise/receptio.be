<?php

use App\Models\Call;
use App\Models\CallMessage;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function backofficeTenantContext(): array
{
    $tenant = Tenant::create([
        'name' => 'Tenant Phase 2',
        'slug' => 'tenant-phase-2',
        'locale' => 'fr-BE',
        'timezone' => 'Europe/Brussels',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $phoneNumber = PhoneNumber::create([
        'tenant_id' => $tenant->id,
        'provider' => 'twilio',
        'label' => 'Ligne principale',
        'phone_number' => '+3220000001',
        'is_active' => true,
    ]);

    return compact('tenant', 'user', 'phoneNumber');
}

test('an authenticated user can open the call detail page for their tenant', function () {
    ['tenant' => $tenant, 'user' => $user, 'phoneNumber' => $phoneNumber] = backofficeTenantContext();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'external_sid' => 'CA_PHASE2_DETAIL',
        'direction' => 'inbound',
        'status' => 'voicemail_received',
        'from_number' => '+32470000001',
        'to_number' => $phoneNumber->phone_number,
        'started_at' => now()->subMinutes(20),
        'ended_at' => now()->subMinutes(18),
        'summary' => 'Le client souhaite être rappelé.',
        'metadata' => [
            'status_events' => [
                ['received_at' => now()->subMinutes(20)->toIso8601String(), 'call_status' => 'initiated'],
            ],
        ],
    ]);

    CallMessage::create([
        'tenant_id' => $tenant->id,
        'call_id' => $call->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Jean Dupont',
        'caller_number' => '+32470000001',
        'message_text' => 'Merci de me rappeler demain.',
        'recording_url' => 'https://example.test/recording.mp3',
        'recording_duration' => 31,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.calls.show', $call->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/CallDetail')
            ->where('call.id', $call->id)
            ->where('call.external_sid', 'CA_PHASE2_DETAIL')
            ->where('call.message.workflow_status', CallMessage::STATUS_NEW));
});

test('an authenticated user can update a message workflow status', function () {
    ['tenant' => $tenant, 'user' => $user, 'phoneNumber' => $phoneNumber] = backofficeTenantContext();

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'status' => 'voicemail_received',
        'from_number' => '+32470000002',
        'to_number' => $phoneNumber->phone_number,
    ]);

    $message = CallMessage::create([
        'tenant_id' => $tenant->id,
        'call_id' => $call->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Alice Martin',
        'caller_number' => '+32470000002',
        'message_text' => 'Merci de me rappeler rapidement.',
    ]);

    $this->actingAs($user)
        ->patch(route('dashboard.messages.update', $message->id), [
            'status' => CallMessage::STATUS_CALLED_BACK,
        ])
        ->assertRedirect();

    $message->refresh();

    expect($message->status)->toBe(CallMessage::STATUS_CALLED_BACK);
    expect($message->assigned_to_user_id)->toBe($user->id);
    expect($message->handled_by_user_id)->toBe($user->id);
    expect($message->handled_at)->not->toBeNull();
});

test('messages page filters the inbox by workflow status', function () {
    ['tenant' => $tenant, 'user' => $user, 'phoneNumber' => $phoneNumber] = backofficeTenantContext();

    $callNew = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'status' => 'voicemail_received',
        'from_number' => '+32470000003',
        'to_number' => $phoneNumber->phone_number,
        'summary' => 'Nouveau message à traiter',
    ]);

    $callClosed = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'status' => 'completed',
        'from_number' => '+32470000004',
        'to_number' => $phoneNumber->phone_number,
        'summary' => 'Message déjà traité',
    ]);

    $newMessage = CallMessage::create([
        'tenant_id' => $tenant->id,
        'call_id' => $callNew->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Nouveau Contact',
        'caller_number' => '+32470000003',
        'message_text' => 'Je voudrais un devis.',
    ]);

    CallMessage::create([
        'tenant_id' => $tenant->id,
        'call_id' => $callClosed->id,
        'status' => CallMessage::STATUS_CLOSED,
        'caller_name' => 'Ancien Contact',
        'caller_number' => '+32470000004',
        'message_text' => 'Dossier terminé.',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.messages', ['status' => CallMessage::STATUS_NEW]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Messages')
            ->has('messages', 1)
            ->where('messages.0.id', $newMessage->id)
            ->where('appliedFilters.status', CallMessage::STATUS_NEW));
});

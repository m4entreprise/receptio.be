<?php

use App\Models\AgentConfig;
use App\Models\Call;
use App\Models\CallMessage;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function createTenantWorkspace(string $suffix): array
{
    $tenant = Tenant::create([
        'name' => "Tenant {$suffix}",
        'slug' => "tenant-{$suffix}",
        'locale' => 'fr-BE',
        'timezone' => 'Europe/Brussels',
    ]);

    AgentConfig::create([
        'tenant_id' => $tenant->id,
        'agent_name' => "Agent {$suffix}",
        'welcome_message' => "Bonjour, vous êtes bien chez Tenant {$suffix}.",
        'after_hours_message' => 'Nous sommes fermés.',
        'notification_email' => "{$suffix}@example.com",
    ]);

    $phoneNumber = PhoneNumber::create([
        'tenant_id' => $tenant->id,
        'provider' => 'twilio',
        'label' => 'Ligne principale',
        'phone_number' => "+32200000{$suffix}",
        'is_active' => true,
        'is_primary' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => "{$suffix}.user@example.com",
    ]);

    return compact('tenant', 'phoneNumber', 'user');
}

test('backoffice messages only expose the authenticated tenant data', function () {
    ['tenant' => $tenantA, 'phoneNumber' => $phoneA, 'user' => $userA] = createTenantWorkspace('01');
    ['tenant' => $tenantB, 'phoneNumber' => $phoneB] = createTenantWorkspace('02');

    $callA = Call::create([
        'tenant_id' => $tenantA->id,
        'phone_number_id' => $phoneA->id,
        'status' => 'voicemail_received',
        'from_number' => '+32470000011',
        'to_number' => $phoneA->phone_number,
        'summary' => 'Message du tenant A',
    ]);

    $callB = Call::create([
        'tenant_id' => $tenantB->id,
        'phone_number_id' => $phoneB->id,
        'status' => 'voicemail_received',
        'from_number' => '+32470000022',
        'to_number' => $phoneB->phone_number,
        'summary' => 'Message du tenant B',
    ]);

    $messageA = CallMessage::create([
        'tenant_id' => $tenantA->id,
        'call_id' => $callA->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Client A',
        'caller_number' => '+32470000011',
        'message_text' => 'Merci de rappeler le tenant A.',
    ]);

    CallMessage::create([
        'tenant_id' => $tenantB->id,
        'call_id' => $callB->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Client B',
        'caller_number' => '+32470000022',
        'message_text' => 'Merci de rappeler le tenant B.',
    ]);

    $this->actingAs($userA)
        ->get(route('dashboard.messages'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Messages')
            ->has('messages', 1)
            ->where('messages.0.id', $messageA->id)
            ->where('messages.0.caller', 'Client A'));
});

test('call detail from another tenant returns not found', function () {
    ['tenant' => $tenantA, 'phoneNumber' => $phoneA, 'user' => $userA] = createTenantWorkspace('11');
    ['tenant' => $tenantB, 'phoneNumber' => $phoneB] = createTenantWorkspace('12');

    $foreignCall = Call::create([
        'tenant_id' => $tenantB->id,
        'phone_number_id' => $phoneB->id,
        'status' => 'completed',
        'from_number' => '+32470000122',
        'to_number' => $phoneB->phone_number,
    ]);

    $this->actingAs($userA)
        ->get(route('dashboard.calls.show', $foreignCall->id))
        ->assertNotFound();
});

test('message workflow cannot be updated across tenants', function () {
    ['tenant' => $tenantA, 'phoneNumber' => $phoneA, 'user' => $userA] = createTenantWorkspace('21');
    ['tenant' => $tenantB, 'phoneNumber' => $phoneB] = createTenantWorkspace('22');

    $foreignCall = Call::create([
        'tenant_id' => $tenantB->id,
        'phone_number_id' => $phoneB->id,
        'status' => 'voicemail_received',
        'from_number' => '+32470000222',
        'to_number' => $phoneB->phone_number,
    ]);

    $foreignMessage = CallMessage::create([
        'tenant_id' => $tenantB->id,
        'call_id' => $foreignCall->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Client B',
        'caller_number' => '+32470000222',
        'message_text' => 'Message tenant B.',
    ]);

    $this->actingAs($userA)
        ->patch(route('dashboard.messages.update', $foreignMessage->id), [
            'status' => CallMessage::STATUS_CLOSED,
        ])
        ->assertNotFound();

    expect($foreignMessage->fresh()->status)->toBe(CallMessage::STATUS_NEW);
});

test('user without tenant does not fallback to another tenant dashboard data', function () {
    ['tenant' => $tenant, 'phoneNumber' => $phoneNumber] = createTenantWorkspace('31');

    $call = Call::create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumber->id,
        'status' => 'voicemail_received',
        'from_number' => '+32470000311',
        'to_number' => $phoneNumber->phone_number,
    ]);

    CallMessage::create([
        'tenant_id' => $tenant->id,
        'call_id' => $call->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Client visible',
        'caller_number' => '+32470000311',
        'message_text' => 'Message à ne pas exposer.',
    ]);

    $userWithoutTenant = User::factory()->create([
        'tenant_id' => null,
    ]);

    $this->actingAs($userWithoutTenant)
        ->get(route('dashboard.messages'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Messages')
            ->has('messages', 0)
            ->where('tenant', null));
});

test('unknown called number does not attach an incoming call to an arbitrary tenant', function () {
    createTenantWorkspace('41');

    $response = $this->post(route('webhooks.twilio.voice.incoming'), [
        'CallSid' => 'CA_UNKNOWN_NUMBER',
        'From' => '+32470000411',
        'To' => '+32999999999',
    ]);

    $response->assertOk();
    $response->assertSee('temporairement indisponible', false);

    expect(Call::count())->toBe(0);
});

test('settings update promotes the chosen tenant number as primary', function () {
    ['tenant' => $tenant, 'phoneNumber' => $primaryPhoneNumber, 'user' => $user] = createTenantWorkspace('51');

    $secondaryPhoneNumber = PhoneNumber::create([
        'tenant_id' => $tenant->id,
        'provider' => 'twilio',
        'label' => 'Ligne secondaire',
        'phone_number' => '+3220000052',
        'is_active' => true,
        'is_primary' => false,
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.settings.update'), [
            'tenant_name' => $tenant->name,
            'agent_name' => 'Agent 51',
            'welcome_message' => 'Bienvenue chez Tenant 51.',
            'after_hours_message' => 'Nous sommes fermés.',
            'faq_content' => '',
            'transfer_phone_number' => '',
            'notification_email' => '51@example.com',
            'opens_at' => '',
            'closes_at' => '',
            'phone_number' => $secondaryPhoneNumber->phone_number,
            'business_days' => ['monday'],
        ])
        ->assertRedirect();

    expect($secondaryPhoneNumber->fresh()->is_primary)->toBeTrue();
    expect($primaryPhoneNumber->fresh()->is_primary)->toBeFalse();
});

test('recording proxy cannot be accessed across tenants', function () {
    ['tenant' => $tenantA, 'phoneNumber' => $phoneA, 'user' => $userA] = createTenantWorkspace('61');
    ['tenant' => $tenantB, 'phoneNumber' => $phoneB] = createTenantWorkspace('62');

    $foreignCall = Call::create([
        'tenant_id' => $tenantB->id,
        'phone_number_id' => $phoneB->id,
        'status' => 'voicemail_received',
        'from_number' => '+32470000622',
        'to_number' => $phoneB->phone_number,
    ]);

    $foreignMessage = CallMessage::create([
        'tenant_id' => $tenantB->id,
        'call_id' => $foreignCall->id,
        'status' => CallMessage::STATUS_NEW,
        'caller_name' => 'Client B',
        'caller_number' => '+32470000622',
        'message_text' => 'Audio prive.',
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC999/Recordings/RE999.mp3',
    ]);

    Http::fake();

    $this->actingAs($userA)
        ->get(route('dashboard.messages.recording', $foreignMessage->id))
        ->assertNotFound();

    Http::assertNothingSent();
});

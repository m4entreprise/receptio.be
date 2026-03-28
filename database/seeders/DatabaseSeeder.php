<?php

namespace Database\Seeders;

use App\Models\AgentConfig;
use App\Models\Call;
use App\Models\CallMessage;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'receptio-demo'],
            [
                'name' => 'Receptio Demo',
                'locale' => 'fr-BE',
                'timezone' => 'Europe/Brussels',
            ],
        );

        AgentConfig::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'agent_name' => 'Sophie',
                'welcome_message' => 'Bonjour, vous êtes bien chez Receptio Demo. Tapez 1 pour être transféré ou laissez-nous un message.',
                'after_hours_message' => 'Nous sommes actuellement fermés. Merci de laisser un message et nous vous rappellerons.',
                'faq_content' => "Horaires: du lundi au vendredi, de 09h00 à 18h00.\nAdresse: Bruxelles.\nUrgences: transfert prioritaire vers l'équipe.",
                'transfer_phone_number' => '+32470000000',
                'notification_email' => 'test@example.com',
                'opens_at' => '09:00',
                'closes_at' => '18:00',
                'business_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            ],
        );

        PhoneNumber::updateOrCreate(
            ['phone_number' => '+3220000000'],
            [
                'tenant_id' => $tenant->id,
                'provider' => 'twilio',
                'label' => 'Ligne principale',
                'is_active' => true,
            ],
        );

        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
        );

        $call = Call::updateOrCreate(
            ['external_sid' => 'CA_DEMO_RECEPTIO'],
            [
                'tenant_id' => $tenant->id,
                'phone_number_id' => $tenant->phoneNumbers()->first()?->id,
                'direction' => 'inbound',
                'status' => 'voicemail_received',
                'from_number' => '+32471234567',
                'to_number' => '+3220000000',
                'started_at' => now()->subMinutes(35),
                'ended_at' => now()->subMinutes(33),
                'summary' => 'Demande de rappel commercial laissée sur la boîte vocale.',
                'metadata' => ['source' => 'database_seeder'],
            ],
        );

        CallMessage::updateOrCreate(
            ['call_id' => $call->id],
            [
                'tenant_id' => $tenant->id,
                'caller_name' => 'Jean Dupont',
                'caller_number' => '+32471234567',
                'status' => CallMessage::STATUS_NEW,
                'message_text' => 'Bonjour, je souhaite être rappelé demain matin pour une démonstration.',
                'recording_url' => 'https://api.twilio.com/demo-recording',
                'recording_duration' => 48,
                'notified_at' => now()->subMinutes(32),
            ],
        );

        User::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Test User',
            'email' => 'demo+'.now()->timestamp.'@example.com',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AgentConfig;
use App\Models\Call;
use App\Models\CallMessage;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class TwilioVoiceWebhookController extends Controller
{
    public function incoming(Request $request): Response
    {
        $phoneNumber = $this->resolvePhoneNumber($request->string('To')->toString());
        $tenant = $phoneNumber?->tenant ?? Tenant::with('agentConfig')->first();
        $agentConfig = $tenant?->agentConfig;

        if (! $tenant || ! $agentConfig) {
            return $this->xmlResponse($this->buildTwiml([
                '<Say language="fr-BE">Le service est temporairement indisponible. Merci de rappeler plus tard.</Say>',
            ]));
        }

        $call = Call::updateOrCreate(
            ['external_sid' => $request->string('CallSid')->toString()],
            [
                'tenant_id' => $tenant->id,
                'phone_number_id' => $phoneNumber?->id,
                'direction' => 'inbound',
                'status' => 'in_progress',
                'from_number' => $request->string('From')->toString(),
                'to_number' => $request->string('To')->toString(),
                'started_at' => now(),
                'metadata' => $request->all(),
            ],
        );

        if (! $this->isOpen($agentConfig)) {
            $call->update(['status' => 'after_hours']);

            return $this->xmlResponse($this->buildTwiml([
                $this->say($agentConfig->after_hours_message ?: 'Nous sommes actuellement indisponibles. Merci de laisser un message après le bip.'),
                $this->record(route('webhooks.twilio.voice.recording', absolute: true)),
            ]));
        }

        if (blank($agentConfig->transfer_phone_number)) {
            return $this->xmlResponse($this->buildTwiml([
                $this->say($agentConfig->welcome_message ?: "Bonjour, vous êtes bien chez {$tenant->name}. Laissez-nous un message après le bip."),
                $this->record(route('webhooks.twilio.voice.recording', absolute: true)),
            ]));
        }

        return $this->xmlResponse($this->buildTwiml([
            $this->gather(
                route('webhooks.twilio.voice.menu', absolute: true),
                [
                    $this->say($agentConfig->welcome_message ?: "Bonjour, vous êtes bien chez {$tenant->name}."),
                    '<Pause length="1"/>',
                    '<Say language="fr-BE">Tapez 1 pour être transféré ou restez en ligne pour laisser un message.</Say>',
                ],
            ),
            $this->record(route('webhooks.twilio.voice.recording', absolute: true)),
        ]));
    }

    public function menu(Request $request): Response
    {
        $call = Call::where('external_sid', $request->string('CallSid')->toString())->first();
        $tenant = $call?->tenant ?? Tenant::with('agentConfig')->first();
        $agentConfig = $tenant?->agentConfig;

        if ($request->string('Digits')->toString() === '1' && filled($agentConfig?->transfer_phone_number)) {
            $call?->update(['status' => 'transferring']);

            return $this->xmlResponse($this->buildTwiml([
                '<Say language="fr-BE">Nous vous transférons immédiatement.</Say>',
                '<Dial>' . e($agentConfig->transfer_phone_number) . '</Dial>',
            ]));
        }

        $call?->update(['status' => 'voicemail_prompted']);

        return $this->xmlResponse($this->buildTwiml([
            '<Say language="fr-BE">Merci. Laissez votre message après le bip.</Say>',
            $this->record(route('webhooks.twilio.voice.recording', absolute: true)),
        ]));
    }

    public function recording(Request $request): Response
    {
        $call = Call::where('external_sid', $request->string('CallSid')->toString())->first();

        if ($call) {
            CallMessage::updateOrCreate(
                ['call_id' => $call->id],
                [
                    'tenant_id' => $call->tenant_id,
                    'caller_number' => $request->string('From')->toString() ?: $call->from_number,
                    'recording_url' => $request->string('RecordingUrl')->toString(),
                    'recording_duration' => $request->integer('RecordingDuration') ?: null,
                    'message_text' => 'Message vocal reçu.',
                    'notified_at' => now(),
                ],
            );

            $call->update([
                'status' => 'voicemail_received',
                'ended_at' => now(),
                'summary' => 'Message vocal reçu depuis le webhook Twilio.',
            ]);

            $notificationEmail = $call->tenant->agentConfig?->notification_email;

            if ($notificationEmail) {
                Mail::raw(
                    "Nouveau message vocal pour {$call->tenant->name}.\n\nAppelant: {$call->from_number}\nEnregistrement: {$request->string('RecordingUrl')->toString()}",
                    fn ($message) => $message->to($notificationEmail)->subject('Receptio - nouveau message vocal'),
                );
            }
        }

        return $this->xmlResponse($this->buildTwiml([
            '<Say language="fr-BE">Merci. Votre message a bien été enregistré. Au revoir.</Say>',
            '<Hangup/>',
        ]));
    }

    private function buildTwiml(array $verbs): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response>" . implode('', $verbs) . '</Response>';
    }

    private function gather(string $action, array $verbs): string
    {
        return '<Gather input="dtmf" numDigits="1" timeout="4" action="' . e($action) . '" method="POST">' . implode('', $verbs) . '</Gather>';
    }

    private function record(string $action): string
    {
        return '<Record action="' . e($action) . '" method="POST" maxLength="120" playBeep="true" trim="trim-silence" />';
    }

    private function say(string $message): string
    {
        return '<Say language="fr-BE">' . e($message) . '</Say>';
    }

    private function resolvePhoneNumber(string $phoneNumber): ?PhoneNumber
    {
        return PhoneNumber::where('phone_number', $phoneNumber)
            ->orWhere('phone_number', preg_replace('/\s+/', '', $phoneNumber))
            ->first();
    }

    private function isOpen(AgentConfig $agentConfig): bool
    {
        if (! $agentConfig->opens_at || ! $agentConfig->closes_at || empty($agentConfig->business_days)) {
            return true;
        }

        $now = now($agentConfig->tenant->timezone ?? 'Europe/Brussels');
        $day = strtolower($now->englishDayOfWeek);

        if (! in_array($day, $agentConfig->business_days, true)) {
            return false;
        }

        $currentTime = $now->format('H:i');

        return $currentTime >= $agentConfig->opens_at && $currentTime <= $agentConfig->closes_at;
    }

    private function xmlResponse(string $content): Response
    {
        return response($content, 200, ['Content-Type' => 'text/xml']);
    }
}

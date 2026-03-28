<?php

namespace App\Http\Controllers;

use App\Models\AgentConfig;
use App\Models\Call;
use App\Models\CallMessage;
use App\Models\PhoneNumber;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TwilioVoiceWebhookController extends Controller
{
    public function __construct(private readonly TenantResolver $tenantResolver) {}

    public function incoming(Request $request): Response
    {
        $phoneNumber = $this->resolvePhoneNumber($request->string('To')->toString());
        $tenant = $phoneNumber?->tenant;
        $agentConfig = $tenant?->agentConfig;

        if (! $tenant || ! $agentConfig) {
            Log::error('twilio.webhook.incoming_unroutable', $this->webhookContext($request, null, [
                'resolved_phone_number_id' => $phoneNumber?->id,
            ]));

            return $this->unavailableResponse();
        }

        $call = Call::updateOrCreate(
            ['external_sid' => $request->string('CallSid')->toString()],
            [
                'tenant_id' => $tenant->id,
                'phone_number_id' => $phoneNumber?->id,
                'direction' => 'inbound',
                'status' => 'received',
                'from_number' => $request->string('From')->toString(),
                'to_number' => $request->string('To')->toString(),
                'started_at' => now(),
                'metadata' => $request->all(),
            ],
        );

        Log::info('twilio.webhook.incoming_received', $this->webhookContext($request, $call, [
            'resolved_phone_number_id' => $phoneNumber?->id,
        ]));

        if (! $this->isOpen($agentConfig)) {
            $call->update(['status' => 'after_hours']);

            Log::info('twilio.webhook.incoming_after_hours', $this->webhookContext($request, $call));

            return $this->xmlResponse($this->buildTwiml([
                $this->say($agentConfig->after_hours_message ?: 'Nous sommes actuellement indisponibles. Merci de laisser un message après le bip.'),
                $this->record(route('webhooks.twilio.voice.recording', absolute: true)),
            ]));
        }

        if (blank($agentConfig->transfer_phone_number)) {
            $call->update(['status' => 'voicemail_prompted']);

            Log::info('twilio.webhook.incoming_voicemail_direct', $this->webhookContext($request, $call));

            return $this->xmlResponse($this->buildTwiml([
                $this->say($agentConfig->welcome_message ?: "Bonjour, vous êtes bien chez {$tenant->name}. Laissez-nous un message après le bip."),
                $this->record(route('webhooks.twilio.voice.recording', absolute: true)),
            ]));
        }

        $call->update(['status' => 'menu_offered']);

        Log::info('twilio.webhook.incoming_menu_offered', $this->webhookContext($request, $call));

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
        $tenant = $call?->tenant;
        $agentConfig = $tenant?->agentConfig;

        Log::info('twilio.webhook.menu_received', $this->webhookContext($request, $call, [
            'digits' => $request->string('Digits')->toString() ?: null,
        ]));

        if (! $call || ! $tenant || ! $agentConfig) {
            Log::warning('twilio.webhook.menu_unroutable', $this->webhookContext($request, $call));

            return $this->unavailableResponse();
        }

        if ($request->string('Digits')->toString() === '1' && filled($agentConfig?->transfer_phone_number)) {
            $call->update(['status' => 'transferring']);

            Log::info('twilio.webhook.menu_transfer_selected', $this->webhookContext($request, $call));

            return $this->xmlResponse($this->buildTwiml([
                '<Say language="fr-BE">Nous vous transférons immédiatement.</Say>',
                '<Dial action="'.e(route('webhooks.twilio.voice.status', absolute: true)).'" method="POST">'.e($agentConfig->transfer_phone_number).'</Dial>',
            ]));
        }

        $call->update(['status' => 'voicemail_prompted']);

        Log::info('twilio.webhook.menu_voicemail_selected', $this->webhookContext($request, $call));

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
                'summary' => $this->voicemailSummary($call),
                'metadata' => $this->mergeMetadata($call, $this->filterNullValues([
                    'recording_url' => $request->string('RecordingUrl')->toString() ?: null,
                    'recording_duration_seconds' => $this->requestInteger($request, 'RecordingDuration'),
                ])),
            ]);

            $notificationEmail = $call->tenant->agentConfig?->notification_email;

            if ($notificationEmail) {
                Mail::raw(
                    "Nouveau message vocal pour {$call->tenant->name}.\n\nAppelant: {$call->from_number}\nEnregistrement: {$request->string('RecordingUrl')->toString()}",
                    fn ($message) => $message->to($notificationEmail)->subject('Receptio - nouveau message vocal'),
                );
            }

            Log::info('twilio.webhook.recording_received', $this->webhookContext($request, $call, [
                'recording_duration_seconds' => $this->requestInteger($request, 'RecordingDuration'),
            ]));
        } else {
            Log::warning('twilio.webhook.recording_call_not_found', $this->webhookContext($request));
        }

        return $this->xmlResponse($this->buildTwiml([
            '<Say language="fr-BE">Merci. Votre message a bien été enregistré. Au revoir.</Say>',
            '<Hangup/>',
        ]));
    }

    public function status(Request $request): Response
    {
        $call = Call::where('external_sid', $request->string('CallSid')->toString())->first()
            ?? Call::where('external_sid', $request->string('ParentCallSid')->toString())->first();

        $fallbackResponse = null;

        if (! $call) {
            Log::warning('twilio.webhook.status_call_not_found', $this->webhookContext($request));
        }

        if ($call) {
            $status = $this->resolveCallbackStatus($call, $request);
            $callDuration = $this->requestInteger($request, 'CallDuration');
            $dialCallDuration = $this->requestInteger($request, 'DialCallDuration');
            $dialStatus = $request->string('DialCallStatus')->toString();
            $statusEvent = $this->filterNullValues([
                'received_at' => now()->toIso8601String(),
                'call_status' => $request->string('CallStatus')->toString() ?: null,
                'call_duration_seconds' => $callDuration,
                'dial_call_status' => $request->string('DialCallStatus')->toString() ?: null,
                'dial_call_duration_seconds' => $dialCallDuration,
                'dial_call_sid' => $request->string('DialCallSid')->toString() ?: null,
                'callback_source' => $request->string('CallbackSource')->toString() ?: null,
                'sequence_number' => $request->input('SequenceNumber'),
            ]);
            $metadata = $this->mergeMetadata($call, [
                'last_status_callback' => $statusEvent,
                'status_events' => array_values(array_slice([
                    ...collect(data_get($call->metadata, 'status_events', []))->filter(fn ($event) => is_array($event))->all(),
                    $statusEvent,
                ], -10)),
            ]);

            if ($callDuration !== null) {
                $metadata['call_duration_seconds'] = $callDuration;
            }

            if ($dialCallDuration !== null) {
                $metadata['dial_call_duration_seconds'] = $dialCallDuration;
            }

            if (filled($request->string('CallStatus')->toString())) {
                $metadata['last_twilio_call_status'] = $request->string('CallStatus')->toString();
            }

            if (filled($request->string('DialCallStatus')->toString())) {
                $metadata['last_twilio_dial_call_status'] = $request->string('DialCallStatus')->toString();
            }

            if ($this->shouldFallbackToVoicemail($dialStatus, $call)) {
                $metadata['transfer_failure_status'] = $dialStatus;
                $metadata['transfer_failed_at'] = now()->toIso8601String();
                $metadata['fallback_target'] = 'voicemail';
                $status = 'voicemail_prompted';
                $fallbackResponse = $this->buildTransferFailureFallbackResponse($call);

                Log::warning('twilio.webhook.status_transfer_failed_fallback', $this->webhookContext($request, $call, [
                    'resolved_status' => $status,
                    'fallback_target' => 'voicemail',
                ]));
            }

            $call->update([
                'status' => $status,
                'ended_at' => $this->isTerminalStatus($status) ? ($call->ended_at ?? now()) : $call->ended_at,
                'summary' => $this->summaryForStatus($call, $status, $metadata),
                'metadata' => $metadata,
            ]);

            Log::info('twilio.webhook.status_processed', $this->webhookContext($request, $call, [
                'resolved_status' => $status,
                'has_fallback_response' => $fallbackResponse !== null,
            ]));
        }

        return $fallbackResponse ?? response('', 204);
    }

    public function ping(Request $request): Response
    {
        Log::info('twilio.webhook.ping_received', $this->webhookContext($request));

        return $this->xmlResponse($this->buildTwiml([
            '<Say language="fr-BE">OK</Say>',
            '<Hangup/>',
        ]));
    }

    private function buildTwiml(array $verbs): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Response>'.implode('', $verbs).'</Response>';
    }

    private function gather(string $action, array $verbs): string
    {
        return '<Gather input="dtmf" numDigits="1" timeout="4" action="'.e($action).'" method="POST">'.implode('', $verbs).'</Gather>';
    }

    private function record(string $action): string
    {
        return '<Record action="'.e($action).'" method="POST" maxLength="120" playBeep="true" trim="trim-silence" />';
    }

    private function say(string $message): string
    {
        return '<Say language="fr-BE">'.e($message).'</Say>';
    }

    private function summaryForStatus(Call $call, string $status, array $metadata): ?string
    {
        return match ($status) {
            'transferred' => 'Transfert humain réussi.',
            'busy', 'no_answer', 'failed' => $this->transferFailureSummary(data_get($metadata, 'transfer_failure_status', $status)),
            'voicemail_prompted' => ($metadata['fallback_target'] ?? null) === 'voicemail'
                ? $this->transferFailureSummary(data_get($metadata, 'transfer_failure_status')).' Bascule vers messagerie.'
                : ($call->summary ?? 'Messagerie proposée au caller.'),
            default => $call->summary,
        };
    }

    private function voicemailSummary(Call $call): string
    {
        $transferFailureStatus = data_get($call->metadata, 'transfer_failure_status');

        if ($transferFailureStatus) {
            return $this->transferFailureSummary($transferFailureStatus).' Message vocal reçu depuis le webhook Twilio.';
        }

        return 'Message vocal reçu depuis le webhook Twilio.';
    }

    private function transferFailureSummary(?string $status): string
    {
        return match ($status) {
            'busy' => 'Transfert humain échoué : ligne occupée.',
            'no-answer' => 'Transfert humain échoué : pas de réponse.',
            'failed' => 'Transfert humain échoué : erreur Twilio ou numéro invalide.',
            'canceled' => 'Transfert humain annulé avant aboutissement.',
            default => 'Transfert humain non abouti.',
        };
    }

    private function shouldFallbackToVoicemail(string $dialStatus, Call $call): bool
    {
        if (! in_array($dialStatus, ['busy', 'no-answer', 'failed', 'canceled'], true)) {
            return false;
        }

        return $call->message === null;
    }

    private function buildTransferFailureFallbackResponse(Call $call): Response
    {
        return $this->xmlResponse($this->buildTwiml([
            $this->say('La ligne humaine est indisponible pour le moment. Merci de laisser un message après le bip.'),
            $this->record(route('webhooks.twilio.voice.recording', absolute: true)),
        ]));
    }

    private function resolveCallbackStatus(Call $call, Request $request): string
    {
        $dialStatus = $request->string('DialCallStatus')->toString();

        if ($dialStatus !== '') {
            return match ($dialStatus) {
                'completed', 'answered' => 'transferred',
                'busy' => 'busy',
                'no-answer' => 'no_answer',
                'failed', 'canceled' => 'failed',
                default => $call->status,
            };
        }

        $callStatus = $request->string('CallStatus')->toString();

        return match ($callStatus) {
            'queued', 'initiated', 'ringing' => $call->status === 'received' ? 'received' : $call->status,
            'in-progress' => in_array($call->status, ['received', 'in_progress'], true) ? 'in_progress' : $call->status,
            'busy' => 'busy',
            'no-answer' => 'no_answer',
            'failed', 'canceled' => 'failed',
            'completed' => $this->completedStatusFor($call),
            default => $call->status,
        };
    }

    private function completedStatusFor(Call $call): string
    {
        return in_array($call->status, ['after_hours', 'transferred', 'voicemail_received', 'busy', 'no_answer', 'failed'], true)
            ? $call->status
            : 'completed';
    }

    private function isTerminalStatus(string $status): bool
    {
        return in_array($status, ['after_hours', 'busy', 'completed', 'failed', 'no_answer', 'transferred', 'voicemail_received'], true);
    }

    private function mergeMetadata(Call $call, array $attributes): array
    {
        return array_merge($call->metadata ?? [], $attributes);
    }

    private function requestInteger(Request $request, string $key): ?int
    {
        if (! $request->has($key) || $request->input($key) === '') {
            return null;
        }

        $value = $request->input($key);

        return is_numeric($value) ? (int) $value : null;
    }

    private function filterNullValues(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }

    private function webhookContext(Request $request, ?Call $call = null, array $context = []): array
    {
        return $this->filterNullValues(array_merge([
            'call_sid' => $request->string('CallSid')->toString() ?: null,
            'parent_call_sid' => $request->string('ParentCallSid')->toString() ?: null,
            'from_number' => $request->string('From')->toString() ?: null,
            'to_number' => $request->string('To')->toString() ?: null,
            'call_status' => $request->string('CallStatus')->toString() ?: null,
            'dial_call_status' => $request->string('DialCallStatus')->toString() ?: null,
            'callback_source' => $request->string('CallbackSource')->toString() ?: null,
            'tenant_id' => $call?->tenant_id,
            'call_id' => $call?->id,
            'external_sid' => $call?->external_sid,
        ], $context));
    }

    private function resolvePhoneNumber(string $phoneNumber): ?PhoneNumber
    {
        return $this->tenantResolver->resolvePhoneNumber($phoneNumber);
    }

    private function unavailableResponse(): Response
    {
        return $this->xmlResponse($this->buildTwiml([
            '<Say language="fr-BE">Le service est temporairement indisponible. Merci de rappeler plus tard.</Say>',
            '<Hangup/>',
        ]));
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

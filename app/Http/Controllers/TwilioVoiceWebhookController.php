<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVoicemailInsights;
use App\Mail\VoicemailReceivedMail;
use App\Models\AgentConfig;
use App\Models\Call;
use App\Models\CallMessage;
use App\Models\PhoneNumber;
use App\Support\ActivityLogger;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TwilioVoiceWebhookController extends Controller
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
        private readonly ActivityLogger $activityLogger,
    ) {}

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
                'channel' => Call::CHANNEL_MENU,
                'metadata' => $request->all(),
            ],
        );

        if ($call->wasRecentlyCreated) {
            $this->activityLogger->log(
                tenant: $tenant,
                eventType: 'call_received',
                title: 'Appel recu',
                description: 'Un nouvel appel entrant a ete enregistre sur la ligne '.$phoneNumber->phone_number.'.',
                call: $call,
                metadata: [
                    'from_number' => $call->from_number,
                    'to_number' => $call->to_number,
                    'phone_number_id' => $phoneNumber->id,
                ],
            );
        }

        Log::info('twilio.webhook.incoming_received', $this->webhookContext($request, $call, [
            'resolved_phone_number_id' => $phoneNumber?->id,
        ]));

        if (! $this->isOpen($agentConfig)) {
            $call->update([
                'status' => 'after_hours',
                'channel' => Call::CHANNEL_VOICEMAIL,
                'resolution_type' => 'after_hours',
            ]);

            Log::info('twilio.webhook.incoming_after_hours', $this->webhookContext($request, $call));

            return $this->xmlResponse($this->buildTwiml([
                $this->say($agentConfig->after_hours_message ?: 'Nous sommes actuellement indisponibles. Merci de laisser un message après le bip.'),
                $this->record(route('webhooks.twilio.voice.recording', absolute: true)),
            ]));
        }

        if ($agentConfig->conversation_enabled) {
            if ($this->conversationRelayConfigured()) {
                $call->update([
                    'status' => 'in_progress',
                    'channel' => Call::CHANNEL_CONVERSATION_AI,
                    'conversation_status' => 'routing',
                ]);

                Log::info('twilio.webhook.incoming_conversation_started', $this->webhookContext($request, $call, [
                    'conversation_enabled' => true,
                ]));

                return $this->xmlResponse($this->buildConversationRelayTwiml($call, $tenant, $agentConfig));
            }

            $call->update([
                'status' => 'voicemail_prompted',
                'channel' => Call::CHANNEL_CONVERSATION_AI,
                'conversation_status' => 'fallback_requested',
                'metadata' => $this->mergeMetadata($call, [
                    'fallback_target' => 'voicemail',
                    'conversation_runtime_unavailable_at' => now()->toIso8601String(),
                ]),
            ]);

            Log::warning('twilio.webhook.incoming_conversation_unavailable', $this->webhookContext($request, $call));

            return $this->conversationUnavailableFallbackResponse();
        }

        if (blank($agentConfig->transfer_phone_number)) {
            $call->update([
                'status' => 'voicemail_prompted',
                'channel' => Call::CHANNEL_VOICEMAIL,
            ]);

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

            $this->activityLogger->log(
                tenant: $tenant,
                eventType: 'transfer_attempted',
                title: 'Transfert tente',
                description: 'Le caller a demande un transfert vers la ligne humaine.',
                call: $call,
                metadata: [
                    'transfer_phone_number' => $agentConfig->transfer_phone_number,
                ],
            );

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
            $recordingUrl = $this->normalizedRecordingMediaUrl($request->string('RecordingUrl')->toString());

            $message = CallMessage::updateOrCreate(
                ['call_id' => $call->id],
                [
                    'tenant_id' => $call->tenant_id,
                    'caller_number' => $request->string('From')->toString() ?: $call->from_number,
                    'recording_url' => $recordingUrl,
                    'recording_duration' => $request->integer('RecordingDuration') ?: null,
                    'message_text' => 'Message vocal reçu.',
                    'transcription_status' => CallMessage::TRANSCRIPTION_STATUS_PENDING,
                    'transcript_provider' => null,
                    'transcription_error' => null,
                    'transcription_processed_at' => null,
                    'ai_summary' => null,
                    'ai_intent' => null,
                    'urgency_level' => null,
                    'automation_processed_at' => null,
                    'notified_at' => now(),
                ],
            );

            $call->update([
                'status' => 'voicemail_received',
                'ended_at' => now(),
                'summary' => $this->voicemailSummary($call),
                'metadata' => $this->mergeMetadata($call, $this->filterNullValues([
                    'recording_url' => $recordingUrl ?: null,
                    'recording_source_url' => $request->string('RecordingUrl')->toString() ?: null,
                    'recording_duration_seconds' => $this->requestInteger($request, 'RecordingDuration'),
                ])),
            ]);

            $notificationEmail = $call->tenant->agentConfig?->notification_email;

            if ($message->wasRecentlyCreated) {
                $this->activityLogger->log(
                    tenant: $call->tenant,
                    eventType: 'message_received',
                    title: 'Message vocal recu',
                    description: 'Un message vocal a ete enregistre et attache a l appel.',
                    call: $call,
                    callMessage: $message,
                    metadata: [
                        'caller_number' => $message->caller_number,
                        'recording_duration' => $message->recording_duration,
                    ],
                );
            }

            $this->activityLogger->log(
                tenant: $call->tenant,
                eventType: 'voicemail_insights_requested',
                title: 'Analyse automatique demandee',
                description: 'Le message vocal est mis en file pour transcription et resume.',
                call: $call,
                callMessage: $message,
            );

            if ($notificationEmail) {
                Mail::to($notificationEmail)->send(
                    new VoicemailReceivedMail($call->fresh('tenant'), $message),
                );

                $this->activityLogger->log(
                    tenant: $call->tenant,
                    eventType: 'notification_email_sent',
                    title: 'Notification email envoyee',
                    description: 'Le message vocal a ete distribue vers '.$notificationEmail.'.',
                    call: $call,
                    callMessage: $message,
                    metadata: [
                        'notification_email' => $notificationEmail,
                    ],
                );
            }

            ProcessVoicemailInsights::dispatch($call->id);

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
            if ($this->isConversationRelayCallback($request)) {
                return $this->handleConversationRelayCallback($request, $call);
            }

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
                $fallbackMessage = $this->contextualTransferFailureFallbackMessage($call);
                $metadata['transfer_failure_status'] = $dialStatus;
                $metadata['transfer_failed_at'] = now()->toIso8601String();
                $metadata['transfer_failure_fallback_message'] = $fallbackMessage;
                $metadata['fallback_target'] = 'voicemail';
                $status = 'voicemail_prompted';
                $fallbackResponse = $this->buildTransferFailureFallbackResponse($fallbackMessage);

                if (filled(data_get($call->metadata, 'conversation_transfer'))) {
                    $metadata['conversation_transfer'] = $this->filterNullValues([
                        ...((array) data_get($call->metadata, 'conversation_transfer', [])),
                        'meta' => array_merge(
                            (array) data_get($call->metadata, 'conversation_transfer.meta', []),
                            [
                                'effective_fallback_spoken_message' => $fallbackMessage,
                            ],
                        ) ?: null,
                    ]);
                }

                if (! data_get($call->metadata, 'activity_flags.transfer_failed_logged')) {
                    $this->activityLogger->log(
                        tenant: $call->tenant,
                        eventType: 'transfer_failed',
                        title: 'Transfert echoue',
                        description: 'Le transfert humain a echoue et l appel a ete bascule vers la messagerie.',
                        call: $call,
                        metadata: [
                            'dial_call_status' => $dialStatus,
                            'fallback_target' => 'voicemail',
                        ],
                    );

                    $metadata['activity_flags'] = array_merge(
                        (array) data_get($call->metadata, 'activity_flags', []),
                        ['transfer_failed_logged' => true],
                    );
                }

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

    private function buildConversationRelayTwiml(Call $call, $tenant, AgentConfig $agentConfig): string
    {
        return $this->buildTwiml([
            '<Connect action="'.e(route('webhooks.twilio.voice.status', absolute: true)).'" method="POST">'.
                '<ConversationRelay '.
                    'url="'.e((string) config('services.twilio.conversation_relay_url')).'" '.
                    'welcomeGreeting="'.e($agentConfig->welcome_message ?: "Bonjour, vous êtes bien chez {$tenant->name}. Comment puis-je vous aider ?").'" '.
                    'language="fr-FR" '.
                    'ttsProvider="Google" '.
                    'transcriptionProvider="Deepgram" '.
                    'speechModel="nova-3-general" '.
                    'interruptible="speech" '.
                    'dtmfDetection="true">'.
                    '<Parameter name="callSid" value="'.e((string) $call->external_sid).'"/>'.
                    '<Parameter name="tenantId" value="'.e((string) $tenant->id).'"/>'.
                    '</ConversationRelay>'.
            '</Connect>',
        ]);
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

    private function conversationRelayConfigured(): bool
    {
        $url = config('services.twilio.conversation_relay_url');

        return is_string($url) && str_starts_with($url, 'wss://');
    }

    private function isConversationRelayCallback(Request $request): bool
    {
        return filled($request->string('SessionStatus')->toString())
            || filled($request->string('SessionId')->toString())
            || filled($request->string('HandoffData')->toString());
    }

    private function handleConversationRelayCallback(Request $request, Call $call): Response
    {
        $handoffData = $this->decodeHandoffData($request->string('HandoffData')->toString());
        $sessionStatus = $request->string('SessionStatus')->toString();
        $sessionEvent = $this->filterNullValues([
            'received_at' => now()->toIso8601String(),
            'session_id' => $request->string('SessionId')->toString() ?: null,
            'session_status' => $sessionStatus ?: null,
            'session_duration_seconds' => $this->requestInteger($request, 'SessionDuration'),
            'handoff_data' => $handoffData ?: null,
        ]);

        $metadata = $this->mergeMetadata($call, [
            'conversation_relay' => [
                'last_event' => $sessionEvent,
                'events' => array_values(array_slice([
                    ...collect(data_get($call->metadata, 'conversation_relay.events', []))->filter(fn ($event) => is_array($event))->all(),
                    $sessionEvent,
                ], -10)),
            ],
        ]);

        $call->update([
            'channel' => Call::CHANNEL_CONVERSATION_AI,
            'conversation_status' => $sessionStatus ?: ($call->conversation_status ?? 'active'),
            'metadata' => $metadata,
        ]);

        $action = data_get($handoffData, 'action');

        if ($action === 'transfer') {
            return $this->handleConversationRelayTransfer($request, $call, $handoffData, $metadata);
        }

        if (in_array($action, ['voicemail', 'fallback_voicemail'], true) || $sessionStatus === 'failed') {
            return $this->handleConversationRelayFallback($request, $call, $handoffData, $metadata, $sessionStatus);
        }

        if ($action === 'hangup') {
            $call->update([
                'status' => 'completed',
                'conversation_status' => 'completed',
                'ended_at' => $call->ended_at ?? now(),
            ]);

            return $this->xmlResponse($this->buildTwiml([
                '<Hangup/>',
            ]));
        }

        if ($sessionStatus === 'ended' || $sessionStatus === 'completed') {
            $call->update([
                'status' => $call->resolution_type === 'transferred' ? 'transferred' : $this->completedStatusFor($call),
                'conversation_status' => 'completed',
                'ended_at' => $call->ended_at ?? now(),
            ]);
        }

        Log::info('twilio.webhook.conversation_relay_processed', $this->webhookContext($request, $call, [
            'session_status' => $sessionStatus ?: null,
            'handoff_action' => is_string($action) ? $action : null,
        ]));

        return response('', 204);
    }

    private function handleConversationRelayTransfer(Request $request, Call $call, array $handoffData, array $metadata): Response
    {
        $targetPhoneNumber = data_get($handoffData, 'target_phone_number') ?: $call->tenant->agentConfig?->transfer_phone_number;
        $reason = data_get($handoffData, 'reason');
        $summary = data_get($handoffData, 'summary');
        $fallbackSpokenMessage = data_get($handoffData, 'fallback_spoken_message');
        $alreadyLogged = filled(data_get($call->metadata, 'conversation_transfer.requested_at'));

        if (! filled($targetPhoneNumber)) {
            return $this->handleConversationRelayFallback($request, $call, [
                'action' => 'fallback_voicemail',
                'reason' => $reason ?: 'transfer_phone_missing',
                'summary' => $summary,
            ], $metadata, 'failed');
        }

        $call->update([
            'status' => 'transferring',
            'conversation_status' => 'transfer_requested',
            'escalation_reason' => is_string($reason) ? $reason : $call->escalation_reason,
            'conversation_summary' => is_string($summary) ? $summary : $call->conversation_summary,
            'summary' => is_string($summary) ? $summary : $call->summary,
            'metadata' => array_merge($metadata, [
                'conversation_transfer' => $this->filterNullValues([
                    ...((array) data_get($call->metadata, 'conversation_transfer', [])),
                    'requested_at' => now()->toIso8601String(),
                    'target_phone_number' => $targetPhoneNumber,
                    'reason' => is_string($reason) ? $reason : null,
                    'summary' => is_string($summary) ? $summary : null,
                    'meta' => array_merge(
                        (array) data_get($call->metadata, 'conversation_transfer.meta', []),
                        $this->filterNullValues([
                            'fallback_spoken_message' => is_string($fallbackSpokenMessage) ? $fallbackSpokenMessage : null,
                        ]),
                    ) ?: null,
                ]),
            ]),
        ]);

        if (! $alreadyLogged) {
            $this->activityLogger->log(
                tenant: $call->tenant,
                eventType: 'transfer_attempted',
                title: 'Transfert tente',
                description: 'Le runtime conversationnel a demande une reprise humaine.',
                call: $call->fresh(),
                metadata: [
                    'transfer_phone_number' => $targetPhoneNumber,
                    'reason' => is_string($reason) ? $reason : null,
                ],
            );
        }

        Log::info('twilio.webhook.conversation_relay_transfer', $this->webhookContext($request, $call, [
            'transfer_phone_number' => $targetPhoneNumber,
        ]));

        return $this->xmlResponse($this->buildTwiml([
            '<Say language="fr-BE">Nous vous transférons immédiatement.</Say>',
            '<Dial action="'.e(route('webhooks.twilio.voice.status', absolute: true)).'" method="POST">'.e($targetPhoneNumber).'</Dial>',
        ]));
    }

    private function handleConversationRelayFallback(Request $request, Call $call, array $handoffData, array $metadata, ?string $sessionStatus = null): Response
    {
        $reason = data_get($handoffData, 'reason');
        $summary = data_get($handoffData, 'summary');
        $spokenMessage = data_get($handoffData, 'spoken_message');
        $alreadyLogged = filled(data_get($call->metadata, 'conversation_fallback.requested_at'));

        $call->update([
            'status' => 'voicemail_prompted',
            'conversation_status' => 'fallback_requested',
            'escalation_reason' => is_string($reason) ? $reason : $call->escalation_reason,
            'conversation_summary' => is_string($summary) ? $summary : $call->conversation_summary,
            'summary' => is_string($summary) ? $summary : ($call->summary ?: 'Fallback vers messagerie apres incident conversationnel.'),
            'metadata' => array_merge($metadata, [
                'fallback_target' => 'voicemail',
                'conversation_fallback' => $this->filterNullValues([
                    'requested_at' => now()->toIso8601String(),
                    'reason' => is_string($reason) ? $reason : null,
                    'session_status' => $sessionStatus,
                ]),
            ]),
        ]);

        if (! $alreadyLogged) {
            $this->activityLogger->log(
                tenant: $call->tenant,
                eventType: 'conversation_fallback_requested',
                title: 'Fallback conversationnel',
                description: 'Le mode conversationnel a ete interrompu et repasse vers la messagerie.',
                call: $call->fresh(),
                metadata: [
                    'reason' => is_string($reason) ? $reason : null,
                    'session_status' => $sessionStatus,
                ],
            );
        }

        Log::warning('twilio.webhook.conversation_relay_fallback', $this->webhookContext($request, $call, [
            'session_status' => $sessionStatus,
            'reason' => is_string($reason) ? $reason : null,
        ]));

        return $this->conversationUnavailableFallbackResponse(
            is_string($spokenMessage) && filled($spokenMessage)
                ? $spokenMessage
                : null,
        );
    }

    private function decodeHandoffData(string $handoffData): array
    {
        if ($handoffData === '') {
            return [];
        }

        $decoded = json_decode($handoffData, true);

        return is_array($decoded) ? $decoded : [];
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

    private function buildTransferFailureFallbackResponse(string $message): Response
    {
        return $this->xmlResponse($this->buildTwiml([
            $this->say($message),
            $this->record(route('webhooks.twilio.voice.recording', absolute: true)),
        ]));
    }

    private function transferFailureFallbackMessage(Call $call): string
    {
        $message = data_get($call->metadata, 'conversation_transfer.meta.fallback_spoken_message');

        if (is_string($message) && filled($message)) {
            return $message;
        }

        return 'La ligne humaine est indisponible pour le moment. Merci de laisser un message après le bip.';
    }

    private function contextualTransferFailureFallbackMessage(Call $call): string
    {
        $message = $this->transferFailureFallbackMessage($call);

        if (filled(data_get($call->metadata, 'conversation_transfer.meta.fallback_spoken_message'))) {
            return $message;
        }

        $context = $this->transferFailureFallbackContext($call);

        if ($context === null) {
            return $message;
        }

        return 'La ligne humaine est indisponible pour le moment. Merci de laisser votre nom, votre numero et votre demande concernant '.$context.' apres le bip.';
    }

    private function transferFailureFallbackContext(Call $call): ?string
    {
        $candidates = array_filter([
            data_get($call->metadata, 'conversation_transfer.summary'),
            $call->conversation_summary,
            $call->summary,
        ], fn ($value) => is_string($value) && filled($value));

        foreach ($candidates as $candidate) {
            $context = $this->sanitizeTransferFallbackContext($candidate);

            if ($context !== null) {
                return $context;
            }
        }

        return null;
    }

    private function sanitizeTransferFallbackContext(string $candidate): ?string
    {
        $context = trim($candidate);
        $context = preg_replace('/^(le|la)\s+(caller|client|appelant)\s+(a\s+)?(demande|souhaite|veut)\s+/i', '', $context) ?? $context;
        $context = preg_replace('/^(une|un)\s+demande\s+de\s+/i', '', $context) ?? $context;
        $context = preg_replace('/^concernant\s+/i', '', $context) ?? $context;
        $context = trim($context, " \t\n\r\0\x0B.:;!?");

        if ($context === '') {
            return null;
        }

        $normalized = Str::of($context)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        if ($normalized === '') {
            return null;
        }

        foreach ([
            'parler a un humain',
            'parler a quelqu un',
            'reprise humaine',
            'transfert humain',
            'transfert',
            'escalade',
        ] as $genericPhrase) {
            if (str_contains($normalized, $genericPhrase)) {
                return null;
            }
        }

        return Str::limit($context, 96, '...');
    }

    private function conversationUnavailableFallbackResponse(?string $message = null): Response
    {
        return $this->xmlResponse($this->buildTwiml([
            $this->say($message ?: 'Notre standard conversationnel est temporairement indisponible. Merci de laisser un message après le bip.'),
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

    private function normalizedRecordingMediaUrl(string $recordingUrl): ?string
    {
        if ($recordingUrl === '') {
            return null;
        }

        if (preg_match('/\.(mp3|wav)$/i', $recordingUrl)) {
            return $recordingUrl;
        }

        return $recordingUrl.'.mp3';
    }
}

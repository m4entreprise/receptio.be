<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallTurn;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RealtimeCallController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function bootstrap(string $callSid): JsonResponse
    {
        $call = $this->resolveCall($callSid);
        $agentConfig = $call->tenant->agentConfig;

        return response()->json([
            'call' => [
                'id' => $call->id,
                'external_sid' => $call->external_sid,
                'from_number' => $call->from_number,
                'to_number' => $call->to_number,
                'status' => $call->status,
                'channel' => $call->channel,
                'conversation_status' => $call->conversation_status,
                'resolution_type' => $call->resolution_type,
                'transcript' => $call->transcript,
                'summary' => $call->conversation_summary ?? $call->summary,
            ],
            'tenant' => [
                'id' => $call->tenant->id,
                'name' => $call->tenant->name,
                'locale' => $call->tenant->locale,
                'timezone' => $call->tenant->timezone,
            ],
            'agent' => [
                'agent_name' => $agentConfig?->agent_name,
                'welcome_message' => $agentConfig?->welcome_message,
                'after_hours_message' => $agentConfig?->after_hours_message,
                'faq_content' => $agentConfig?->faq_content,
                'transfer_phone_number' => $agentConfig?->transfer_phone_number,
                'conversation_enabled' => (bool) $agentConfig?->conversation_enabled,
                'conversation_prompt' => $agentConfig?->conversation_prompt,
                'max_clarification_turns' => $agentConfig?->max_clarification_turns ?? 2,
            ],
            'turns' => $call->turns->map(fn (CallTurn $turn) => [
                'id' => $turn->id,
                'speaker' => $turn->speaker,
                'text' => $turn->text,
                'confidence' => $turn->confidence,
                'sequence' => $turn->sequence,
                'meta' => $turn->meta,
                'created_at' => $turn->created_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function storeTurn(Request $request, string $callSid): JsonResponse
    {
        $validated = $request->validate([
            'speaker' => ['required', 'in:caller,assistant,system'],
            'text' => ['required', 'string', 'max:10000'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'sequence' => ['required', 'integer', 'min:0'],
            'meta' => ['nullable', 'array'],
        ]);

        $call = $this->resolveCall($callSid);

        $turn = CallTurn::updateOrCreate(
            [
                'call_id' => $call->id,
                'sequence' => $validated['sequence'],
            ],
            [
                'speaker' => $validated['speaker'],
                'text' => $validated['text'],
                'confidence' => $validated['confidence'] ?? null,
                'meta' => $validated['meta'] ?? null,
            ],
        );

        $call->update([
            'channel' => Call::CHANNEL_CONVERSATION_AI,
            'conversation_status' => $call->conversation_status ?? 'active',
            'transcript' => $this->buildTranscript($call->fresh('turns')),
        ]);

        return response()->json([
            'turn' => [
                'id' => $turn->id,
                'speaker' => $turn->speaker,
                'text' => $turn->text,
                'confidence' => $turn->confidence,
                'sequence' => $turn->sequence,
                'meta' => $turn->meta,
            ],
        ], $turn->wasRecentlyCreated ? 201 : 200);
    }

    public function storeResolution(Request $request, string $callSid): JsonResponse
    {
        $validated = $request->validate([
            'resolution_type' => ['required', 'in:answered,transferred,voicemail,failed,after_hours'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'conversation_status' => ['nullable', 'string', 'max:255'],
            'escalation_reason' => ['nullable', 'string', 'max:1000'],
            'meta' => ['nullable', 'array'],
        ]);

        $call = $this->resolveCall($callSid);
        $metadata = array_merge($call->metadata ?? [], [
            'conversation_resolution' => array_filter([
                'resolved_at' => now()->toIso8601String(),
                'resolution_type' => $validated['resolution_type'],
                'summary' => $validated['summary'] ?? null,
                'escalation_reason' => $validated['escalation_reason'] ?? null,
                'meta' => $validated['meta'] ?? null,
            ], fn ($value) => $value !== null),
        ]);

        $status = $this->statusForResolution($validated['resolution_type']);

        $call->update([
            'channel' => Call::CHANNEL_CONVERSATION_AI,
            'conversation_status' => $validated['conversation_status'] ?? 'resolved',
            'resolution_type' => $validated['resolution_type'],
            'conversation_summary' => $validated['summary'] ?? $call->conversation_summary,
            'summary' => $validated['summary'] ?? $call->summary,
            'escalation_reason' => $validated['escalation_reason'] ?? $call->escalation_reason,
            'status' => $status,
            'ended_at' => $this->isTerminalConversationStatus($status) ? ($call->ended_at ?? now()) : $call->ended_at,
            'transcript' => $this->buildTranscript($call->fresh('turns')),
            'metadata' => $metadata,
        ]);

        $this->activityLogger->log(
            tenant: $call->tenant,
            eventType: 'conversation_resolved',
            title: 'Conversation resolue',
            description: 'Le runtime conversationnel a publie une resolution exploitable.',
            call: $call->fresh(),
            metadata: [
                'resolution_type' => $validated['resolution_type'],
                'conversation_status' => $validated['conversation_status'] ?? 'resolved',
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function storeTransfer(Request $request, string $callSid): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'target_phone_number' => ['nullable', 'string', 'max:30'],
            'meta' => ['nullable', 'array'],
        ]);

        $call = $this->resolveCall($callSid);
        $targetPhoneNumber = $validated['target_phone_number'] ?: $call->tenant->agentConfig?->transfer_phone_number;

        if (! filled($targetPhoneNumber)) {
            return response()->json([
                'message' => 'No transfer phone number is configured for this call.',
            ], 422);
        }

        $call->update([
            'channel' => Call::CHANNEL_CONVERSATION_AI,
            'status' => 'transferring',
            'conversation_status' => 'transfer_requested',
            'conversation_summary' => $validated['summary'] ?? $call->conversation_summary,
            'summary' => $validated['summary'] ?? $call->summary,
            'escalation_reason' => $validated['reason'] ?? $call->escalation_reason,
            'metadata' => array_merge($call->metadata ?? [], array_filter([
                'conversation_transfer' => [
                    'requested_at' => now()->toIso8601String(),
                    'target_phone_number' => $targetPhoneNumber,
                    'reason' => $validated['reason'] ?? null,
                    'meta' => $validated['meta'] ?? null,
                ],
            ])),
        ]);

        $this->activityLogger->log(
            tenant: $call->tenant,
            eventType: 'transfer_attempted',
            title: 'Transfert demande par le runtime',
            description: 'Le mode conversationnel demande une reprise humaine.',
            call: $call->fresh(),
            metadata: [
                'transfer_phone_number' => $targetPhoneNumber,
                'reason' => $validated['reason'] ?? null,
            ],
        );

        return response()->json([
            'transfer_phone_number' => $targetPhoneNumber,
        ]);
    }

    public function storeFallback(Request $request, string $callSid): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'target' => ['nullable', 'in:voicemail,hangup'],
            'meta' => ['nullable', 'array'],
        ]);

        $call = $this->resolveCall($callSid);
        $fallbackTarget = $validated['target'] ?? 'voicemail';
        $status = $fallbackTarget === 'voicemail' ? 'voicemail_prompted' : 'failed';

        $call->update([
            'channel' => Call::CHANNEL_CONVERSATION_AI,
            'status' => $status,
            'conversation_status' => 'fallback_requested',
            'conversation_summary' => $validated['summary'] ?? $call->conversation_summary,
            'summary' => $validated['summary'] ?? $call->summary,
            'escalation_reason' => $validated['reason'] ?? $call->escalation_reason,
            'metadata' => array_merge($call->metadata ?? [], [
                'fallback_target' => $fallbackTarget,
                'conversation_fallback' => array_filter([
                    'requested_at' => now()->toIso8601String(),
                    'reason' => $validated['reason'] ?? null,
                    'target' => $fallbackTarget,
                    'meta' => $validated['meta'] ?? null,
                ], fn ($value) => $value !== null),
            ]),
        ]);

        $this->activityLogger->log(
            tenant: $call->tenant,
            eventType: 'conversation_fallback_requested',
            title: 'Fallback conversationnel demande',
            description: 'Le runtime conversationnel repasse vers un flux de secours.',
            call: $call->fresh(),
            metadata: [
                'target' => $fallbackTarget,
                'reason' => $validated['reason'] ?? null,
            ],
        );

        return response()->json([
            'target' => $fallbackTarget,
        ]);
    }

    private function resolveCall(string $callSid): Call
    {
        $call = Call::with(['tenant.agentConfig', 'turns'])->where('external_sid', $callSid)->first();

        if (! $call) {
            throw new NotFoundHttpException;
        }

        return $call;
    }

    private function buildTranscript(Call $call): ?string
    {
        $lines = $call->turns
            ->map(fn (CallTurn $turn) => Str::headline($turn->speaker).': '.$turn->text)
            ->filter()
            ->values();

        return $lines->isEmpty() ? null : $lines->implode("\n");
    }

    private function statusForResolution(string $resolutionType): string
    {
        return match ($resolutionType) {
            'answered' => 'completed',
            'transferred' => 'transferred',
            'voicemail' => 'voicemail_prompted',
            'failed' => 'failed',
            'after_hours' => 'after_hours',
        };
    }

    private function isTerminalConversationStatus(string $status): bool
    {
        return in_array($status, ['completed', 'transferred', 'failed', 'after_hours'], true);
    }
}

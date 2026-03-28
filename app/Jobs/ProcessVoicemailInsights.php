<?php

namespace App\Jobs;

use App\Models\Call;
use App\Models\CallMessage;
use App\Support\ActivityLogger;
use App\Support\VoicemailInsights\GeneratesVoicemailInsights;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessVoicemailInsights implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $callId) {}

    public function handle(GeneratesVoicemailInsights $generator, ActivityLogger $activityLogger): void
    {
        $call = Call::with(['message', 'tenant'])->find($this->callId);

        if (! $call || ! $call->message) {
            return;
        }

        /** @var CallMessage $message */
        $message = $call->message;
        $insights = $generator->generate($call, $message);

        $message->forceFill([
            'message_text' => $insights->transcript ?? $message->message_text,
            'transcription_status' => $insights->transcriptionStatus,
            'transcript_provider' => $insights->provider,
            'transcription_error' => $insights->error,
            'transcription_processed_at' => now(),
            'ai_summary' => $insights->summary,
            'ai_intent' => $insights->intent,
            'urgency_level' => $insights->urgency,
            'automation_processed_at' => now(),
        ])->save();

        $call->forceFill([
            'transcript' => $insights->transcript,
            'summary' => $insights->summary ?? $call->summary,
        ])->save();

        $activityLogger->log(
            tenant: $call->tenant,
            eventType: 'voicemail_insights_generated',
            title: 'Analyse du message terminee',
            description: $this->activityDescription($message, $insights->summary),
            call: $call,
            callMessage: $message,
            metadata: [
                'transcription_status' => $insights->transcriptionStatus,
                'provider' => $insights->provider,
                'intent' => $insights->intent,
                'urgency' => $insights->urgency,
            ],
        );
    }

    private function activityDescription(CallMessage $message, ?string $summary): string
    {
        $parts = [];

        if ($message->transcription_status === CallMessage::TRANSCRIPTION_STATUS_COMPLETED) {
            $parts[] = 'La transcription automatique est disponible.';
        } elseif ($message->transcription_status === CallMessage::TRANSCRIPTION_STATUS_FAILED) {
            $parts[] = 'La transcription automatique a echoue.';
        } else {
            $parts[] = 'La transcription automatique est indisponible.';
        }

        if ($summary) {
            $parts[] = 'Resume: '.$summary;
        }

        return implode(' ', $parts);
    }
}

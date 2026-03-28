<?php

namespace App\Support\VoicemailInsights;

use App\Models\Call;
use App\Models\CallMessage;
use Illuminate\Support\Str;

class HeuristicVoicemailInsightGenerator implements GeneratesVoicemailInsights
{
    public function generate(Call $call, CallMessage $message): VoicemailInsights
    {
        $transcript = $this->transcriptFromContext($call, $message);

        return new VoicemailInsights(
            transcript: $transcript,
            transcriptionStatus: $transcript ? CallMessage::TRANSCRIPTION_STATUS_COMPLETED : CallMessage::TRANSCRIPTION_STATUS_UNAVAILABLE,
            provider: 'heuristic',
            summary: $this->buildSummary($call, $message, $transcript),
            intent: $this->detectIntent($transcript),
            urgency: $this->detectUrgency($transcript),
            error: null,
        );
    }

    public function summarize(Call $call, CallMessage $message, ?string $transcript): VoicemailInsights
    {
        return new VoicemailInsights(
            transcript: $transcript,
            transcriptionStatus: $transcript ? CallMessage::TRANSCRIPTION_STATUS_COMPLETED : CallMessage::TRANSCRIPTION_STATUS_UNAVAILABLE,
            provider: 'heuristic',
            summary: $this->buildSummary($call, $message, $transcript),
            intent: $this->detectIntent($transcript),
            urgency: $this->detectUrgency($transcript),
            error: null,
        );
    }

    private function transcriptFromContext(Call $call, CallMessage $message): ?string
    {
        $existingMessageText = trim((string) $message->message_text);

        if ($existingMessageText !== '' && ! in_array($existingMessageText, ['Message vocal reçu.', 'Message vocal recu.'], true)) {
            return $existingMessageText;
        }

        $summary = trim((string) $call->summary);

        if ($summary !== '') {
            return $summary;
        }

        return null;
    }

    private function buildSummary(Call $call, CallMessage $message, ?string $transcript): string
    {
        $source = trim((string) ($transcript ?: $message->message_text ?: $call->summary));
        $caller = $message->caller_name ?: $message->caller_number ?: $call->from_number ?: 'un contact';

        if ($source === '' || in_array($source, ['Message vocal reçu.', 'Message vocal recu.'], true)) {
            return "Message vocal laisse par {$caller}. Rappel humain recommande.";
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $source) ?: [];
        $summary = trim((string) ($sentences[0] ?? $source));

        if (Str::length($summary) > 180) {
            $summary = Str::limit($summary, 180);
        }

        return $summary;
    }

    private function detectIntent(?string $transcript): string
    {
        $text = Str::lower((string) $transcript);

        return match (true) {
            Str::contains($text, ['devis', 'prix', 'tarif', 'offre']) => 'commercial',
            Str::contains($text, ['rdv', 'rendez-vous', 'agenda', 'disponibil']) => 'planning',
            Str::contains($text, ['facture', 'paiement', 'remboursement']) => 'facturation',
            Str::contains($text, ['support', 'bug', 'probleme', 'panne', 'incident']) => 'support',
            Str::contains($text, ['rappel', 'rappelez', 'recontacter']) => 'rappel',
            default => 'contact_general',
        };
    }

    private function detectUrgency(?string $transcript): string
    {
        $text = Str::lower((string) $transcript);

        return match (true) {
            Str::contains($text, ['urgent', 'urgence', 'immediat', 'immediat', 'asap']) => 'high',
            Str::contains($text, ['rapidement', 'aujourd', 'des que possible']) => 'medium',
            default => 'low',
        };
    }
}

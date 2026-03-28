<?php

namespace App\Support\VoicemailInsights;

use App\Models\Call;
use App\Models\CallMessage;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class OpenAiVoicemailInsightGenerator implements GeneratesVoicemailInsights
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly HeuristicVoicemailInsightGenerator $heuristic,
        private readonly ?string $apiKey,
        private readonly ?string $transcriptionModel,
        private readonly ?string $textModel,
    ) {}

    public function generate(Call $call, CallMessage $message): VoicemailInsights
    {
        if (! $this->apiKey || ! $this->transcriptionModel) {
            return $this->heuristic->generate($call, $message);
        }

        $temporaryFile = $this->downloadRecording($message->recording_url);

        if (! $temporaryFile) {
            $heuristic = $this->heuristic->summarize($call, $message, null);

            return new VoicemailInsights(
                transcript: null,
                transcriptionStatus: CallMessage::TRANSCRIPTION_STATUS_UNAVAILABLE,
                provider: $this->providerLabel(),
                summary: $heuristic->summary,
                intent: $heuristic->intent,
                urgency: $heuristic->urgency,
                error: 'recording_unavailable',
            );
        }

        try {
            $response = $this->http
                ->withToken($this->apiKey)
                ->acceptJson()
                ->attach('file', fopen($temporaryFile, 'r'), basename($temporaryFile))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $this->transcriptionModel,
                    'language' => 'fr',
                ])
                ->throw()
                ->json();

            $transcript = trim((string) Arr::get($response, 'text'));
            $analysis = $this->analyzeTranscript($call, $message, $transcript !== '' ? $transcript : null);

            return new VoicemailInsights(
                transcript: $transcript !== '' ? $transcript : null,
                transcriptionStatus: $transcript !== '' ? CallMessage::TRANSCRIPTION_STATUS_COMPLETED : CallMessage::TRANSCRIPTION_STATUS_UNAVAILABLE,
                provider: $this->providerLabel(),
                summary: $analysis->summary,
                intent: $analysis->intent,
                urgency: $analysis->urgency,
                error: $transcript !== '' ? null : 'empty_transcript',
            );
        } catch (\Throwable $exception) {
            Log::warning('voicemail.transcription.openai_failed', [
                'call_id' => $call->id,
                'call_message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            $heuristic = $this->heuristic->generate($call, $message);

            return new VoicemailInsights(
                transcript: $heuristic->transcript,
                transcriptionStatus: CallMessage::TRANSCRIPTION_STATUS_FAILED,
                provider: $this->providerLabel(),
                summary: $heuristic->summary,
                intent: $heuristic->intent,
                urgency: $heuristic->urgency,
                error: $exception->getMessage(),
            );
        } finally {
            @unlink($temporaryFile);
        }
    }

    private function analyzeTranscript(Call $call, CallMessage $message, ?string $transcript): VoicemailInsights
    {
        $heuristic = $this->heuristic->summarize($call, $message, $transcript);

        if (! $transcript || ! $this->textModel) {
            return $heuristic;
        }

        try {
            $response = $this->http
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->textModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu analyses des messages vocaux entrants pour un standard telephonique. Reponds uniquement en JSON valide.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->analysisPrompt($call, $message, $transcript),
                        ],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'voicemail_insights',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'summary' => ['type' => 'string'],
                                    'intent' => [
                                        'type' => 'string',
                                        'enum' => ['commercial', 'planning', 'facturation', 'support', 'rappel', 'contact_general'],
                                    ],
                                    'urgency' => [
                                        'type' => 'string',
                                        'enum' => ['low', 'medium', 'high'],
                                    ],
                                ],
                                'required' => ['summary', 'intent', 'urgency'],
                            ],
                        ],
                    ],
                ])
                ->throw()
                ->json();

            $content = data_get($response, 'choices.0.message.content');
            $decoded = is_string($content) ? json_decode($content, true) : null;

            if (! is_array($decoded)) {
                return $heuristic;
            }

            return new VoicemailInsights(
                transcript: $transcript,
                transcriptionStatus: CallMessage::TRANSCRIPTION_STATUS_COMPLETED,
                provider: $this->providerLabel(),
                summary: $this->stringOrFallback($decoded, 'summary', $heuristic->summary),
                intent: $this->enumOrFallback($decoded, 'intent', ['commercial', 'planning', 'facturation', 'support', 'rappel', 'contact_general'], $heuristic->intent ?? 'contact_general'),
                urgency: $this->enumOrFallback($decoded, 'urgency', ['low', 'medium', 'high'], $heuristic->urgency ?? 'low'),
                error: null,
            );
        } catch (\Throwable $exception) {
            Log::warning('voicemail.analysis.openai_failed', [
                'call_id' => $call->id,
                'call_message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            return $heuristic;
        }
    }

    private function analysisPrompt(Call $call, CallMessage $message, string $transcript): string
    {
        $caller = $message->caller_name ?: $message->caller_number ?: $call->from_number ?: 'Inconnu';

        return implode("\n", [
            'Contexte: message vocal laisse a un standard telephonique.',
            'Appelant: '.$caller,
            'Numero: '.($message->caller_number ?: $call->from_number ?: 'Inconnu'),
            'Transcript:',
            $transcript,
            '',
            'Produis:',
            '- un summary tres court en francais, utile pour une equipe de reception',
            '- une intent parmi: commercial, planning, facturation, support, rappel, contact_general',
            '- une urgency parmi: low, medium, high',
        ]);
    }

    private function stringOrFallback(array $payload, string $key, ?string $fallback): ?string
    {
        $value = trim((string) data_get($payload, $key));

        return $value !== '' ? Str::limit($value, 240) : $fallback;
    }

    private function enumOrFallback(array $payload, string $key, array $allowed, string $fallback): string
    {
        $value = (string) data_get($payload, $key);

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function downloadRecording(?string $recordingUrl): ?string
    {
        if (! $recordingUrl) {
            return null;
        }

        $response = $this->http->timeout(30)->get($recordingUrl);

        if (! $response->successful()) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'receptio-audio-');

        if (! $path) {
            throw new RuntimeException('Unable to allocate temporary file for voicemail transcription.');
        }

        file_put_contents($path, $response->body());

        return $path;
    }

    private function providerLabel(): string
    {
        if ($this->textModel && $this->textModel !== $this->transcriptionModel) {
            return 'openai:'.$this->transcriptionModel.'+'.$this->textModel;
        }

        return 'openai:'.$this->transcriptionModel;
    }
}

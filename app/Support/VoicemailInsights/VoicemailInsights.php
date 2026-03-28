<?php

namespace App\Support\VoicemailInsights;

class VoicemailInsights
{
    public function __construct(
        public readonly ?string $transcript,
        public readonly string $transcriptionStatus,
        public readonly string $provider,
        public readonly ?string $summary,
        public readonly ?string $intent,
        public readonly ?string $urgency,
        public readonly ?string $error = null,
    ) {}
}

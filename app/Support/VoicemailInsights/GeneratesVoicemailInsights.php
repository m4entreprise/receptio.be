<?php

namespace App\Support\VoicemailInsights;

use App\Models\Call;
use App\Models\CallMessage;

interface GeneratesVoicemailInsights
{
    public function generate(Call $call, CallMessage $message): VoicemailInsights;
}

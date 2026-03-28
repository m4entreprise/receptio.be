<?php

namespace App\Http\Controllers;

use App\Support\TenantResolver;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BackofficeRecordingController extends Controller
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
        private readonly HttpFactory $http,
    ) {}

    public function show(Request $request, int $message): Response
    {
        $tenant = $this->tenantResolver->forUser($request->user());

        abort_unless($tenant, 404);

        $callMessage = $tenant->callMessages()
            ->whereKey($message)
            ->firstOrFail();

        abort_unless(filled($callMessage->recording_url), 404);

        $recordingUrl = $this->normalizedRecordingMediaUrl($callMessage->recording_url);
        $recordingResponse = $this->recordingRequest($recordingUrl)->get($recordingUrl);

        abort_unless($recordingResponse->successful(), 502);

        $contentType = $recordingResponse->header('Content-Type') ?: $this->fallbackContentType($recordingUrl);

        return response($recordingResponse->body(), 200, array_filter([
            'Content-Type' => $contentType,
            'Content-Length' => $recordingResponse->header('Content-Length'),
            'Cache-Control' => 'private, max-age=300',
        ]));
    }

    private function recordingRequest(string $recordingUrl): PendingRequest
    {
        $request = $this->http->timeout(30);
        $host = parse_url($recordingUrl, PHP_URL_HOST);

        if (is_string($host) && str_contains($host, 'twilio.com')) {
            $accountSid = config('services.twilio.account_sid');
            $authToken = config('services.twilio.auth_token');

            if ($accountSid && $authToken) {
                $request = $request->withBasicAuth($accountSid, $authToken);
            }
        }

        return $request;
    }

    private function normalizedRecordingMediaUrl(string $recordingUrl): string
    {
        if (preg_match('/\.(mp3|wav)$/i', $recordingUrl)) {
            return $recordingUrl;
        }

        return $recordingUrl.'.mp3';
    }

    private function fallbackContentType(string $recordingUrl): string
    {
        return preg_match('/\.wav$/i', $recordingUrl) ? 'audio/wav' : 'audio/mpeg';
    }
}

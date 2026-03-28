<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $authToken = config('services.twilio.auth_token');

        if (! $authToken) {
            if (app()->environment(['local', 'testing'])) {
                return $next($request);
            }

            Log::error('twilio.signature.auth_token_missing', $this->signatureContext($request));

            abort(500, 'TWILIO_AUTH_TOKEN is not configured.');
        }

        $providedSignature = $request->header('X-Twilio-Signature');

        if (! $providedSignature) {
            Log::warning('twilio.signature.missing', $this->signatureContext($request));

            abort(403);
        }

        $expectedSignature = $this->buildSignature(
            $request->fullUrl(),
            $request->request->all(),
            $authToken,
        );

        if (! hash_equals($expectedSignature, $providedSignature)) {
            Log::warning('twilio.signature.invalid', $this->signatureContext($request, [
                'expected_signature_prefix' => substr($expectedSignature, 0, 8),
                'provided_signature_prefix' => substr($providedSignature, 0, 8),
            ]));

            abort(403);
        }

        return $next($request);
    }

    private function buildSignature(string $url, array $params, string $authToken): string
    {
        ksort($params);

        $data = $url;

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $value = implode('', $value);
            }

            $data .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $data, $authToken, true));
    }

    private function signatureContext(Request $request, array $context = []): array
    {
        return array_filter(array_merge([
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'call_sid' => $request->string('CallSid')->toString() ?: null,
            'parent_call_sid' => $request->string('ParentCallSid')->toString() ?: null,
            'from_number' => $request->string('From')->toString() ?: null,
            'to_number' => $request->string('To')->toString() ?: null,
        ], $context), fn ($value) => $value !== null && $value !== '');
    }
}

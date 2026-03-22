<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

            abort(500, 'TWILIO_AUTH_TOKEN is not configured.');
        }

        $providedSignature = $request->header('X-Twilio-Signature');

        if (! $providedSignature) {
            abort(403);
        }

        $expectedSignature = $this->buildSignature(
            $request->fullUrl(),
            $request->request->all(),
            $authToken,
        );

        if (! hash_equals($expectedSignature, $providedSignature)) {
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
}

import crypto from 'node:crypto';

function resolveValidationUrl(request, publicBaseUrl = null) {
    if (publicBaseUrl) {
        return new URL(request.url, publicBaseUrl).toString();
    }

    const host = request.headers.host;

    if (!host) {
        throw new Error('Unable to resolve request host for Twilio signature validation.');
    }

    const protocol =
        request.headers['x-forwarded-proto']?.split(',')[0]?.trim() ||
        (request.socket.encrypted ? 'wss' : 'ws');

    return `${protocol}://${host}${request.url}`;
}

export function computeTwilioSignature({ authToken, url }) {
    return crypto.createHmac('sha1', authToken).update(url).digest('base64');
}

export function isValidTwilioSignature({ request, authToken, publicBaseUrl = null }) {
    if (!authToken) {
        return true;
    }

    const providedSignature = request.headers['x-twilio-signature'];

    if (typeof providedSignature !== 'string' || providedSignature.length === 0) {
        return false;
    }

    const expectedSignature = computeTwilioSignature({
        authToken,
        url: resolveValidationUrl(request, publicBaseUrl),
    });

    const providedBuffer = Buffer.from(providedSignature);
    const expectedBuffer = Buffer.from(expectedSignature);

    return providedBuffer.length === expectedBuffer.length && crypto.timingSafeEqual(providedBuffer, expectedBuffer);
}

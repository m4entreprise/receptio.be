function joinUrl(baseUrl, path) {
    return new URL(path.replace(/^\//, ''), `${baseUrl.replace(/\/+$/, '')}/`).toString();
}

async function parseJson(response) {
    const contentType = response.headers.get('content-type') ?? '';

    if (!contentType.includes('application/json')) {
        return null;
    }

    return response.json();
}

export class LaravelRealtimeClient {
    constructor({ baseUrl, token, fetchImpl = fetch, timeoutMs = 10_000 }) {
        if (!baseUrl) {
            throw new Error('Missing Laravel realtime base URL.');
        }

        if (!token) {
            throw new Error('Missing realtime internal token.');
        }

        this.baseUrl = baseUrl;
        this.token = token;
        this.fetchImpl = fetchImpl;
        this.timeoutMs = timeoutMs;
    }

    async bootstrap(callSid) {
        return this.request('GET', `/internal/realtime/calls/${encodeURIComponent(callSid)}/bootstrap`);
    }

    async storeTurn(callSid, payload) {
        return this.request('POST', `/internal/realtime/calls/${encodeURIComponent(callSid)}/turns`, payload);
    }

    async storeResolution(callSid, payload) {
        return this.request('POST', `/internal/realtime/calls/${encodeURIComponent(callSid)}/resolution`, payload);
    }

    async storeTransfer(callSid, payload) {
        return this.request('POST', `/internal/realtime/calls/${encodeURIComponent(callSid)}/transfer`, payload);
    }

    async storeFallback(callSid, payload) {
        return this.request('POST', `/internal/realtime/calls/${encodeURIComponent(callSid)}/fallback`, payload);
    }

    async request(method, path, body = null) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeoutMs);

        try {
            const response = await this.fetchImpl(joinUrl(this.baseUrl, path), {
                method,
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${this.token}`,
                    ...(body ? { 'Content-Type': 'application/json' } : {}),
                },
                body: body ? JSON.stringify(body) : undefined,
                signal: controller.signal,
            });

            const json = await parseJson(response);

            if (!response.ok) {
                throw new Error(
                    `Laravel realtime request failed (${response.status}) for ${method} ${path}: ${JSON.stringify(json)}`,
                );
            }

            return json;
        } finally {
            clearTimeout(timeout);
        }
    }
}

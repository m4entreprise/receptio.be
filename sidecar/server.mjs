import { ConversationSession } from './conversation-session.mjs';
import { createDecisionEngine } from './decision-engine.mjs';
import { LaravelRealtimeClient } from './laravel-realtime-client.mjs';
import { isValidTwilioSignature } from './twilio-signature.mjs';
import { createConversationRelayServer } from './websocket-server.mjs';

function env(name, fallback = null) {
    return process.env[name] ?? fallback;
}

function envNumber(name, fallback) {
    const value = Number(env(name, String(fallback)));

    return Number.isFinite(value) && value > 0 ? value : fallback;
}

function parseMessage(rawMessage) {
    try {
        return JSON.parse(rawMessage);
    } catch {
        return null;
    }
}

const port = Number(env('CONVERSATION_RELAY_PORT', '8080'));
const host = env('CONVERSATION_RELAY_HOST', '0.0.0.0');
const path = env('CONVERSATION_RELAY_PATH', '/conversationrelay');
const publicBaseUrl = env('CONVERSATION_RELAY_PUBLIC_URL');
const logPrefix = '[conversation-sidecar]';

const realtimeClient = new LaravelRealtimeClient({
    baseUrl: env('REALTIME_API_BASE_URL', env('APP_URL', 'http://localhost')),
    token: env('REALTIME_INTERNAL_TOKEN'),
});

const decisionEngine = createDecisionEngine({
    provider: env('CONVERSATION_DECISION_PROVIDER', 'heuristic'),
    openAiApiKey: env('OPENAI_API_KEY'),
    openAiModel: env('CONVERSATION_OPENAI_MODEL', env('OPENAI_TEXT_MODEL', 'gpt-5.4-mini')),
    openAiTimeoutMs: envNumber('CONVERSATION_OPENAI_TIMEOUT_MS', 12_000),
    logger: console,
});

const server = createConversationRelayServer({
    path,
    verifyRequest: (request) =>
        isValidTwilioSignature({
            request,
            authToken: env('TWILIO_AUTH_TOKEN'),
            publicBaseUrl,
        }),
    onConnection: {
        open(connection) {
            connection.session = new ConversationSession({
                realtimeClient,
                transport: {
                    send(payload) {
                        connection.sendJson(payload);
                    },
                },
                decisionEngine,
                logger: console,
            });

            console.info(`${logPrefix} connected`);
        },
        async message(connection, rawMessage) {
            const payload = parseMessage(rawMessage);

            if (!connection.session || !payload) {
                return;
            }

            await connection.session.handleMessage(payload);
        },
        close(connection) {
            console.info(`${logPrefix} disconnected`);
            connection.session = null;
        },
    },
});

server.listen(port, host, () => {
    console.info(`${logPrefix} listening on ws://${host}:${port}${path}`);
});

import assert from 'node:assert/strict';
import test from 'node:test';

import { ConversationSession } from './conversation-session.mjs';

function createRealtimeClient() {
    const calls = [];

    return {
        calls,
        bootstrap: async () => ({
            call: {
                external_sid: 'CA_TEST',
            },
            agent: {
                faq_content: 'Horaires: du lundi au vendredi de 9h a 18h.',
                max_clarification_turns: 2,
                transfer_phone_number: '+32470000000',
            },
            turns: [],
        }),
        storeTurn: async (callSid, payload) => {
            calls.push(['turn', callSid, payload]);
        },
        storeResolution: async (callSid, payload) => {
            calls.push(['resolution', callSid, payload]);
        },
        storeTransfer: async (callSid, payload) => {
            calls.push(['transfer', callSid, payload]);
        },
        storeFallback: async (callSid, payload) => {
            calls.push(['fallback', callSid, payload]);
        },
    };
}

test('session bootstraps and answers an FAQ prompt', async () => {
    const realtimeClient = createRealtimeClient();
    const outboundMessages = [];
    const session = new ConversationSession({
        realtimeClient,
        transport: {
            send(payload) {
                outboundMessages.push(payload);
            },
        },
        logger: {
            info() {},
            warn() {},
            error() {},
        },
    });

    await session.handleMessage({
        type: 'setup',
        callSid: 'CA_TEST',
        sessionId: 'VX_TEST',
    });

    await session.handleMessage({
        type: 'prompt',
        voicePrompt: 'Quels sont vos horaires ?',
        last: true,
        lang: 'fr-FR',
    });

    assert.deepEqual(
        realtimeClient.calls.map((entry) => entry[0]),
        ['turn', 'turn'],
    );
    assert.equal(outboundMessages[0].type, 'text');
    assert.match(outboundMessages[0].token, /9h a 18h/i);
});

test('session escalates to transfer on explicit human request', async () => {
    const realtimeClient = createRealtimeClient();
    const outboundMessages = [];
    const session = new ConversationSession({
        realtimeClient,
        transport: {
            send(payload) {
                outboundMessages.push(payload);
            },
        },
        logger: {
            info() {},
            warn() {},
            error() {},
        },
    });

    await session.handleMessage({
        type: 'setup',
        callSid: 'CA_TEST',
        sessionId: 'VX_TEST',
    });

    await session.handleMessage({
        type: 'prompt',
        voicePrompt: "Je veux parler a quelqu'un.",
        last: true,
        lang: 'fr-FR',
    });

    assert.equal(realtimeClient.calls.at(-1)[0], 'transfer');
    assert.equal(outboundMessages.at(-1).type, 'end');

    const handoffData = JSON.parse(outboundMessages.at(-1).handoffData);

    assert.equal(handoffData.action, 'transfer');
    assert.equal(handoffData.reason, 'caller_requested_human');
});

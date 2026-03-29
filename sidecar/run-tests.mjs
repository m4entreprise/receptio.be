import assert from 'node:assert/strict';

import { createDecisionEngine } from './decision-engine.mjs';
import { decideConversationAction, deriveConversationState, findFaqCandidates } from './conversation-policy.mjs';
import { ConversationSession } from './conversation-session.mjs';

function bootstrap(overrides = {}) {
    return {
        agent: {
            faq_content: 'Horaires: du lundi au vendredi de 9h a 18h.\nAdresse: Rue de la Loi 1, Bruxelles.',
            max_clarification_turns: 2,
            transfer_phone_number: '+32470000000',
        },
        turns: [],
        ...overrides,
    };
}

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

const tests = [
    {
        name: 'policy answers FAQ questions',
        run() {
            const action = decideConversationAction({
                bootstrap: bootstrap(),
                state: deriveConversationState(bootstrap()),
                userText: 'Quels sont vos horaires ?',
            });

            assert.equal(action.type, 'answer');
            assert.match(action.reply, /9h a 18h/i);
        },
    },
    {
        name: 'policy matches question answer FAQ blocks',
        run() {
            const promptBootstrap = bootstrap({
                agent: {
                    faq_content:
                        'Question: Quels sont vos horaires ?\nReponse: Nous sommes ouverts du lundi au vendredi de 9h a 18h.\n\nQuestion: Ou etes-vous situes ?\nReponse: Rue de la Loi 1, Bruxelles.',
                    max_clarification_turns: 2,
                    transfer_phone_number: '+32470000000',
                },
            });

            const action = decideConversationAction({
                bootstrap: promptBootstrap,
                state: deriveConversationState(promptBootstrap),
                userText: 'Ou etes-vous situes exactement ?',
            });

            assert.equal(action.type, 'answer');
            assert.match(action.reply, /Rue de la Loi 1/i);
        },
    },
    {
        name: 'policy exposes relevant FAQ candidates',
        run() {
            const candidates = findFaqCandidates(
                'Je cherche votre adresse a Bruxelles.',
                'Horaires: du lundi au vendredi de 9h a 18h.\nAdresse: Rue de la Loi 1, Bruxelles.',
                2,
            );

            assert.equal(candidates.length, 1);
            assert.equal(candidates[0].entry.topic, 'Adresse');
        },
    },
    {
        name: 'policy applies structured escalation rules from conversation_prompt',
        run() {
            const promptBootstrap = bootstrap({
                agent: {
                    faq_content: '',
                    max_clarification_turns: 2,
                    transfer_phone_number: '+32470000000',
                    conversation_prompt:
                        'Escalade: devis + urgent, contrat + resiliation\nMessage escalade: Je vous transfere vers un humain pour ce type de demande.\nMessagerie: Merci de laisser votre nom et votre numero apres le bip.',
                },
            });

            const action = decideConversationAction({
                bootstrap: promptBootstrap,
                state: deriveConversationState(promptBootstrap),
                userText: 'J ai besoin d un devis urgent pour demain.',
            });

            assert.equal(action.type, 'transfer');
            assert.equal(action.reason, 'tenant_policy_escalation');
            assert.match(action.reply, /transfere vers un humain/i);
        },
    },
    {
        name: 'policy applies structured clarification rules from conversation_prompt',
        run() {
            const promptBootstrap = bootstrap({
                agent: {
                    faq_content: '',
                    max_clarification_turns: 2,
                    transfer_phone_number: '+32470000000',
                    conversation_prompt:
                        'Clarifier: dossier + reference\nMessage clarification: Pouvez-vous me donner votre numero de reference ?',
                },
            });

            const action = decideConversationAction({
                bootstrap: promptBootstrap,
                state: deriveConversationState(promptBootstrap),
                userText: 'Je vous appelle pour un dossier avec une reference.',
            });

            assert.equal(action.type, 'clarify');
            assert.equal(action.reason, 'tenant_policy_clarification');
            assert.match(action.reply, /numero de reference/i);
        },
    },
    {
        name: 'policy clarifies appointment requests before routing them to a human',
        run() {
            const action = decideConversationAction({
                bootstrap: bootstrap(),
                state: deriveConversationState(bootstrap()),
                userText: 'Je voudrais prendre un rendez-vous pour un soin du visage.',
            });

            assert.equal(action.type, 'clarify');
            assert.equal(action.intent, 'appointment_request');
            assert.equal(action.reason, 'appointment_request_needs_details');
            assert.match(action.reply, /prestation souhaitez-vous/i);
        },
    },
    {
        name: 'policy transfers appointment requests after the appointment clarification turn',
        run() {
            const appointmentBootstrap = bootstrap({
                turns: [
                    {
                        speaker: 'assistant',
                        text: 'Je peux transmettre une demande de rendez vous a l institut.',
                        meta: {
                            decision: 'clarify',
                            intent: 'appointment_request',
                        },
                    },
                ],
            });

            const action = decideConversationAction({
                bootstrap: appointmentBootstrap,
                state: deriveConversationState(appointmentBootstrap),
                userText: 'Ce serait pour mardi apres-midi pour un massage relaxant.',
            });

            assert.equal(action.type, 'transfer');
            assert.equal(action.intent, 'appointment_request');
            assert.equal(action.reason, 'appointment_request_requires_human');
            assert.match(action.fallbackMessage, /prestation souhaitee/i);
        },
    },
    {
        name: 'policy falls back to voicemail for appointment requests when no transfer is available',
        run() {
            const appointmentBootstrap = bootstrap({
                agent: {
                    faq_content: '',
                    max_clarification_turns: 2,
                    transfer_phone_number: null,
                    conversation_prompt:
                        'Messagerie: Merci de laisser votre nom, votre numero, la prestation souhaitee et votre disponibilite apres le bip.',
                },
                turns: [
                    {
                        speaker: 'assistant',
                        text: 'Je peux transmettre une demande de rendez vous a l institut.',
                        meta: {
                            decision: 'clarify',
                            intent: 'appointment_request',
                        },
                    },
                ],
            });

            const action = decideConversationAction({
                bootstrap: appointmentBootstrap,
                state: deriveConversationState(appointmentBootstrap),
                userText: 'Je voudrais annuler mon rendez-vous de vendredi.',
            });

            assert.equal(action.type, 'fallback');
            assert.equal(action.intent, 'appointment_request');
            assert.equal(action.reason, 'appointment_request_requires_human');
            assert.equal(action.target, 'voicemail');
            assert.match(action.reply, /votre disponibilite apres le bip/i);
        },
    },
    {
        name: 'policy escalates explicit human requests',
        run() {
            const action = decideConversationAction({
                bootstrap: bootstrap(),
                state: deriveConversationState(bootstrap()),
                userText: "Je veux parler a quelqu'un du service client.",
            });

            assert.equal(action.type, 'transfer');
            assert.equal(action.reason, 'caller_requested_human');
        },
    },
    {
        name: 'policy applies tenant escalation directives from conversation_prompt',
        run() {
            const promptBootstrap = bootstrap({
                agent: {
                    faq_content: 'Horaires: du lundi au vendredi de 9h a 18h.',
                    max_clarification_turns: 2,
                    transfer_phone_number: '+32470000000',
                    conversation_prompt: 'Escalade: devis, facturation\nMessagerie: Merci de laisser votre nom, votre numero et l objet de votre demande apres le bip.',
                },
            });

            const action = decideConversationAction({
                bootstrap: promptBootstrap,
                state: deriveConversationState(promptBootstrap),
                userText: 'Je voudrais un devis pour demain.',
            });

            assert.equal(action.type, 'transfer');
            assert.equal(action.reason, 'tenant_policy_escalation');
            assert.match(action.reply, /laisser votre nom/i);
        },
    },
    {
        name: 'openai decision engine can override the heuristic decision',
        async run() {
            let capturedBody = null;
            const engine = createDecisionEngine({
                provider: 'openai',
                openAiApiKey: 'test-key',
                openAiModel: 'gpt-5.4-mini',
                fetchImpl: async (_url, options) => ({
                    ok: true,
                    async json() {
                        capturedBody = JSON.parse(options.body);
                        return {
                            output_text: JSON.stringify({
                                type: 'clarify',
                                reply: 'Pouvez-vous preciser votre numero de dossier ?',
                                summary: 'Clarification demandee sur le dossier.',
                                reason: 'needs_identifier',
                            }),
                        };
                    },
                }),
                logger: {
                    warn() {},
                },
            });

            const action = await engine.decide({
                bootstrap: bootstrap(),
                state: deriveConversationState(bootstrap()),
                userText: 'J ai un souci.',
            });

            assert.equal(action.type, 'clarify');
            assert.match(action.reply, /numero de dossier/i);
            assert.equal(action.reason, 'needs_identifier');
            assert.equal(action.decisionSource, 'openai');
            assert.equal(action.decisionProvider, 'openai');
            assert.equal(action.decisionModel, 'gpt-5.4-mini');
            assert.equal(typeof action.decisionLatencyMs, 'number');
            assert.match(capturedBody.input, /FAQ candidates:/);
        },
    },
    {
        name: 'openai decision engine includes relevant FAQ candidates in the prompt',
        async run() {
            let capturedBody = null;
            const engine = createDecisionEngine({
                provider: 'openai',
                openAiApiKey: 'test-key',
                openAiModel: 'gpt-5.4-mini',
                fetchImpl: async (_url, options) => ({
                    ok: true,
                    async json() {
                        capturedBody = JSON.parse(options.body);
                        return {
                            output_text: JSON.stringify({
                                type: 'clarify',
                                reply: 'Pouvez-vous confirmer votre demande ?',
                                summary: 'Clarification demandee.',
                                reason: 'needs_clarification',
                            }),
                        };
                    },
                }),
                logger: {
                    warn() {},
                },
            });

            const action = await engine.decide({
                bootstrap: bootstrap({
                    agent: {
                        faq_content: 'Horaires: du lundi au vendredi de 9h a 18h.\nAdresse: Rue de la Loi 1, Bruxelles.',
                        max_clarification_turns: 2,
                        transfer_phone_number: '+32470000000',
                    },
                }),
                state: deriveConversationState(bootstrap()),
                userText: 'Je cherche votre adresse.',
            });

            assert.equal(action.type, 'clarify');
            assert.equal(action.decisionSource, 'openai');
            assert.match(capturedBody.input, /Rue de la Loi 1, Bruxelles/);
            assert.match(capturedBody.input, /FAQ candidates:/);
        },
    },
    {
        name: 'openai decision engine keeps appointment requests within V1-safe constraints',
        async run() {
            const engine = createDecisionEngine({
                provider: 'openai',
                openAiApiKey: 'test-key',
                openAiModel: 'gpt-5.4-mini',
                fetchImpl: async () => ({
                    ok: true,
                    async json() {
                        return {
                            output_text: JSON.stringify({
                                type: 'answer',
                                reply: 'Parfait, votre rendez-vous est confirme mardi a 15 heures.',
                                summary: 'Rendez-vous confirme.',
                                reason: 'appointment_confirmed',
                            }),
                        };
                    },
                }),
                logger: {
                    warn() {},
                },
            });

            const action = await engine.decide({
                bootstrap: bootstrap(),
                state: deriveConversationState(bootstrap()),
                userText: 'Je voudrais prendre un rendez-vous pour un soin du visage.',
            });

            assert.equal(action.type, 'clarify');
            assert.equal(action.intent, 'appointment_request');
            assert.equal(action.reason, 'appointment_request_needs_details');
            assert.doesNotMatch(action.reply, /confirme mardi/i);
        },
    },
    {
        name: 'openai decision engine falls back to heuristics when the api fails',
        async run() {
            const engine = createDecisionEngine({
                provider: 'openai',
                openAiApiKey: 'test-key',
                openAiModel: 'gpt-5.4-mini',
                fetchImpl: async () => {
                    throw new Error('network down');
                },
                logger: {
                    warn() {},
                },
            });

            const action = await engine.decide({
                bootstrap: bootstrap(),
                state: deriveConversationState(bootstrap()),
                userText: "Je veux parler a quelqu'un.",
            });

            assert.equal(action.type, 'transfer');
            assert.equal(action.reason, 'caller_requested_human');
            assert.equal(action.decisionSource, 'heuristic_fallback');
            assert.equal(action.decisionProvider, 'openai');
            assert.equal(action.decisionModel, 'gpt-5.4-mini');
            assert.equal(action.decisionErrorCode, 'openai_request_failed');
            assert.match(action.decisionError, /network down/i);
        },
    },
    {
        name: 'openai decision engine exposes timeout failures explicitly',
        async run() {
            const engine = createDecisionEngine({
                provider: 'openai',
                openAiApiKey: 'test-key',
                openAiModel: 'gpt-5.4-mini',
                openAiTimeoutMs: 5,
                fetchImpl: async (_url, options) =>
                    new Promise((_resolve, reject) => {
                        options.signal.addEventListener('abort', () => {
                            const abortError = new Error('aborted');
                            abortError.name = 'AbortError';
                            reject(abortError);
                        });
                    }),
                logger: {
                    warn() {},
                },
            });

            const action = await engine.decide({
                bootstrap: bootstrap(),
                state: deriveConversationState(bootstrap()),
                userText: "Je veux parler a quelqu'un.",
            });

            assert.equal(action.type, 'transfer');
            assert.equal(action.decisionSource, 'heuristic_fallback');
            assert.equal(action.decisionErrorCode, 'openai_timeout');
            assert.match(action.decisionError, /timed out after 5ms/i);
        },
    },
    {
        name: 'session persists turns and emits a text answer',
        async run() {
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
        },
    },
    {
        name: 'session emits a transfer handoff on human request',
        async run() {
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
        },
    },
    {
        name: 'session clarifies appointment requests before transferring them to a human',
        async run() {
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
                voicePrompt: 'Je voudrais prendre un rendez-vous pour un soin du visage.',
                last: true,
                lang: 'fr-FR',
            });

            assert.equal(realtimeClient.calls[1][0], 'turn');
            assert.equal(realtimeClient.calls[1][2].meta.decision, 'clarify');
            assert.equal(realtimeClient.calls[1][2].meta.intent, 'appointment_request');
            assert.equal(outboundMessages[0].type, 'text');
            assert.match(outboundMessages[0].token, /quelle prestation souhaitez-vous/i);

            await session.handleMessage({
                type: 'prompt',
                voicePrompt: 'Ce serait mardi apres-midi pour un massage relaxant.',
                last: true,
                lang: 'fr-FR',
            });

            assert.equal(realtimeClient.calls.at(-1)[0], 'transfer');
            const handoffData = JSON.parse(outboundMessages.at(-1).handoffData);

            assert.equal(handoffData.action, 'transfer');
            assert.equal(handoffData.reason, 'appointment_request_requires_human');
            assert.match(handoffData.fallback_spoken_message, /prestation souhaitee/i);
        },
    },
    {
        name: 'session persists the real decision source in assistant metadata',
        async run() {
            const realtimeClient = createRealtimeClient();
            const outboundMessages = [];
            const session = new ConversationSession({
                realtimeClient,
                transport: {
                    send(payload) {
                        outboundMessages.push(payload);
                    },
                },
                decisionEngine: {
                    async decide() {
                        return {
                            type: 'answer',
                            reply: 'Nous sommes situes Rue de la Loi 1, Bruxelles.',
                            summary: 'Reponse FAQ fournie.',
                            reason: 'faq_matched',
                            decisionSource: 'openai',
                        };
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
                voicePrompt: 'Ou etes-vous situes ?',
                last: true,
                lang: 'fr-FR',
            });

            assert.equal(realtimeClient.calls[1][2].meta.source, 'sidecar.openai');
            assert.equal(outboundMessages[0].type, 'text');
        },
    },
    {
        name: 'session persists decision runtime metadata on assistant turns',
        async run() {
            const realtimeClient = createRealtimeClient();
            const session = new ConversationSession({
                realtimeClient,
                transport: {
                    send() {},
                },
                decisionEngine: {
                    async decide() {
                        return {
                            type: 'answer',
                            reply: 'Voici la reponse.',
                            summary: 'Reponse fournie.',
                            reason: 'faq_matched',
                            decisionSource: 'heuristic_fallback',
                            decisionProvider: 'openai',
                            decisionModel: 'gpt-5.4-mini',
                            decisionLatencyMs: 187,
                            decisionError: 'OpenAI decision request timed out after 12000ms.',
                            decisionErrorCode: 'openai_timeout',
                        };
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
                voicePrompt: 'Pouvez-vous m aider ?',
                last: true,
                lang: 'fr-FR',
            });

            const assistantTurn = realtimeClient.calls[1][2];

            assert.equal(assistantTurn.meta.source, 'sidecar.heuristic_fallback');
            assert.equal(assistantTurn.meta.decision_provider, 'openai');
            assert.equal(assistantTurn.meta.decision_model, 'gpt-5.4-mini');
            assert.equal(assistantTurn.meta.decision_latency_ms, 187);
            assert.equal(assistantTurn.meta.decision_error_code, 'openai_timeout');
        },
    },
    {
        name: 'session includes fallback voicemail guidance in transfer handoff',
        async run() {
            const realtimeClient = createRealtimeClient();
            realtimeClient.bootstrap = async () => ({
                call: {
                    external_sid: 'CA_TEST',
                },
                agent: {
                    faq_content: '',
                    max_clarification_turns: 2,
                    transfer_phone_number: '+32470000000',
                    conversation_prompt:
                        'Escalade: devis\nMessagerie: Merci de laisser votre nom, votre numero et la reference de votre demande apres le bip.',
                },
                turns: [],
            });

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
                voicePrompt: 'Je voudrais un devis.',
                last: true,
                lang: 'fr-FR',
            });

            const transferCall = realtimeClient.calls.find((entry) => entry[0] === 'transfer');
            const handoffData = JSON.parse(outboundMessages.at(-1).handoffData);

            assert.equal(transferCall[2].meta.fallback_spoken_message.includes('votre nom'), true);
            assert.equal(handoffData.fallback_spoken_message.includes('reference de votre demande'), true);
        },
    },
];

let failures = 0;

for (const currentTest of tests) {
    try {
        await currentTest.run();
        console.log(`PASS ${currentTest.name}`);
    } catch (error) {
        failures += 1;
        console.error(`FAIL ${currentTest.name}`);
        console.error(error);
    }
}

if (failures > 0) {
    process.exitCode = 1;
}

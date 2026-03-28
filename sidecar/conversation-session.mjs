import { decideConversationAction, deriveConversationState } from './conversation-policy.mjs';

function nextSequence(turns) {
    const lastSequence = turns.reduce((max, turn) => Math.max(max, Number(turn?.sequence ?? 0)), 0);

    return lastSequence + 1;
}

function createTextMessage(text) {
    return {
        type: 'text',
        token: text,
        last: true,
        interruptible: true,
        preemptible: false,
    };
}

function createEndMessage(handoffData = null) {
    return {
        type: 'end',
        ...(handoffData ? { handoffData: JSON.stringify(handoffData) } : {}),
    };
}

function decisionRuntimeMeta(decision, source) {
    return {
        source,
        decision_provider: decision?.decisionProvider ?? null,
        decision_model: decision?.decisionModel ?? null,
        decision_latency_ms: Number.isFinite(decision?.decisionLatencyMs) ? decision.decisionLatencyMs : null,
        decision_error: typeof decision?.decisionError === 'string' ? decision.decisionError : null,
        decision_error_code: typeof decision?.decisionErrorCode === 'string' ? decision.decisionErrorCode : null,
    };
}

export class ConversationSession {
    constructor({ realtimeClient, transport, decisionEngine = null, logger = console }) {
        this.realtimeClient = realtimeClient;
        this.transport = transport;
        this.decisionEngine = decisionEngine;
        this.logger = logger;
        this.bootstrap = null;
        this.callSid = null;
        this.sessionId = null;
        this.partialPrompt = '';
        this.state = {
            clarificationCount: 0,
            maxClarificationTurns: 2,
            transferPhoneNumber: null,
        };
        this.sequence = 1;
    }

    async handleMessage(message) {
        switch (message?.type) {
            case 'setup':
                await this.handleSetup(message);
                return;
            case 'prompt':
                await this.handlePrompt(message);
                return;
            case 'dtmf':
                await this.handleDecision('', await this.decide({
                    userText: '',
                    digit: message.digit,
                }), {
                    kind: 'dtmf',
                    digit: message.digit,
                });
                return;
            case 'interrupt':
                this.logger.info?.('conversation.interrupt', {
                    callSid: this.callSid,
                    sessionId: this.sessionId,
                    utteranceUntilInterrupt: message.utteranceUntilInterrupt ?? null,
                });
                return;
            case 'error':
                this.logger.warn?.('conversation.twilio_error', {
                    callSid: this.callSid,
                    sessionId: this.sessionId,
                    description: message.description ?? null,
                });
                return;
            default:
                this.logger.warn?.('conversation.unknown_message', {
                    callSid: this.callSid,
                    sessionId: this.sessionId,
                    type: message?.type ?? null,
                });
        }
    }

    async handleSetup(message) {
        this.callSid = message.callSid;
        this.sessionId = message.sessionId ?? null;

        if (!this.callSid) {
            this.transport.send(createEndMessage({
                action: 'fallback_voicemail',
                reason: 'missing_call_sid',
            }));
            return;
        }

        try {
            this.bootstrap = await this.realtimeClient.bootstrap(this.callSid);
            this.state = deriveConversationState(this.bootstrap);
            this.sequence = nextSequence(this.bootstrap.turns ?? []);
        } catch (error) {
            this.logger.error?.('conversation.bootstrap_failed', {
                callSid: this.callSid,
                sessionId: this.sessionId,
                message: error instanceof Error ? error.message : String(error),
            });

            this.transport.send(createEndMessage({
                action: 'fallback_voicemail',
                reason: 'bootstrap_failed',
            }));
        }
    }

    async handlePrompt(message) {
        this.partialPrompt = `${this.partialPrompt}${message.voicePrompt ?? ''}`;

        if (!message.last) {
            return;
        }

        const prompt = this.partialPrompt.trim();
        this.partialPrompt = '';

        const decision = await this.decide({
            userText: prompt,
        });

        await this.handleDecision(prompt, decision, {
            kind: 'speech',
            lang: message.lang ?? null,
        });
    }

    async handleDecision(userText, decision, inputMeta) {
        if (!this.callSid || !this.bootstrap) {
            this.transport.send(createEndMessage({
                action: 'fallback_voicemail',
                reason: 'missing_bootstrap_context',
            }));
            return;
        }

        const decisionSource = `sidecar.${decision?.decisionSource ?? 'heuristic'}`;

        if (userText.trim() !== '') {
            await this.persistTurn('caller', userText, {
                source: 'twilio.conversationrelay',
                ...inputMeta,
            });
        }

        switch (decision.type) {
            case 'answer':
                await this.persistTurn('assistant', decision.reply, {
                    ...decisionRuntimeMeta(decision, decisionSource),
                    decision: 'answer',
                    matched_faq: decision.matchedFaq ?? null,
                });
                this.transport.send(createTextMessage(decision.reply));
                return;
            case 'clarify':
                await this.persistTurn('assistant', decision.reply, {
                    ...decisionRuntimeMeta(decision, decisionSource),
                    decision: 'clarify',
                    kind: 'clarification',
                });
                this.state.clarificationCount += 1;
                this.transport.send(createTextMessage(decision.reply));
                return;
            case 'hangup':
                await this.persistTurn('assistant', decision.reply, {
                    ...decisionRuntimeMeta(decision, decisionSource),
                    decision: 'hangup',
                });
                await this.realtimeClient.storeResolution(this.callSid, {
                    resolution_type: decision.resolutionType ?? 'answered',
                    summary: decision.summary,
                    conversation_status: 'completed',
                });
                this.transport.send(createTextMessage(decision.reply));
                this.transport.send(createEndMessage({
                    action: 'hangup',
                    summary: decision.summary,
                }));
                return;
            case 'transfer':
                await this.persistTurn('assistant', 'Je vous transfere vers un humain.', {
                    ...decisionRuntimeMeta(decision, decisionSource),
                    decision: 'transfer',
                });
                await this.realtimeClient.storeTransfer(this.callSid, {
                    reason: decision.reason,
                    summary: decision.summary,
                    target_phone_number: decision.targetPhoneNumber ?? undefined,
                    meta: {
                        ...decisionRuntimeMeta(decision, decisionSource),
                        fallback_spoken_message: decision.fallbackMessage ?? null,
                    },
                });
                this.transport.send(createEndMessage({
                    action: 'transfer',
                    reason: decision.reason,
                    summary: decision.summary,
                    target_phone_number: decision.targetPhoneNumber ?? null,
                    fallback_spoken_message: decision.fallbackMessage ?? null,
                }));
                return;
            case 'fallback':
                await this.persistTurn('assistant', decision.reply ?? 'Je vais prendre un message pour l equipe.', {
                    ...decisionRuntimeMeta(decision, decisionSource),
                    decision: 'fallback',
                });
                await this.realtimeClient.storeFallback(this.callSid, {
                    reason: decision.reason,
                    summary: decision.summary,
                    target: decision.target ?? 'voicemail',
                    meta: {
                        ...decisionRuntimeMeta(decision, decisionSource),
                    },
                });
                this.transport.send(createEndMessage({
                    action: decision.target === 'hangup' ? 'hangup' : 'fallback_voicemail',
                    reason: decision.reason,
                    summary: decision.summary,
                    spoken_message: decision.reply ?? null,
                }));
                return;
            default:
                this.transport.send(createEndMessage({
                    action: 'fallback_voicemail',
                    reason: 'unhandled_decision',
                }));
        }
    }

    async decide({ userText, digit = null }) {
        if (this.decisionEngine?.decide) {
            return this.decisionEngine.decide({
                bootstrap: this.bootstrap,
                state: this.state,
                userText,
                digit,
            });
        }

        return decideConversationAction({
            bootstrap: this.bootstrap,
            state: this.state,
            userText,
            digit,
        });
    }

    async persistTurn(speaker, text, meta = null) {
        if (!this.callSid) {
            return;
        }

        await this.realtimeClient.storeTurn(this.callSid, {
            speaker,
            text,
            sequence: this.sequence,
            meta,
        });

        if (this.bootstrap) {
            this.bootstrap.turns = [
                ...(this.bootstrap.turns ?? []),
                {
                    speaker,
                    text,
                    sequence: this.sequence,
                    meta,
                },
            ];
        }

        this.sequence += 1;
    }
}

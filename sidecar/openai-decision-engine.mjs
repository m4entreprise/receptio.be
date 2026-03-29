import { decideConversationAction, findFaqCandidates } from './conversation-policy.mjs';

function enforceProductConstraints(decision, fallbackDecision) {
    if (fallbackDecision?.intent !== 'appointment_request') {
        return decision;
    }

    if (!['clarify', 'transfer', 'fallback'].includes(decision.type)) {
        return fallbackDecision;
    }

    return {
        ...decision,
        intent: 'appointment_request',
        reason: decision.reason ?? fallbackDecision.reason,
        summary: decision.summary ?? fallbackDecision.summary,
        fallbackMessage: decision.fallbackMessage ?? fallbackDecision.fallbackMessage ?? null,
    };
}

function sanitizeDecision(rawDecision, fallbackDecision) {
    const allowedTypes = new Set(['answer', 'clarify', 'transfer', 'fallback', 'hangup']);
    const allowedTargets = new Set(['voicemail', 'hangup']);

    if (!rawDecision || typeof rawDecision !== 'object' || !allowedTypes.has(rawDecision.type)) {
        return fallbackDecision;
    }

    return enforceProductConstraints({
        ...fallbackDecision,
        ...rawDecision,
        type: rawDecision.type,
        intent: typeof rawDecision.intent === 'string' && rawDecision.intent.trim() !== '' ? rawDecision.intent : fallbackDecision.intent ?? null,
        reply: typeof rawDecision.reply === 'string' ? rawDecision.reply : fallbackDecision.reply ?? null,
        summary: typeof rawDecision.summary === 'string' && rawDecision.summary.trim() !== '' ? rawDecision.summary : fallbackDecision.summary,
        reason: typeof rawDecision.reason === 'string' && rawDecision.reason.trim() !== '' ? rawDecision.reason : fallbackDecision.reason,
        target: allowedTargets.has(rawDecision.target) ? rawDecision.target : fallbackDecision.target ?? null,
        targetPhoneNumber:
            typeof rawDecision.targetPhoneNumber === 'string' && rawDecision.targetPhoneNumber.trim() !== ''
                ? rawDecision.targetPhoneNumber
                : fallbackDecision.targetPhoneNumber ?? null,
        fallbackMessage:
            typeof rawDecision.fallbackMessage === 'string' && rawDecision.fallbackMessage.trim() !== ''
                ? rawDecision.fallbackMessage
                : fallbackDecision.fallbackMessage ?? null,
    }, fallbackDecision);
}

function compactTurns(turns) {
    return (turns ?? []).slice(-8).map((turn) => ({
        speaker: turn.speaker,
        text: turn.text,
        meta: turn.meta ?? null,
    }));
}

function buildPrompt({ bootstrap, state, userText, fallbackDecision }) {
    const faqCandidates = findFaqCandidates(userText, bootstrap?.agent?.faq_content, 3).map(({ entry, score }) => ({
        topic: entry.topic,
        question: entry.question,
        answer: entry.answer,
        score,
    }));

    return [
        'Tu es le moteur de decision d un receptionniste telephonique francophone.',
        'Tu dois choisir UNE action parmi: answer, clarify, transfer, fallback, hangup.',
        'Contraintes produit:',
        '- repondre directement seulement sur FAQ, informations simples et accueil',
        '- clarifier au maximum jusqu a la limite fournie',
        '- transferer si le caller demande un humain, si la politique du tenant le demande, ou si le sujet sort du perimetre',
        '- fallback vers messagerie si aucun transfert humain n est disponible',
        '- si la demande concerne un rendez-vous, une modification ou une annulation de rendez-vous: ne jamais confirmer une action agenda, demander au plus une clarification puis transferer ou basculer vers une prise de message structuree',
        '- repondre en francais, concis, operationnel',
        '',
        `Demande caller: ${userText}`,
        `Decision heuristique de secours: ${JSON.stringify(fallbackDecision)}`,
        `Clarifications utilisees: ${state?.clarificationCount ?? 0}`,
        `Clarifications max: ${state?.maxClarificationTurns ?? bootstrap?.agent?.max_clarification_turns ?? 2}`,
        `Transfert disponible: ${state?.transferPhoneNumber ? 'oui' : 'non'}`,
        `Numero de transfert: ${state?.transferPhoneNumber ?? 'n/a'}`,
        `Bienvenue: ${bootstrap?.agent?.welcome_message ?? ''}`,
        `FAQ: ${bootstrap?.agent?.faq_content ?? ''}`,
        `FAQ candidates: ${JSON.stringify(faqCandidates)}`,
        `Prompt tenant: ${bootstrap?.agent?.conversation_prompt ?? ''}`,
        `Historique recent: ${JSON.stringify(compactTurns(bootstrap?.turns))}`,
        '',
        'Retourne uniquement du JSON avec les champs suivants:',
        '- type',
        '- reply',
        '- summary',
        '- reason',
        '- target',
        '- targetPhoneNumber',
        '- fallbackMessage',
    ].join('\n');
}

function extractTextPayload(responseJson) {
    if (typeof responseJson?.output_text === 'string' && responseJson.output_text.trim() !== '') {
        return responseJson.output_text;
    }

    for (const item of responseJson?.output ?? []) {
        for (const contentItem of item?.content ?? []) {
            if (typeof contentItem?.text === 'string' && contentItem.text.trim() !== '') {
                return contentItem.text;
            }
        }
    }

    return null;
}

export class OpenAiDecisionEngine {
    constructor({
        apiKey,
        model,
        fetchImpl = fetch,
        baseUrl = 'https://api.openai.com/v1',
        logger = console,
        timeoutMs = 12_000,
    }) {
        this.apiKey = apiKey;
        this.model = model;
        this.fetchImpl = fetchImpl;
        this.baseUrl = baseUrl.replace(/\/+$/, '');
        this.logger = logger;
        this.timeoutMs = timeoutMs;
    }

    async decide({ bootstrap, state, userText, digit = null }) {
        const startedAt = Date.now();
        const heuristicDecision = decideConversationAction({
            bootstrap,
            state,
            userText,
            digit,
        });

        if (!this.apiKey || !this.model || digit !== null) {
            return {
                ...heuristicDecision,
                decisionSource: 'heuristic',
                decisionProvider: 'heuristic',
                decisionModel: null,
                decisionLatencyMs: null,
                decisionError: null,
                decisionErrorCode: null,
            };
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeoutMs);

        try {
            const response = await this.fetchImpl(`${this.baseUrl}/responses`, {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${this.apiKey}`,
                    'Content-Type': 'application/json',
                },
                signal: controller.signal,
                body: JSON.stringify({
                    model: this.model,
                    input: buildPrompt({ bootstrap, state, userText, fallbackDecision: heuristicDecision }),
                    text: {
                        format: {
                            type: 'json_object',
                        },
                    },
                }),
            });

            if (!response.ok) {
                throw new Error(`OpenAI decision request failed with status ${response.status}`);
            }

            const json = await response.json();
            const textPayload = extractTextPayload(json);

            if (!textPayload) {
                throw new Error('OpenAI decision response did not contain a JSON payload.');
            }

            const parsedDecision = JSON.parse(textPayload);

            return {
                ...sanitizeDecision(parsedDecision, heuristicDecision),
                decisionSource: 'openai',
                decisionProvider: 'openai',
                decisionModel: this.model,
                decisionLatencyMs: Date.now() - startedAt,
                decisionError: null,
                decisionErrorCode: null,
            };
        } catch (error) {
            const isTimeout = error instanceof Error && error.name === 'AbortError';
            const errorMessage = isTimeout
                ? `OpenAI decision request timed out after ${this.timeoutMs}ms.`
                : error instanceof Error
                    ? error.message
                    : String(error);
            const errorCode = isTimeout ? 'openai_timeout' : 'openai_request_failed';

            this.logger.warn?.('conversation.openai_decision_failed', {
                message: errorMessage,
                code: errorCode,
                timeoutMs: this.timeoutMs,
            });

            return {
                ...heuristicDecision,
                decisionSource: 'heuristic_fallback',
                decisionProvider: 'openai',
                decisionModel: this.model,
                decisionLatencyMs: Date.now() - startedAt,
                decisionError: errorMessage,
                decisionErrorCode: errorCode,
            };
        } finally {
            clearTimeout(timeout);
        }
    }
}

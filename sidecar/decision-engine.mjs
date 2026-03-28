import { decideConversationAction } from './conversation-policy.mjs';
import { OpenAiDecisionEngine } from './openai-decision-engine.mjs';

class HeuristicDecisionEngine {
    async decide(context) {
        return {
            ...decideConversationAction(context),
            decisionSource: 'heuristic',
            decisionProvider: 'heuristic',
            decisionModel: null,
            decisionLatencyMs: null,
            decisionError: null,
            decisionErrorCode: null,
        };
    }
}

export function createDecisionEngine({
    provider = 'heuristic',
    openAiApiKey = null,
    openAiModel = null,
    openAiTimeoutMs = 12_000,
    fetchImpl = fetch,
    logger = console,
}) {
    if (provider === 'openai' && openAiApiKey && openAiModel) {
        return new OpenAiDecisionEngine({
            apiKey: openAiApiKey,
            model: openAiModel,
            timeoutMs: openAiTimeoutMs,
            fetchImpl,
            logger,
        });
    }

    return new HeuristicDecisionEngine();
}

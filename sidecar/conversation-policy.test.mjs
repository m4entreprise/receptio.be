import assert from 'node:assert/strict';
import test from 'node:test';

import { decideConversationAction, deriveConversationState, findFaqCandidates } from './conversation-policy.mjs';

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

test('answers directly when an FAQ entry matches the caller request', () => {
    const action = decideConversationAction({
        bootstrap: bootstrap(),
        state: deriveConversationState(bootstrap()),
        userText: 'Quels sont vos horaires ?',
    });

    assert.equal(action.type, 'answer');
    assert.match(action.reply, /9h a 18h/i);
});

test('matches FAQ entries written as question answer blocks', () => {
    const qrBootstrap = bootstrap({
        agent: {
            faq_content:
                'Question: Quels sont vos horaires ?\nReponse: Nous sommes ouverts du lundi au vendredi de 9h a 18h.\n\nQuestion: Ou etes-vous situes ?\nReponse: Rue de la Loi 1, Bruxelles.',
            max_clarification_turns: 2,
            transfer_phone_number: '+32470000000',
        },
    });

    const action = decideConversationAction({
        bootstrap: qrBootstrap,
        state: deriveConversationState(qrBootstrap),
        userText: 'Ou etes-vous situes exactement ?',
    });

    assert.equal(action.type, 'answer');
    assert.match(action.reply, /Rue de la Loi 1/i);
});

test('returns the most relevant FAQ candidates for a caller request', () => {
    const candidates = findFaqCandidates(
        'Je cherche votre adresse a Bruxelles.',
        'Horaires: du lundi au vendredi de 9h a 18h.\nAdresse: Rue de la Loi 1, Bruxelles.',
        2,
    );

    assert.equal(candidates.length, 1);
    assert.equal(candidates[0].entry.topic, 'Adresse');
});

test('applies structured escalation rules from conversation_prompt', () => {
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
});

test('applies structured clarification rules from conversation_prompt', () => {
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
});

test('requests a transfer when the caller explicitly asks for a human', () => {
    const action = decideConversationAction({
        bootstrap: bootstrap(),
        state: deriveConversationState(bootstrap()),
        userText: "Je veux parler a quelqu'un du service client.",
    });

    assert.equal(action.type, 'transfer');
    assert.equal(action.reason, 'caller_requested_human');
});

test('falls back when clarification budget is exhausted and no transfer exists', () => {
    const exhaustedBootstrap = bootstrap({
        agent: {
            faq_content: '',
            max_clarification_turns: 1,
            transfer_phone_number: null,
        },
        turns: [
            {
                speaker: 'assistant',
                meta: {
                    decision: 'clarify',
                },
            },
        ],
    });

    const action = decideConversationAction({
        bootstrap: exhaustedBootstrap,
        state: deriveConversationState(exhaustedBootstrap),
        userText: 'Je ne sais pas trop, euh...',
    });

    assert.equal(action.type, 'fallback');
    assert.equal(action.reason, 'clarification_limit_reached');
    assert.equal(action.target, 'voicemail');
});

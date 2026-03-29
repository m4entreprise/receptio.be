const HUMAN_REQUEST_PATTERNS = [
    /\b(humain|personne|conseiller|quelqu['’]?un|operateur|op[eé]rateur|service client)\b/u,
    /\bparler a\b/u,
    /\btransf[ée]rer?\b/u,
];

const GOODBYE_PATTERNS = [
    /\bau revoir\b/u,
    /\bbonne journ[ée]e\b/u,
    /\bc['’]est tout\b/u,
    /\bcest tout\b/u,
    /\bmerci beaucoup\b/u,
    /\bmerci au revoir\b/u,
];

const VOICEMAIL_PATTERNS = [/\b(laisser un message|prendre un message|rappel(?:ez)?[- ]?moi|messagerie|bo[iî]te vocale)\b/u];

const APPOINTMENT_PATTERNS = [
    /\b(rendez vous|rdv)\b/u,
    /\b(prendre|planifier|reserver|booker)\b.*\b(rendez vous|rdv|creneau)\b/u,
    /\b(modifier|annuler|reporter|deplacer|decaler)\b.*\b(rendez vous|rdv)\b/u,
];

function unique(items) {
    return [...new Set(items.filter(Boolean))];
}

const STOPWORDS = new Set([
    'alors',
    'aussi',
    'avec',
    'avoir',
    'bonjour',
    'comment',
    'dans',
    'depuis',
    'des',
    'donc',
    'elle',
    'elles',
    'encore',
    'entre',
    'est',
    'etre',
    'faire',
    'fois',
    'leur',
    'leurs',
    'mais',
    'merci',
    'mes',
    'moi',
    'mon',
    'nous',
    'notre',
    'nos',
    'pour',
    'pouvez',
    'puis',
    'quel',
    'quelle',
    'quelles',
    'quels',
    'sont',
    'sur',
    'tes',
    'ton',
    'tous',
    'tout',
    'tres',
    'une',
    'vos',
    'votre',
    'vous',
]);

function normalizeText(value) {
    return (value ?? '')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function tokenize(value) {
    return normalizeText(value)
        .split(' ')
        .filter((token) => token.length >= 3 && !STOPWORDS.has(token));
}

function uniqueTokens(tokens) {
    return [...new Set(tokens)];
}

function splitFaqBlocks(faqContent) {
    const content = String(faqContent ?? '').trim();

    if (content === '') {
        return [];
    }

    const blocks = content
        .split(/\r?\n\s*\r?\n/)
        .map((block) => block.trim())
        .filter(Boolean);

    if (blocks.length > 1) {
        return blocks;
    }

    return content
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);
}

function stripBulletPrefix(value) {
    return String(value ?? '').replace(/^[-*•]\s*/, '').trim();
}

function isQuestionLabel(value) {
    return /^(q|question|demande)\s*:/i.test(value);
}

function isAnswerLabel(value) {
    return /^(r|reponse|réponse|answer)\s*:/i.test(value);
}

function stripLabeledValue(value) {
    return String(value ?? '').replace(/^[^:]+:\s*/i, '').trim();
}

function createFaqEntry(raw, topic, question, answer) {
    const resolvedTopic = stripBulletPrefix(topic || question || answer);
    const resolvedQuestion = stripBulletPrefix(question || topic || answer);
    const resolvedAnswer = String(answer || question || topic || '').trim();
    const questionTokens = uniqueTokens(tokenize(`${resolvedTopic} ${resolvedQuestion}`));
    const answerTokens = uniqueTokens(tokenize(resolvedAnswer));

    return {
        raw,
        topic: resolvedTopic,
        question: resolvedQuestion,
        answer: resolvedAnswer,
        questionTokens,
        answerTokens,
        tokens: uniqueTokens([...questionTokens, ...answerTokens]),
    };
}

function parseFaqBlock(block) {
    const lines = String(block ?? '')
        .split(/\r?\n/)
        .map((line) => stripBulletPrefix(line))
        .filter(Boolean);

    if (lines.length === 0) {
        return null;
    }

    const raw = lines.join('\n');
    const questionLines = [];
    const answerLines = [];
    let activeSection = null;

    for (const line of lines) {
        if (isQuestionLabel(line)) {
            activeSection = 'question';
            questionLines.push(stripLabeledValue(line));
            continue;
        }

        if (isAnswerLabel(line)) {
            activeSection = 'answer';
            answerLines.push(stripLabeledValue(line));
            continue;
        }

        if (activeSection === 'answer') {
            answerLines.push(line);
            continue;
        }

        if (activeSection === 'question') {
            questionLines.push(line);
            continue;
        }
    }

    if (questionLines.length > 0 || answerLines.length > 0) {
        return createFaqEntry(
            raw,
            questionLines[0] ?? answerLines[0] ?? '',
            questionLines.join(' '),
            answerLines.join(' '),
        );
    }

    if (lines.length === 1) {
        const [topic, ...rest] = lines[0].split(':');
        const answer = rest.length > 0 ? rest.join(':').trim() : lines[0];

        return createFaqEntry(raw, topic.trim(), topic.trim(), answer || lines[0]);
    }

    const [firstLine, ...rest] = lines;

    if (firstLine.includes(':')) {
        const [topic, ...answerParts] = firstLine.split(':');

        return createFaqEntry(raw, topic.trim(), topic.trim(), [answerParts.join(':').trim(), ...rest].filter(Boolean).join(' '));
    }

    return createFaqEntry(raw, firstLine, firstLine, rest.join(' '));
}

function parseFaqEntries(faqContent) {
    return splitFaqBlocks(faqContent)
        .map((block) => parseFaqBlock(block))
        .filter(Boolean)
        .filter((entry) => entry.answer !== '');
}

function scoreFaqEntry(userText, entry) {
    const normalizedUserText = normalizeText(userText);
    const userTokens = uniqueTokens(tokenize(userText));

    if (userTokens.length === 0) {
        return 0;
    }

    const questionOverlap = entry.questionTokens.reduce((total, token) => total + (userTokens.includes(token) ? 1 : 0), 0);
    const answerOverlap = entry.answerTokens.reduce((total, token) => total + (userTokens.includes(token) ? 1 : 0), 0);
    const topicExactMatch = entry.topic && normalizedUserText.includes(normalizeText(entry.topic)) ? 2 : 0;
    const questionExactMatch = entry.question && normalizedUserText.includes(normalizeText(entry.question)) ? 3 : 0;

    return questionOverlap * 3 + answerOverlap + topicExactMatch + questionExactMatch;
}

export function findFaqCandidates(userText, faqContent, limit = 3) {
    return parseFaqEntries(faqContent)
        .map((entry) => ({
            entry,
            score: scoreFaqEntry(userText, entry),
        }))
        .filter(({ score }) => score >= 2)
        .sort((left, right) => right.score - left.score)
        .slice(0, limit);
}

function matchFaq(userText, faqContent) {
    return findFaqCandidates(userText, faqContent, 1)[0]?.entry ?? null;
}

function hasPattern(text, patterns) {
    return patterns.some((pattern) => pattern.test(text));
}

function parseDirectiveValues(lines, prefixes) {
    const normalizedPrefixes = prefixes.map((prefix) => normalizeText(prefix));

    return lines
        .map((line) => {
            const [rawPrefix, ...rest] = line.split(':');

            if (rest.length === 0) {
                return null;
            }

            const prefix = normalizeText(rawPrefix);

            if (!normalizedPrefixes.includes(prefix)) {
                return null;
            }

            return rest.join(':').trim();
        })
        .filter(Boolean);
}

function parseRuleGroups(values) {
    return values.flatMap((value) =>
        value
            .split(',')
            .map((item) =>
                item
                    .split('+')
                    .map((term) => normalizeText(term))
                    .filter((term) => term.length >= 3),
            )
            .filter((group) => group.length > 0),
    );
}

function parsePromptDirectives(prompt) {
    const lines = String(prompt ?? '')
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    const escalationValues = parseDirectiveValues(lines, ['escalade', 'escalader', 'escalation']);
    const clarificationValues = parseDirectiveValues(lines, ['clarifier', 'clarification', 'qualification']);
    const voicemailValues = parseDirectiveValues(lines, ['messagerie', 'message', 'voicemail']);
    const escalationMessageValues = parseDirectiveValues(lines, ['message escalade', 'message escalation', 'message transfert']);
    const clarificationMessageValues = parseDirectiveValues(lines, ['message clarification', 'message clarifier', 'message qualification']);

    return {
        escalationRules: parseRuleGroups(escalationValues),
        escalationKeywords: unique(
            escalationValues.flatMap((value) =>
                value
                    .split(',')
                    .map((item) => normalizeText(item))
                    .filter((item) => item.length >= 3),
            ),
        ),
        clarificationRules: parseRuleGroups(clarificationValues),
        escalationReply: escalationMessageValues[0] ?? null,
        clarificationReply: clarificationMessageValues[0] ?? null,
        voicemailInstruction: voicemailValues[0] ?? null,
    };
}

function matchesEscalationKeyword(text, keywords) {
    return keywords.some((keyword) => text.includes(keyword));
}

function matchesRuleGroup(text, terms) {
    return terms.length > 0 && terms.every((term) => text.includes(term));
}

function matchesDirectiveRule(text, rules) {
    return rules.some((terms) => matchesRuleGroup(text, terms));
}

function summarize(text, fallback = 'Demande conversationnelle en attente de reprise humaine.') {
    const cleaned = String(text ?? '').trim();

    if (cleaned.length === 0) {
        return fallback;
    }

    return cleaned.length <= 180 ? cleaned : `${cleaned.slice(0, 177).trimEnd()}...`;
}

function appointmentSummary(text) {
    return summarize(text, 'Le caller souhaite organiser un rendez vous et doit etre repris par l equipe.');
}

function appointmentVoicemailInstruction(promptPolicy) {
    return (
        promptPolicy?.voicemailInstruction ??
        'Merci de laisser votre nom, votre numero, la prestation souhaitee ainsi que le jour ou le creneau ideal apres le bip.'
    );
}

function hasPendingAppointmentClarification(turns) {
    const latestAssistantTurn = [...(Array.isArray(turns) ? turns : [])]
        .reverse()
        .find((turn) => turn?.speaker === 'assistant');

    return (
        latestAssistantTurn?.meta?.decision === 'clarify' &&
        latestAssistantTurn?.meta?.intent === 'appointment_request'
    );
}

function appointmentFollowupDecision({ trimmed, transferPhoneNumber, promptPolicy }) {
    const summary = appointmentSummary(trimmed);
    const voicemailInstruction = appointmentVoicemailInstruction(promptPolicy);

    if (transferPhoneNumber) {
        return {
            type: 'transfer',
            intent: 'appointment_request',
            reason: 'appointment_request_requires_human',
            summary,
            targetPhoneNumber: transferPhoneNumber,
            fallbackMessage: voicemailInstruction,
        };
    }

    return {
        type: 'fallback',
        intent: 'appointment_request',
        reason: 'appointment_request_requires_human',
        summary,
        target: 'voicemail',
        reply: voicemailInstruction,
        fallbackMessage: voicemailInstruction,
    };
}

function clarificationCount(turns) {
    return turns.filter(
        (turn) =>
            turn?.speaker === 'assistant' &&
            (turn?.meta?.decision === 'clarify' || turn?.meta?.kind === 'clarification'),
    ).length;
}

export function deriveConversationState(bootstrap) {
    const turns = Array.isArray(bootstrap?.turns) ? bootstrap.turns : [];
    const promptPolicy = parsePromptDirectives(bootstrap?.agent?.conversation_prompt);

    return {
        clarificationCount: clarificationCount(turns),
        maxClarificationTurns: bootstrap?.agent?.max_clarification_turns ?? 2,
        transferPhoneNumber: bootstrap?.agent?.transfer_phone_number ?? null,
        promptPolicy,
    };
}

export function decideConversationAction({ bootstrap, state, userText, digit = null }) {
    const normalized = normalizeText(userText);
    const trimmed = String(userText ?? '').trim();
    const transferPhoneNumber = state?.transferPhoneNumber ?? bootstrap?.agent?.transfer_phone_number ?? null;
    const promptPolicy = state?.promptPolicy ?? parsePromptDirectives(bootstrap?.agent?.conversation_prompt);
    const appointmentIntentDetected = hasPattern(normalized, APPOINTMENT_PATTERNS);
    const pendingAppointmentClarification = hasPendingAppointmentClarification(bootstrap?.turns);
    const remainingClarifications = Math.max(
        0,
        (state?.maxClarificationTurns ?? bootstrap?.agent?.max_clarification_turns ?? 2) -
            (state?.clarificationCount ?? 0),
    );

    if (digit === '0') {
        return {
            type: transferPhoneNumber ? 'transfer' : 'fallback',
            reason: 'dtmf_human_request',
            summary: 'Le caller a demande une reprise humaine via le clavier.',
            target: transferPhoneNumber ? null : 'voicemail',
            targetPhoneNumber: transferPhoneNumber,
            fallbackMessage: promptPolicy?.voicemailInstruction ?? null,
        };
    }

    if (trimmed === '') {
        return {
            type: remainingClarifications > 0 ? 'clarify' : transferPhoneNumber ? 'transfer' : 'fallback',
            reply: 'Je n ai pas bien compris. Pouvez-vous reformuler votre demande en une phrase ?',
            reason: 'empty_prompt',
            summary: 'Le caller n a pas fourni de demande exploitable.',
            targetPhoneNumber: transferPhoneNumber,
            target: transferPhoneNumber ? null : 'voicemail',
        };
    }

    if (hasPattern(normalized, HUMAN_REQUEST_PATTERNS)) {
        return {
            type: transferPhoneNumber ? 'transfer' : 'fallback',
            reason: 'caller_requested_human',
            summary: summarize(trimmed, 'Le caller souhaite parler a un humain.'),
            target: transferPhoneNumber ? null : 'voicemail',
            targetPhoneNumber: transferPhoneNumber,
            fallbackMessage: promptPolicy.voicemailInstruction ?? null,
        };
    }

    if (matchesEscalationKeyword(normalized, promptPolicy.escalationKeywords)) {
        return {
            type: transferPhoneNumber ? 'transfer' : 'fallback',
            reason: 'tenant_policy_escalation',
            summary: summarize(trimmed, 'La politique du tenant impose une escalation.'),
            target: transferPhoneNumber ? null : 'voicemail',
            targetPhoneNumber: transferPhoneNumber,
            reply: promptPolicy.escalationReply ?? promptPolicy.voicemailInstruction ?? null,
            fallbackMessage: promptPolicy.voicemailInstruction ?? null,
        };
    }

    if (matchesDirectiveRule(normalized, promptPolicy.escalationRules ?? [])) {
        return {
            type: transferPhoneNumber ? 'transfer' : 'fallback',
            reason: 'tenant_policy_escalation',
            summary: summarize(trimmed, 'La politique du tenant impose une escalation.'),
            target: transferPhoneNumber ? null : 'voicemail',
            targetPhoneNumber: transferPhoneNumber,
            reply: promptPolicy.escalationReply ?? promptPolicy.voicemailInstruction ?? null,
            fallbackMessage: promptPolicy.voicemailInstruction ?? null,
        };
    }

    if (remainingClarifications > 0 && matchesDirectiveRule(normalized, promptPolicy.clarificationRules ?? [])) {
        return {
            type: 'clarify',
            reply:
                promptPolicy.clarificationReply ??
                'J ai besoin d une precision complementaire pour traiter votre demande. Pouvez-vous me donner plus de details ?',
            reason: 'tenant_policy_clarification',
            summary: summarize(trimmed, 'La politique du tenant impose une clarification.'),
        };
    }

    if (appointmentIntentDetected || pendingAppointmentClarification) {
        if (!pendingAppointmentClarification && (state?.clarificationCount ?? 0) === 0 && remainingClarifications > 0) {
            return {
                type: 'clarify',
                intent: 'appointment_request',
                reply:
                    'Je peux transmettre une demande de rendez vous a l institut. Quelle prestation souhaitez-vous, et idealement quel jour ou quel moment vous conviendrait ?',
                reason: 'appointment_request_needs_details',
                summary: appointmentSummary(trimmed),
            };
        }

        return appointmentFollowupDecision({
            trimmed,
            transferPhoneNumber,
            promptPolicy,
        });
    }

    if (hasPattern(normalized, VOICEMAIL_PATTERNS) && !transferPhoneNumber) {
        return {
            type: 'fallback',
            reason: 'caller_requested_message',
            summary: summarize(trimmed, 'Le caller souhaite laisser un message.'),
            target: 'voicemail',
            reply: promptPolicy.voicemailInstruction ?? null,
        };
    }

    if (hasPattern(normalized, GOODBYE_PATTERNS)) {
        return {
            type: 'hangup',
            reply: 'Merci pour votre appel. Au revoir.',
            reason: 'caller_finished',
            summary: 'La conversation est terminee apres une reponse exploitable.',
            resolutionType: 'answered',
        };
    }

    const faqMatch = matchFaq(trimmed, bootstrap?.agent?.faq_content);

    if (faqMatch) {
        return {
            type: 'answer',
            reply: faqMatch.answer,
            reason: 'faq_matched',
            summary: summarize(`Question traitee via FAQ: ${faqMatch.topic || faqMatch.answer}`),
            matchedFaq: faqMatch.raw,
        };
    }

    if (remainingClarifications > 0) {
        return {
            type: 'clarify',
            reply: 'Je peux vous aider sur les informations d accueil, les horaires ou organiser une reprise humaine. Quel est votre besoin precis ?',
            reason: 'needs_clarification',
            summary: summarize(trimmed, 'La demande doit etre clarifiee.'),
        };
    }

    return {
        type: transferPhoneNumber ? 'transfer' : 'fallback',
        reason: 'clarification_limit_reached',
        summary: summarize(trimmed, 'La demande reste floue apres plusieurs tours.'),
        target: transferPhoneNumber ? null : 'voicemail',
        targetPhoneNumber: transferPhoneNumber,
        reply: promptPolicy.voicemailInstruction ?? null,
        fallbackMessage: promptPolicy.voicemailInstruction ?? null,
    };
}

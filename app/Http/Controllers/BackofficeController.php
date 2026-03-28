<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\CallMessage;
use App\Models\Tenant;
use App\Support\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BackofficeController extends Controller
{
    public function __construct(private readonly TenantResolver $tenantResolver) {}

    private const OPEN_MESSAGE_STATUSES = [
        CallMessage::STATUS_NEW,
        CallMessage::STATUS_IN_PROGRESS,
    ];

    public function overview(Request $request): Response
    {
        $context = $this->buildContext($request);
        $calls = $context['calls'];
        $summary = $context['summary'];
        $recentCalls = array_slice($calls, 0, 5);

        $activityFeed = [
            [
                'title' => 'Volume entrant',
                'value' => $summary['total_calls'],
                'description' => 'Total d’appels enregistrés sur la période visible.',
            ],
            [
                'title' => 'Boîte de réception',
                'value' => $summary['messages_waiting'],
                'description' => 'Messages à rappeler ou à traiter.',
            ],
            [
                'title' => 'Numéros actifs',
                'value' => count($context['numbers']),
                'description' => 'Lignes connectées au tenant actif.',
            ],
        ];

        return Inertia::render('dashboard/Overview', [
            ...$this->sharedPageData($context),
            'pageMeta' => [
                'title' => 'Vue d’ensemble',
                'description' => 'Supervise l’activité, la qualité de service et les réglages clés depuis un cockpit centralisé.',
            ],
            'recentCalls' => $recentCalls,
            'activityFeed' => $activityFeed,
            'alerts' => $context['alerts'],
            'onboarding' => $context['onboarding'],
            'quickActions' => [
                [
                    'label' => 'Configurer l’agent',
                    'description' => 'Ajuste les messages, les horaires et les règles de transfert.',
                    'href' => route('dashboard.agent'),
                ],
                [
                    'label' => 'Vérifier le routage',
                    'description' => 'Contrôle les numéros actifs et le webhook Twilio.',
                    'href' => route('dashboard.numbers'),
                ],
                [
                    'label' => 'Ouvrir les intégrations',
                    'description' => 'Contrôle les services connectés, les webhooks et les canaux de notification.',
                    'href' => route('dashboard.integrations'),
                ],
            ],
            'integrations' => $context['integrations'],
        ]);
    }

    public function calls(Request $request): Response
    {
        $context = $this->buildContext($request);
        $tenant = $context['tenantModel'];

        $callsQuery = $tenant
            ? $this->applyCallFilters($tenant->calls()->with(['message.assignedTo', 'message.handledBy', 'phoneNumber']), $request)
            : Call::query()->whereRaw('0 = 1');

        $callsPaginator = $callsQuery->paginate(15)->withQueryString();
        $calls = $callsPaginator->getCollection()->map(fn (Call $call) => $this->mapCall($call))->values()->all();

        $callStatsQuery = $tenant
            ? $this->applyCallFilters($tenant->calls(), $request)
            : Call::query()->whereRaw('0 = 1');

        return Inertia::render('dashboard/Calls', [
            ...$this->sharedPageData($context),
            'pageMeta' => [
                'title' => 'Appels',
                'description' => 'Retrouve l’historique d’appels, les statuts de prise en charge et le niveau d’activité du standard.',
            ],
            'calls' => $calls,
            'callStats' => [
                ['label' => 'Appels visibles', 'value' => (clone $callStatsQuery)->count(), 'tone' => 'default'],
                ['label' => 'Messages vocaux', 'value' => (clone $callStatsQuery)->whereHas('message')->count(), 'tone' => 'warning'],
                ['label' => 'Transférés', 'value' => (clone $callStatsQuery)->where('status', 'transferred')->count(), 'tone' => 'success'],
            ],
            'filterOptions' => [
                'statuses' => $this->callStatusOptions(),
            ],
            'appliedFilters' => $this->filterState($request),
            'pagination' => $this->paginationData($callsPaginator),
        ]);
    }

    public function showCall(Request $request, int $call): Response
    {
        $context = $this->buildContext($request);
        $tenant = $context['tenantModel'];

        abort_unless($tenant, 404);

        $callRecord = $tenant->calls()
            ->with(['message.assignedTo', 'message.handledBy', 'phoneNumber'])
            ->whereKey($call)
            ->firstOrFail();

        return Inertia::render('dashboard/CallDetail', [
            ...$this->sharedPageData($context),
            'pageMeta' => [
                'title' => 'Fiche appel',
                'description' => 'Retrouve le cycle de vie complet de l’appel, ses événements Twilio et le message vocal associé.',
            ],
            'call' => [
                ...$this->mapCall($callRecord),
                'tenant_name' => $tenant->name,
            ],
        ]);
    }

    public function messages(Request $request): Response
    {
        $context = $this->buildContext($request);
        $tenant = $context['tenantModel'];

        $messagesQuery = $tenant
            ? $this->applyMessageFilters($tenant->callMessages()->with(['call.phoneNumber', 'assignedTo', 'handledBy']), $request)
            : CallMessage::query()->whereRaw('0 = 1');

        $messagesPaginator = $messagesQuery->paginate(12)->withQueryString();
        $messages = $messagesPaginator->getCollection()->map(fn (CallMessage $message) => $this->mapMessage($message))->values()->all();

        $inboxStatsQuery = $tenant
            ? $this->applyMessageFilters($tenant->callMessages(), $request)
            : CallMessage::query()->whereRaw('0 = 1');

        return Inertia::render('dashboard/Messages', [
            ...$this->sharedPageData($context),
            'pageMeta' => [
                'title' => 'Messages',
                'description' => 'Gère la boîte de réception des demandes de rappel, messages vocaux et suivis prioritaires.',
            ],
            'messages' => $messages,
            'inboxStats' => [
                ['label' => 'À traiter', 'value' => (clone $inboxStatsQuery)->whereIn('status', self::OPEN_MESSAGE_STATUSES)->count(), 'tone' => 'warning'],
                ['label' => 'Messages visibles', 'value' => (clone $inboxStatsQuery)->count(), 'tone' => 'default'],
                ['label' => 'Rappelés', 'value' => (clone $inboxStatsQuery)->where('status', CallMessage::STATUS_CALLED_BACK)->count(), 'tone' => 'success'],
            ],
            'filterOptions' => [
                'statuses' => $this->messageStatusOptions(),
            ],
            'appliedFilters' => $this->filterState($request),
            'serviceRules' => [
                'Tous les messages sont historisés et attachés à un appel.',
                'Le rappel humain reste l’action prioritaire quand une demande attend.',
                'Le traitement suit une lecture claire, priorisée et centralisée.',
            ],
            'pagination' => $this->paginationData($messagesPaginator),
        ]);
    }

    public function agent(Request $request): Response
    {
        $context = $this->buildContext($request);

        return Inertia::render('dashboard/Agent', [
            ...$this->sharedPageData($context),
            'pageMeta' => [
                'title' => 'Agent',
                'description' => 'Centralise l’identité de l’agent, la promesse conversationnelle et les règles de disponibilité.',
            ],
            'settings' => $context['settings'],
            'readiness' => [
                ['label' => 'Score de configuration', 'value' => $context['summary']['configuration_score'].'%', 'description' => 'Indicateur de complétude sur les paramètres essentiels du standard.'],
                ['label' => 'Horaires', 'value' => $context['summary']['open_hours'], 'description' => 'Plage utilisée pour distinguer ouverture et hors horaires.'],
                ['label' => 'Transfert humain', 'value' => $context['summary']['transfer_enabled'] ? 'Disponible' : 'Non renseigné', 'description' => 'Le numéro de transfert permet l’escalade immédiate.'],
            ],
            'capabilities' => [
                ['title' => 'Ton & posture', 'items' => ['Accueil sobre et professionnel', 'Réponses courtes et orientées action', 'Escalade humaine toujours visible']],
                ['title' => 'Cadre métier', 'items' => ['Jours ouvrés et horaires d’ouverture', 'FAQ concise pour les demandes simples', 'Notification email sur message laissé']],
                ['title' => 'Qualité de réponse', 'items' => ['Formulations cohérentes et rassurantes', 'Réponses cadrées sur les besoins fréquents', 'Continuité de service sur toutes les plages horaires']],
            ],
        ]);
    }

    public function numbers(Request $request): Response
    {
        $context = $this->buildContext($request);

        return Inertia::render('dashboard/Numbers', [
            ...$this->sharedPageData($context),
            'pageMeta' => [
                'title' => 'Numéros & routage',
                'description' => 'Visualise les lignes actives, les endpoints opérationnels et la logique de routage associée.',
            ],
            'numbers' => $context['numbers'],
            'webhooks' => $context['webhooks'],
            'twilio' => $context['twilio'],
            'routingSteps' => [
                ['title' => '1. Appel entrant', 'description' => 'Le numéro entrant est reconnu et rattaché au bon espace de travail.'],
                ['title' => '2. Qualification', 'description' => 'Les horaires, la disponibilité et les règles de traitement sont appliqués automatiquement.'],
                ['title' => '3. Action', 'description' => 'L’appel est orienté vers la meilleure action : transfert, accueil ou prise de message.'],
                ['title' => '4. Suivi', 'description' => 'Les interactions sont historisées et distribuées aux bons canaux de suivi.'],
            ],
        ]);
    }

    public function integrations(Request $request): Response
    {
        $context = $this->buildContext($request);

        return Inertia::render('dashboard/Integrations', [
            ...$this->sharedPageData($context),
            'pageMeta' => [
                'title' => 'Intégrations',
                'description' => 'Supervise les services connectés, les canaux de diffusion et les points de synchronisation du workspace.',
            ],
            'integrations' => $context['integrations'],
            'deploymentChecklist' => [
                ['label' => 'Numéro Twilio connecté', 'done' => count($context['numbers']) > 0],
                ['label' => 'Webhook entrant accessible', 'done' => $context['twilio']['last_call'] !== null],
                ['label' => 'Notification email configurée', 'done' => $context['summary']['notification_ready']],
                ['label' => 'Message d’accueil validé', 'done' => filled($context['settings']['welcome_message'])],
            ],
            'healthFeed' => [
                ['title' => 'Téléphonie', 'description' => 'Le routage principal reste disponible pour la gestion des appels entrants.', 'tone' => count($context['numbers']) > 0 ? 'success' : 'warning'],
                ['title' => 'Notifications', 'description' => 'Les alertes de suivi sont distribuées vers la boîte de réception définie.', 'tone' => $context['summary']['notification_ready'] ? 'success' : 'warning'],
                ['title' => 'Traçabilité', 'description' => 'Les interactions, messages et événements sont centralisés dans le dashboard.', 'tone' => 'info'],
            ],
        ]);
    }

    public function workspace(Request $request): Response
    {
        $context = $this->buildContext($request);
        $tenant = $context['tenant'] ?? [];

        return Inertia::render('dashboard/Workspace', [
            ...$this->sharedPageData($context),
            'pageMeta' => [
                'title' => 'Paramètres',
                'description' => 'Retrouve les informations d’organisation, les préférences de compte et les points de gouvernance du workspace.',
            ],
            'workspacePanels' => [
                [
                    'title' => 'Organisation',
                    'items' => [
                        ['label' => 'Entreprise', 'value' => $tenant['name'] ?? 'Organisation'],
                        ['label' => 'Slug', 'value' => $tenant['slug'] ?? 'n/a'],
                        ['label' => 'Locale', 'value' => $tenant['locale'] ?? 'fr-BE'],
                        ['label' => 'Fuseau horaire', 'value' => $tenant['timezone'] ?? 'Europe/Brussels'],
                    ],
                ],
                [
                    'title' => 'Exploitation',
                    'items' => [
                        ['label' => 'Horaires', 'value' => $context['summary']['open_hours']],
                        ['label' => 'Transfert', 'value' => $context['summary']['transfer_enabled'] ? 'Activé' : 'Non configuré'],
                        ['label' => 'Notifications', 'value' => $context['summary']['notification_ready'] ? 'Actives' : 'Non renseignées'],
                        ['label' => 'Complétude', 'value' => $context['summary']['configuration_score'].'%'],
                    ],
                ],
            ],
            'accountLinks' => [
                ['label' => 'Profil utilisateur', 'href' => route('profile.edit')],
                ['label' => 'Mot de passe', 'href' => route('password.edit')],
                ['label' => 'Apparence', 'href' => route('appearance')],
                ['label' => 'Configuration agent', 'href' => route('dashboard.agent')],
            ],
            'governance' => [
                'Utiliser un ton simple, court et rassurant dans tous les messages.',
                'Toujours conserver l’option de transfert humain visible.',
                'Maintenir une expérience homogène sur tous les points de contact.',
            ],
        ]);
    }

    private function sharedPageData(array $context): array
    {
        return [
            'tenant' => $context['tenant'],
            'summary' => $context['summary'],
            'serviceStatus' => $context['serviceStatus'],
        ];
    }

    private function buildContext(Request $request): array
    {
        $tenant = $this->resolveDashboardTenant($request);
        $agentConfig = $tenant?->agentConfig;
        $primaryPhoneNumber = $this->tenantResolver->primaryPhoneNumber($tenant);

        $calls = $tenant
            ? $tenant->calls()->with(['message.assignedTo', 'message.handledBy', 'phoneNumber'])->orderByDesc('started_at')->orderByDesc('id')->take(25)->get()->map(fn (Call $call) => $this->mapCall($call))->values()->all()
            : [];

        $messages = $tenant
            ? $tenant->callMessages()->with(['call.phoneNumber', 'assignedTo', 'handledBy'])->latest()->take(10)->get()->map(fn (CallMessage $message) => $this->mapMessage($message))->values()->all()
            : [];

        $numbers = $tenant
            ? $tenant->phoneNumbers->map(fn ($phoneNumber) => [
                'id' => $phoneNumber->id,
                'label' => $phoneNumber->label,
                'phone_number' => $phoneNumber->phone_number,
                'provider' => strtoupper($phoneNumber->provider),
                'status' => $phoneNumber->is_active ? 'Actif' : 'Inactif',
                'tone' => $phoneNumber->is_active ? 'success' : 'neutral',
                'is_primary' => $phoneNumber->is_primary,
            ])->values()->all()
            : [];

        $settings = [
            'agent_name' => $agentConfig?->agent_name ?? 'Receptio',
            'welcome_message' => $agentConfig?->welcome_message ?? 'Bonjour, vous êtes bien chez Receptio. Comment puis-je vous aider ?',
            'after_hours_message' => $agentConfig?->after_hours_message ?? 'Nous sommes actuellement indisponibles. Merci de laisser un message après le bip.',
            'faq_content' => $agentConfig?->faq_content ?? '',
            'transfer_phone_number' => $agentConfig?->transfer_phone_number ?? '',
            'notification_email' => $agentConfig?->notification_email ?? $request->user()->email,
            'opens_at' => $agentConfig?->opens_at ? substr($agentConfig->opens_at, 0, 5) : null,
            'closes_at' => $agentConfig?->closes_at ? substr($agentConfig->closes_at, 0, 5) : null,
            'business_days' => $agentConfig?->business_days ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'phone_number' => $primaryPhoneNumber?->phone_number ?? '',
        ];

        $checks = [
            filled($tenant?->name),
            filled($settings['phone_number']),
            filled($settings['welcome_message']),
            filled($settings['notification_email']),
            filled($settings['opens_at']) && filled($settings['closes_at']),
            filled($settings['transfer_phone_number']),
        ];

        $configurationScore = (int) round(collect($checks)->filter()->count() / count($checks) * 100);
        $totalCalls = $tenant?->calls()->count() ?? 0;
        $voicemailCalls = $tenant?->calls()->where('status', 'voicemail_received')->count() ?? 0;
        $afterHoursCalls = $tenant?->calls()->where('status', 'after_hours')->count() ?? 0;
        $messagesWaiting = $tenant?->callMessages()->whereIn('status', self::OPEN_MESSAGE_STATUSES)->count() ?? 0;
        $lastTwilioCall = collect($calls)->first(fn (array $call) => filled($call['external_sid']));

        $tenantData = $tenant ? [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'locale' => $tenant->locale,
            'timezone' => $tenant->timezone,
        ] : null;

        $summary = [
            'total_calls' => $totalCalls,
            'voicemail_calls' => $voicemailCalls,
            'after_hours_calls' => $afterHoursCalls,
            'messages_waiting' => $messagesWaiting,
            'open_hours' => $settings['opens_at'] && $settings['closes_at'] ? $settings['opens_at'].' - '.$settings['closes_at'] : 'Non défini',
            'transfer_enabled' => filled($settings['transfer_phone_number']),
            'notification_ready' => filled($settings['notification_email']),
            'configuration_score' => $configurationScore,
        ];

        $serviceStatus = $configurationScore >= 80 && count($numbers) > 0
            ? ['label' => 'Opérationnel', 'tone' => 'success', 'description' => 'Le workspace présente un niveau de configuration stable et cohérent pour l’exploitation.']
            : ['label' => 'Sous contrôle', 'tone' => 'warning', 'description' => 'Quelques paramètres restent à affiner pour aligner l’ensemble des réglages opérationnels.'];

        $alerts = collect([
            ! filled($settings['phone_number']) ? ['title' => 'Aucun numéro principal', 'description' => 'Ajoute ou connecte un numéro Twilio pour recevoir des appels.', 'tone' => 'warning'] : null,
            ! filled($settings['transfer_phone_number']) ? ['title' => 'Transfert humain absent', 'description' => 'Définis un numéro d’escalade pour sécuriser les cas sensibles.', 'tone' => 'info'] : null,
            blank($settings['faq_content']) ? ['title' => 'FAQ encore vide', 'description' => 'Ajoute des réponses courtes sur les questions fréquentes pour cadrer l’agent.', 'tone' => 'info'] : null,
            ! filled($settings['opens_at']) || ! filled($settings['closes_at']) ? ['title' => 'Horaires non finalisés', 'description' => 'Précise les horaires d’ouverture pour fiabiliser le basculement hors horaires.', 'tone' => 'warning'] : null,
        ])->filter()->values()->all();

        $onboarding = [
            ['label' => 'Numéro principal relié', 'done' => count($numbers) > 0],
            ['label' => 'Accueil vocal validé', 'done' => filled($settings['welcome_message'])],
            ['label' => 'Notifications actives', 'done' => filled($settings['notification_email'])],
            ['label' => 'Escalade disponible', 'done' => filled($settings['transfer_phone_number'])],
        ];

        $integrations = [
            ['name' => 'Twilio Voice', 'status' => count($numbers) > 0 ? 'Connecté' : 'En attente', 'tone' => count($numbers) > 0 ? 'success' : 'warning', 'description' => 'Gestion des appels entrants, du routage vocal et des événements téléphoniques.'],
            ['name' => 'Notifications email', 'status' => filled($settings['notification_email']) ? 'Actif' : 'En attente', 'tone' => filled($settings['notification_email']) ? 'success' : 'warning', 'description' => 'Distribution des demandes de rappel et des messages vers l’équipe concernée.'],
            ['name' => 'Journal d’activité', 'status' => 'Disponible', 'tone' => 'info', 'description' => 'Historisation centralisée des appels, messages et événements métier.'],
            ['name' => 'Escalade téléphonique', 'status' => filled($settings['transfer_phone_number']) ? 'Active' : 'En attente', 'tone' => 'neutral', 'description' => 'Bascule des appels vers une ligne de reprise définie par l’organisation.'],
        ];

        return [
            'tenant' => $tenantData,
            'tenantModel' => $tenant,
            'summary' => $summary,
            'settings' => $settings,
            'calls' => $calls,
            'messages' => $messages,
            'numbers' => $numbers,
            'alerts' => $alerts,
            'onboarding' => $onboarding,
            'integrations' => $integrations,
            'serviceStatus' => $serviceStatus,
            'twilio' => ['last_call' => $lastTwilioCall],
            'webhooks' => [
                'incoming' => route('webhooks.twilio.voice.incoming', absolute: true),
                'menu' => route('webhooks.twilio.voice.menu', absolute: true),
                'status' => route('webhooks.twilio.voice.status', absolute: true),
                'recording' => route('webhooks.twilio.voice.recording', absolute: true),
                'ping' => route('webhooks.twilio.voice.ping', absolute: true),
            ],
        ];
    }

    private function resolveDashboardTenant(Request $request): ?Tenant
    {
        return $this->tenantResolver->forUser($request->user(), ['agentConfig', 'phoneNumbers']);
    }

    private function applyCallFilters(Builder|Relation $query, Request $request): Builder|Relation
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        if (filled($status)) {
            $query->where('status', $status);
        }

        if (filled($search)) {
            $query->where(function (Builder $callQuery) use ($search) {
                $callQuery
                    ->where('from_number', 'like', "%{$search}%")
                    ->orWhere('to_number', 'like', "%{$search}%")
                    ->orWhere('external_sid', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        if (filled($dateFrom)) {
            $query->whereDate('started_at', '>=', $dateFrom);
        }

        if (filled($dateTo)) {
            $query->whereDate('started_at', '<=', $dateTo);
        }

        return $query->orderByDesc('started_at')->orderByDesc('id');
    }

    private function applyMessageFilters(Builder|Relation $query, Request $request): Builder|Relation
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        if (filled($status)) {
            $query->where('status', $status);
        }

        if (filled($search)) {
            $query->where(function (Builder $messageQuery) use ($search) {
                $messageQuery
                    ->where('caller_name', 'like', "%{$search}%")
                    ->orWhere('caller_number', 'like', "%{$search}%")
                    ->orWhere('message_text', 'like', "%{$search}%")
                    ->orWhereHas('call', function (Builder $callQuery) use ($search) {
                        $callQuery
                            ->where('from_number', 'like', "%{$search}%")
                            ->orWhere('external_sid', 'like', "%{$search}%")
                            ->orWhere('summary', 'like', "%{$search}%");
                    });
            });
        }

        if (filled($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (filled($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->latest();
    }

    private function filterState(Request $request): array
    {
        return [
            'status' => $request->string('status')->toString(),
            'search' => $request->string('search')->toString(),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
        ];
    }

    private function paginationData(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'previous_page_url' => $paginator->previousPageUrl(),
            'next_page_url' => $paginator->nextPageUrl(),
        ];
    }

    private function mapCall(Call $call): array
    {
        $durationSeconds = data_get($call->metadata, 'call_duration_seconds')
            ?? data_get($call->metadata, 'dial_call_duration_seconds')
            ?? ($call->started_at && $call->ended_at ? $call->started_at->diffInSeconds($call->ended_at) : null);

        $recentStatusEvents = collect(data_get($call->metadata, 'status_events', []))
            ->filter(fn ($event) => is_array($event))
            ->take(-5)
            ->values()
            ->map(fn (array $event) => [
                'received_at' => data_get($event, 'received_at'),
                'call_status' => data_get($event, 'call_status'),
                'call_duration_seconds' => is_numeric(data_get($event, 'call_duration_seconds')) ? (int) data_get($event, 'call_duration_seconds') : null,
                'dial_call_status' => data_get($event, 'dial_call_status'),
                'dial_call_duration_seconds' => is_numeric(data_get($event, 'dial_call_duration_seconds')) ? (int) data_get($event, 'dial_call_duration_seconds') : null,
                'dial_call_sid' => data_get($event, 'dial_call_sid'),
                'callback_source' => data_get($event, 'callback_source'),
                'sequence_number' => data_get($event, 'sequence_number'),
            ])
            ->all();

        return [
            'id' => $call->id,
            'external_sid' => $call->external_sid,
            'status' => $call->status,
            'status_label' => $this->statusLabel($call->status),
            'tone' => $this->statusTone($call->status),
            'direction' => $call->direction,
            'from_number' => $call->from_number,
            'to_number' => $call->to_number,
            'phone_label' => $call->phoneNumber?->label,
            'started_at' => $call->started_at?->toIso8601String(),
            'ended_at' => $call->ended_at?->toIso8601String(),
            'duration_seconds' => is_numeric($durationSeconds) ? (int) $durationSeconds : null,
            'summary' => $call->summary,
            'transfer_failure_status' => data_get($call->metadata, 'transfer_failure_status'),
            'fallback_target' => data_get($call->metadata, 'fallback_target'),
            'recent_status_events' => $recentStatusEvents,
            'message' => $call->message ? [
                'caller_name' => $call->message->caller_name,
                'caller_number' => $call->message->caller_number,
                'message_text' => $call->message->message_text,
                'recording_url' => $call->message->recording_url,
                'recording_duration' => $call->message->recording_duration,
                'workflow_status' => $call->message->status,
                'workflow_status_label' => $this->messageStatusLabel($call->message->status),
                'workflow_status_tone' => $this->messageStatusTone($call->message->status),
                'assigned_to_name' => $call->message->assignedTo?->name,
                'handled_by_name' => $call->message->handledBy?->name,
                'handled_at' => $call->message->handled_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function mapMessage(CallMessage $message): array
    {
        $call = $message->call;
        $prioritySource = Str::lower(($call?->summary ?? '').' '.($message->message_text ?? ''));
        $priority = Str::contains($prioritySource, ['urgent', 'rapidement', 'aujourd', 'immediat', 'immédiat']) ? 'Élevée' : 'Normale';

        return [
            'id' => $message->id,
            'call_id' => $message->call_id,
            'call_external_sid' => $call?->external_sid,
            'caller' => $message->caller_name ?: $message->caller_number ?: $call?->from_number ?: 'Contact inconnu',
            'phone' => $message->caller_number ?: $call?->from_number ?: 'n/a',
            'excerpt' => Str::limit($message->message_text ?? 'Message vocal enregistré.', 160),
            'message_text' => $message->message_text,
            'recording_url' => $message->recording_url,
            'recording_duration' => $message->recording_duration,
            'status' => $message->status,
            'status_label' => $this->messageStatusLabel($message->status),
            'status_tone' => $this->messageStatusTone($message->status),
            'call_status' => $call?->status,
            'call_status_label' => $call?->status ? $this->statusLabel($call->status) : null,
            'priority' => $priority,
            'created_at' => $message->created_at?->toIso8601String(),
            'summary' => $call?->summary,
            'assigned_to_name' => $message->assignedTo?->name,
            'handled_by_name' => $message->handledBy?->name,
            'handled_at' => $message->handled_at?->toIso8601String(),
        ];
    }

    private function callStatusOptions(): array
    {
        return [
            ['label' => 'Tous les statuts', 'value' => ''],
            ['label' => 'Reçu', 'value' => 'received'],
            ['label' => 'En cours', 'value' => 'in_progress'],
            ['label' => 'Transféré', 'value' => 'transferred'],
            ['label' => 'Message reçu', 'value' => 'voicemail_received'],
            ['label' => 'Hors horaires', 'value' => 'after_hours'],
            ['label' => 'Terminé', 'value' => 'completed'],
            ['label' => 'Échec', 'value' => 'failed'],
        ];
    }

    private function messageStatusOptions(): array
    {
        return [
            ['label' => 'Tous les statuts', 'value' => ''],
            ['label' => 'Nouveau', 'value' => CallMessage::STATUS_NEW],
            ['label' => 'En cours', 'value' => CallMessage::STATUS_IN_PROGRESS],
            ['label' => 'Rappelé', 'value' => CallMessage::STATUS_CALLED_BACK],
            ['label' => 'Clôturé', 'value' => CallMessage::STATUS_CLOSED],
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'received' => 'Reçu',
            'menu_offered' => 'Menu proposé',
            'transferring' => 'Transfert en cours',
            'transferred' => 'Transféré',
            'voicemail_prompted' => 'Messagerie proposée',
            'voicemail_received' => 'Message reçu',
            'after_hours' => 'Hors horaires',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'failed' => 'Échec',
            'no_answer' => 'Sans réponse',
            'busy' => 'Occupé',
            default => Str::headline(str_replace('_', ' ', $status)),
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'voicemail_received' => 'warning',
            'after_hours' => 'info',
            'completed', 'transferred' => 'success',
            'busy', 'failed', 'no_answer' => 'warning',
            'transferring', 'in_progress' => 'info',
            default => 'default',
        };
    }

    private function messageStatusLabel(string $status): string
    {
        return match ($status) {
            CallMessage::STATUS_NEW => 'Nouveau',
            CallMessage::STATUS_IN_PROGRESS => 'En cours',
            CallMessage::STATUS_CALLED_BACK => 'Rappelé',
            CallMessage::STATUS_CLOSED => 'Clôturé',
            default => Str::headline(str_replace('_', ' ', $status)),
        };
    }

    private function messageStatusTone(string $status): string
    {
        return match ($status) {
            CallMessage::STATUS_NEW => 'warning',
            CallMessage::STATUS_IN_PROGRESS => 'info',
            CallMessage::STATUS_CALLED_BACK => 'success',
            CallMessage::STATUS_CLOSED => 'neutral',
            default => 'default',
        };
    }
}

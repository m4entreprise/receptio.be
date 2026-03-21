<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BackofficeController extends Controller
{
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
        $calls = $context['calls'];

        return Inertia::render('dashboard/Calls', [
            ...$this->sharedPageData($context),
            'pageMeta' => [
                'title' => 'Appels',
                'description' => 'Retrouve l’historique d’appels, les statuts de prise en charge et le niveau d’activité du standard.',
            ],
            'calls' => $calls,
            'callStats' => [
                [
                    'label' => 'Appels totaux',
                    'value' => $context['summary']['total_calls'],
                    'tone' => 'default',
                ],
                [
                    'label' => 'Transferts actifs',
                    'value' => $context['summary']['transfer_enabled'] ? 'Activé' : 'Inactif',
                    'tone' => $context['summary']['transfer_enabled'] ? 'success' : 'warning',
                ],
                [
                    'label' => 'Messages laissés',
                    'value' => $context['summary']['voicemail_calls'],
                    'tone' => 'info',
                ],
            ],
            'filters' => [
                ['label' => 'Tous les statuts', 'active' => true],
                ['label' => 'Messages reçus', 'active' => false],
                ['label' => 'Après horaires', 'active' => false],
                ['label' => 'Transférés', 'active' => false],
            ],
        ]);
    }

    public function messages(Request $request): Response
    {
        $context = $this->buildContext($request);

        return Inertia::render('dashboard/Messages', [
            ...$this->sharedPageData($context),
            'pageMeta' => [
                'title' => 'Messages',
                'description' => 'Gère la boîte de réception des demandes de rappel, messages vocaux et suivis prioritaires.',
            ],
            'messages' => $context['messages'],
            'inboxStats' => [
                [
                    'label' => 'À traiter',
                    'value' => $context['summary']['messages_waiting'],
                    'tone' => 'warning',
                ],
                [
                    'label' => 'Messages enregistrés',
                    'value' => count($context['messages']),
                    'tone' => 'default',
                ],
                [
                    'label' => 'Notifications email',
                    'value' => $context['summary']['notification_ready'] ? 'Actives' : 'À renseigner',
                    'tone' => $context['summary']['notification_ready'] ? 'success' : 'warning',
                ],
            ],
            'serviceRules' => [
                'Tous les messages sont historisés et attachés à un appel.',
                'Le rappel humain reste l’action prioritaire quand une demande attend.',
                'Le traitement suit une lecture claire, priorisée et centralisée.',
            ],
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
                [
                    'label' => 'Score de configuration',
                    'value' => $context['summary']['configuration_score'].'%',
                    'description' => 'Indicateur de complétude sur les paramètres essentiels du standard.',
                ],
                [
                    'label' => 'Horaires',
                    'value' => $context['summary']['open_hours'],
                    'description' => 'Plage utilisée pour distinguer ouverture et hors horaires.',
                ],
                [
                    'label' => 'Transfert humain',
                    'value' => $context['summary']['transfer_enabled'] ? 'Disponible' : 'Non renseigné',
                    'description' => 'Le numéro de transfert permet l’escalade immédiate.',
                ],
            ],
            'capabilities' => [
                [
                    'title' => 'Ton & posture',
                    'items' => ['Accueil sobre et professionnel', 'Réponses courtes et orientées action', 'Escalade humaine toujours visible'],
                ],
                [
                    'title' => 'Cadre métier',
                    'items' => ['Jours ouvrés et horaires d’ouverture', 'FAQ concise pour les demandes simples', 'Notification email sur message laissé'],
                ],
                [
                    'title' => 'Qualité de réponse',
                    'items' => ['Formulations cohérentes et rassurantes', 'Réponses cadrées sur les besoins fréquents', 'Continuité de service sur toutes les plages horaires'],
                ],
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
            'routingSteps' => [
                [
                    'title' => '1. Appel entrant',
                    'description' => 'Le numéro entrant est reconnu et rattaché au bon espace de travail.',
                ],
                [
                    'title' => '2. Qualification',
                    'description' => 'Les horaires, la disponibilité et les règles de traitement sont appliqués automatiquement.',
                ],
                [
                    'title' => '3. Action',
                    'description' => 'L’appel est orienté vers la meilleure action : transfert, accueil ou prise de message.',
                ],
                [
                    'title' => '4. Suivi',
                    'description' => 'Les interactions sont historisées et distribuées aux bons canaux de suivi.',
                ],
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
                ['label' => 'Webhook entrant accessible', 'done' => filled($context['webhooks']['incoming'])],
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
        $tenant = $request->user()->tenant()->with(['agentConfig', 'phoneNumbers', 'calls.message', 'calls.phoneNumber'])->first()
            ?? Tenant::with(['agentConfig', 'phoneNumbers', 'calls.message', 'calls.phoneNumber'])->first();

        $agentConfig = $tenant?->agentConfig;
        $calls = $tenant
            ? $tenant->calls()->with(['message', 'phoneNumber'])->latest()->take(25)->get()->map(fn (Call $call) => $this->mapCall($call))->values()->all()
            : [];

        $messages = collect($calls)
            ->filter(fn (array $call) => $call['message'] !== null)
            ->map(fn (array $call) => [
                'id' => $call['id'],
                'caller' => $call['message']['caller_name'] ?: $call['from_number'] ?: 'Contact inconnu',
                'phone' => $call['message']['caller_number'] ?: $call['from_number'] ?: 'n/a',
                'excerpt' => Str::limit($call['message']['message_text'] ?? 'Message vocal enregistré.', 120),
                'recording_url' => $call['message']['recording_url'],
                'status' => $call['status'],
                'status_label' => $call['status_label'],
                'priority' => Str::contains(Str::lower(($call['summary'] ?? '').' '.($call['message']['message_text'] ?? '')), ['urgent', 'rapidement', 'aujourd']) ? 'Élevée' : 'Normale',
                'created_at' => $call['started_at'],
                'summary' => $call['summary'],
            ])
            ->values()
            ->all();

        $numbers = $tenant
            ? $tenant->phoneNumbers->map(fn ($phoneNumber) => [
                'id' => $phoneNumber->id,
                'label' => $phoneNumber->label,
                'phone_number' => $phoneNumber->phone_number,
                'provider' => strtoupper($phoneNumber->provider),
                'status' => $phoneNumber->is_active ? 'Actif' : 'Inactif',
                'tone' => $phoneNumber->is_active ? 'success' : 'neutral',
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
            'phone_number' => $numbers[0]['phone_number'] ?? '',
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
        $voicemailCalls = collect($calls)->filter(fn (array $call) => $call['status'] === 'voicemail_received')->count();
        $afterHoursCalls = collect($calls)->filter(fn (array $call) => $call['status'] === 'after_hours')->count();

        $tenantData = $tenant ? [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'locale' => $tenant->locale,
            'timezone' => $tenant->timezone,
        ] : null;

        $summary = [
            'total_calls' => count($calls),
            'voicemail_calls' => $voicemailCalls,
            'after_hours_calls' => $afterHoursCalls,
            'messages_waiting' => count($messages),
            'open_hours' => $settings['opens_at'] && $settings['closes_at'] ? $settings['opens_at'].' - '.$settings['closes_at'] : 'Non défini',
            'transfer_enabled' => filled($settings['transfer_phone_number']),
            'notification_ready' => filled($settings['notification_email']),
            'configuration_score' => $configurationScore,
        ];

        $serviceStatus = $configurationScore >= 80 && count($numbers) > 0
            ? [
                'label' => 'Opérationnel',
                'tone' => 'success',
                'description' => 'Le workspace présente un niveau de configuration stable et cohérent pour l’exploitation.',
            ]
            : [
                'label' => 'Sous contrôle',
                'tone' => 'warning',
                'description' => 'Quelques paramètres restent à affiner pour aligner l’ensemble des réglages opérationnels.',
            ];

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
            [
                'name' => 'Twilio Voice',
                'status' => count($numbers) > 0 ? 'Connecté' : 'En attente',
                'tone' => count($numbers) > 0 ? 'success' : 'warning',
                'description' => 'Gestion des appels entrants, du routage vocal et des événements téléphoniques.',
            ],
            [
                'name' => 'Notifications email',
                'status' => filled($settings['notification_email']) ? 'Actif' : 'En attente',
                'tone' => filled($settings['notification_email']) ? 'success' : 'warning',
                'description' => 'Distribution des demandes de rappel et des messages vers l’équipe concernée.',
            ],
            [
                'name' => 'Journal d’activité',
                'status' => 'Disponible',
                'tone' => 'info',
                'description' => 'Historisation centralisée des appels, messages et événements métier.',
            ],
            [
                'name' => 'Escalade téléphonique',
                'status' => filled($settings['transfer_phone_number']) ? 'Active' : 'En attente',
                'tone' => 'neutral',
                'description' => 'Bascule des appels vers une ligne de reprise définie par l’organisation.',
            ],
        ];

        return [
            'tenant' => $tenantData,
            'summary' => $summary,
            'settings' => $settings,
            'calls' => $calls,
            'messages' => $messages,
            'numbers' => $numbers,
            'alerts' => $alerts,
            'onboarding' => $onboarding,
            'integrations' => $integrations,
            'serviceStatus' => $serviceStatus,
            'webhooks' => [
                'incoming' => route('webhooks.twilio.voice.incoming', absolute: true),
                'menu' => route('webhooks.twilio.voice.menu', absolute: true),
                'recording' => route('webhooks.twilio.voice.recording', absolute: true),
            ],
        ];
    }

    private function mapCall(Call $call): array
    {
        return [
            'id' => $call->id,
            'status' => $call->status,
            'status_label' => $this->statusLabel($call->status),
            'tone' => $this->statusTone($call->status),
            'direction' => $call->direction,
            'from_number' => $call->from_number,
            'to_number' => $call->to_number,
            'phone_label' => $call->phoneNumber?->label,
            'started_at' => $call->started_at?->toIso8601String(),
            'ended_at' => $call->ended_at?->toIso8601String(),
            'duration_seconds' => $call->started_at && $call->ended_at ? $call->started_at->diffInSeconds($call->ended_at) : null,
            'summary' => $call->summary,
            'message' => $call->message ? [
                'caller_name' => $call->message->caller_name,
                'caller_number' => $call->message->caller_number,
                'message_text' => $call->message->message_text,
                'recording_url' => $call->message->recording_url,
            ] : null,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'voicemail_received' => 'Message reçu',
            'after_hours' => 'Hors horaires',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            default => Str::headline(str_replace('_', ' ', $status)),
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'voicemail_received' => 'warning',
            'after_hours' => 'info',
            'completed' => 'success',
            default => 'default',
        };
    }
}

<script setup lang="ts">
import BackofficePageHeader from '@/components/dashboard/BackofficePageHeader.vue';
import EmptyStateCard from '@/components/dashboard/EmptyStateCard.vue';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import ToneBadge from '@/components/dashboard/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CallItem, IntegrationItem, ServiceStatus, TenantSummary, WorkspaceSummary } from '@/types/backoffice';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BellRing, Clock3, PhoneCall, ShieldCheck } from 'lucide-vue-next';

const breadcrumbs = [{ title: 'Vue d’ensemble', href: '/dashboard' }];

interface Props {
    tenant: TenantSummary | null;
    summary: WorkspaceSummary;
    serviceStatus: ServiceStatus;
    pageMeta: {
        title: string;
        description: string;
    };
    recentCalls: CallItem[];
    activityFeed: Array<{
        title: string;
        value: number | string;
        description: string;
    }>;
    alerts: Array<{
        title: string;
        description: string;
        tone: 'default' | 'success' | 'warning' | 'info' | 'neutral';
    }>;
    onboarding: Array<{
        label: string;
        done: boolean;
    }>;
    quickActions: Array<{
        label: string;
        description: string;
        href: string;
    }>;
    integrations: IntegrationItem[];
}

const props = defineProps<Props>();

const serviceMarkers = [
    {
        title: 'Statut de service',
        value: props.serviceStatus.label,
        icon: ShieldCheck,
    },
    {
        title: 'Horaires',
        value: props.summary.open_hours,
        icon: Clock3,
    },
    {
        title: 'Messages',
        value: `${props.summary.messages_waiting}`,
        icon: BellRing,
    },
    {
        title: 'Routage',
        value: props.summary.transfer_enabled ? 'Actif' : 'En réglage',
        icon: PhoneCall,
    },
];
</script>

<template>
    <Head :title="pageMeta.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 rounded-xl p-4">
            <BackofficePageHeader
                :title="pageMeta.title"
                :description="pageMeta.description"
                :badge-label="serviceStatus.label"
                :badge-tone="serviceStatus.tone"
                action-label="Ouvrir l’agent"
                :action-href="route('dashboard.agent')"
            />

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard title="Appels totaux" :value="summary.total_calls" description="Volume consolidé du standard." />
                <MetricCard title="Messages à traiter" :value="summary.messages_waiting" description="Demandes de rappel en attente." />
                <MetricCard title="Hors horaires" :value="summary.after_hours_calls" description="Appels reçus en dehors des créneaux." />
                <MetricCard title="Horaires actifs" :value="summary.open_hours" description="Fenêtre utilisée par l’agent vocal." />
            </div>

            <div class="grid gap-6 2xl:grid-cols-[1.3fr_1fr]">
                <div class="space-y-6">
                    <Card class="border-border/70 bg-background/95 shadow-[0_16px_45px_-28px_rgba(15,23,42,0.28)]">
                        <CardHeader>
                            <CardDescription>Pilotage</CardDescription>
                            <CardTitle>Résumé exécutif</CardTitle>
                        </CardHeader>
                        <CardContent class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                            <div class="space-y-4 rounded-[1.5rem] border border-border/60 bg-muted/20 p-5">
                                <div class="space-y-2">
                                    <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Organisation active</p>
                                    <p class="text-2xl font-semibold tracking-tight text-foreground">{{ tenant?.name ?? 'Receptio' }}</p>
                                    <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                                        {{ serviceStatus.description }} Les indicateurs critiques et les accès d’action restent centralisés sur cette vue.
                                    </p>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div v-for="item in serviceMarkers" :key="item.title" class="rounded-2xl border border-border/60 bg-background px-4 py-4">
                                        <div class="mb-3 inline-flex rounded-xl border border-border/60 bg-muted/40 p-2 text-foreground">
                                            <component :is="item.icon" class="size-4" />
                                        </div>
                                        <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">{{ item.title }}</p>
                                        <p class="mt-2 text-lg font-semibold tracking-tight text-foreground">{{ item.value }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3 rounded-[1.5rem] border border-border/60 bg-background p-5">
                                <div class="space-y-1">
                                    <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Contrôles</p>
                                    <p class="text-lg font-semibold tracking-tight">Éléments de supervision</p>
                                </div>
                                <div
                                    v-for="step in onboarding"
                                    :key="step.label"
                                    class="flex items-center justify-between rounded-2xl border border-border/60 bg-muted/20 px-4 py-3"
                                >
                                    <span class="text-sm font-medium">{{ step.label }}</span>
                                    <ToneBadge :label="step.done ? 'OK' : 'À faire'" :tone="step.done ? 'success' : 'warning'" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 bg-background/95 shadow-[0_16px_45px_-28px_rgba(15,23,42,0.28)]">
                        <CardHeader class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                            <div>
                                <CardDescription>Vue opérationnelle</CardDescription>
                                <CardTitle>Aperçu d’activité</CardTitle>
                            </div>
                            <Button as-child variant="outline">
                                <Link :href="route('dashboard.calls')">Voir tous les appels</Link>
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="item in activityFeed" :key="item.title" class="rounded-2xl border border-border/60 px-4 py-4 transition hover:border-primary/20 hover:bg-primary/5">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1">
                                        <p class="text-sm font-medium">{{ item.title }}</p>
                                        <p class="text-sm text-muted-foreground">{{ item.description }}</p>
                                    </div>
                                    <div class="text-2xl font-semibold tracking-tight">{{ item.value }}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 bg-background/95 shadow-[0_16px_45px_-28px_rgba(15,23,42,0.28)]">
                        <CardHeader class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                            <div>
                                <CardDescription>Dernières interactions</CardDescription>
                                <CardTitle>Appels récents</CardTitle>
                            </div>
                            <Button as-child variant="ghost" class="gap-2">
                                <Link :href="route('dashboard.messages')">
                                    Ouvrir la boîte messages
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <EmptyStateCard
                                v-if="recentCalls.length === 0"
                                title="Aucune interaction récente"
                                description="Les appels récents, les messages associés et les résumés d’échange apparaîtront ici dans un format consolidé."
                                action-label="Ouvrir le routage"
                                :action-href="route('dashboard.numbers')"
                            />
                            <div v-else class="space-y-3">
                                <div v-for="call in recentCalls" :key="call.id" class="rounded-2xl border border-border/60 p-4">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div class="space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-medium">{{ call.from_number ?? 'Numéro inconnu' }}</p>
                                                <ToneBadge :label="call.status_label" :tone="call.tone" />
                                            </div>
                                            <p class="text-sm text-muted-foreground">
                                                {{ call.started_at ? new Date(call.started_at).toLocaleString('fr-BE') : 'Date inconnue' }}
                                            </p>
                                        </div>
                                        <p class="text-sm text-muted-foreground">{{ call.to_number ?? 'Ligne principale' }}</p>
                                    </div>
                                    <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ call.summary ?? 'Aucun résumé disponible pour cet appel.' }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card class="border-border/70 bg-background/95 shadow-[0_16px_45px_-28px_rgba(15,23,42,0.28)]">
                        <CardHeader>
                            <CardDescription>Priorités</CardDescription>
                            <CardTitle>Points d’attention</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="alerts.length === 0" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                                Aucun point sensible détecté. L’ensemble du workspace reste lisible et sous contrôle.
                            </div>
                            <div v-else class="space-y-3">
                                <div v-for="alert in alerts" :key="alert.title" class="rounded-2xl border border-border/60 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-medium">{{ alert.title }}</p>
                                        <ToneBadge :label="alert.tone === 'warning' ? 'Attention' : 'Info'" :tone="alert.tone" />
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ alert.description }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 bg-background/95 shadow-[0_16px_45px_-28px_rgba(15,23,42,0.28)]">
                        <CardHeader>
                            <CardDescription>Services connectés</CardDescription>
                            <CardTitle>État des intégrations</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="integration in integrations" :key="integration.name" class="rounded-2xl border border-border/60 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium">{{ integration.name }}</p>
                                    <ToneBadge :label="integration.status" :tone="integration.tone" />
                                </div>
                                <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ integration.description }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 bg-background/95 shadow-[0_16px_45px_-28px_rgba(15,23,42,0.28)]">
                        <CardHeader>
                            <CardDescription>Raccourcis</CardDescription>
                            <CardTitle>Actions rapides</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Link
                                v-for="action in quickActions"
                                :key="action.label"
                                :href="action.href"
                                class="flex items-center justify-between rounded-2xl border border-border/60 px-4 py-4 transition hover:border-primary/20 hover:bg-primary/5"
                            >
                                <div class="space-y-1">
                                    <p class="text-sm font-medium">{{ action.label }}</p>
                                    <p class="text-sm text-muted-foreground">{{ action.description }}</p>
                                </div>
                                <ArrowRight class="size-4 text-muted-foreground" />
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

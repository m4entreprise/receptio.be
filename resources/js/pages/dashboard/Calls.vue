<script setup lang="ts">
import BackofficePageHeader from '@/components/dashboard/BackofficePageHeader.vue';
import EmptyStateCard from '@/components/dashboard/EmptyStateCard.vue';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import ToneBadge from '@/components/dashboard/ToneBadge.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CallItem, ServiceStatus, TenantSummary, WorkspaceSummary } from '@/types/backoffice';
import { Head } from '@inertiajs/vue3';

const breadcrumbs = [
    { title: 'Vue d’ensemble', href: '/dashboard' },
    { title: 'Appels', href: '/dashboard/calls' },
];

interface Props {
    tenant: TenantSummary | null;
    summary: WorkspaceSummary;
    serviceStatus: ServiceStatus;
    pageMeta: {
        title: string;
        description: string;
    };
    calls: CallItem[];
    callStats: Array<{
        label: string;
        value: number | string;
        tone: 'default' | 'success' | 'warning' | 'info' | 'neutral';
    }>;
    filters: Array<{
        label: string;
        active: boolean;
    }>;
}

defineProps<Props>();
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
                action-label="Boîte messages"
                :action-href="route('dashboard.messages')"
            />

            <div class="grid gap-4 md:grid-cols-3">
                <div v-for="stat in callStats" :key="stat.label">
                    <MetricCard :title="stat.label" :value="stat.value" :description="tenant?.name ?? 'Organisation active'" />
                </div>
            </div>

            <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                <CardHeader>
                    <CardDescription>Filtres</CardDescription>
                    <CardTitle>Lecture rapide de l’activité</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-3">
                    <div
                        v-for="filter in filters"
                        :key="filter.label"
                        :class="filter.active ? 'border-primary/30 bg-primary/5 text-primary' : 'border-border/60 bg-background text-muted-foreground'"
                        class="rounded-full border px-4 py-2 text-sm font-medium"
                    >
                        {{ filter.label }}
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                <CardHeader>
                    <CardDescription>Historique</CardDescription>
                    <CardTitle>Toutes les interactions téléphoniques</CardTitle>
                </CardHeader>
                <CardContent>
                    <EmptyStateCard
                        v-if="calls.length === 0"
                        title="Aucun appel enregistré"
                        description="L’historique des appels, leurs statuts et leurs résumés apparaîtront ici dans une vue unifiée dès l’arrivée des premières interactions."
                        action-label="Ouvrir les numéros"
                        :action-href="route('dashboard.numbers')"
                    />
                    <div v-else class="overflow-hidden rounded-3xl border border-border/60">
                        <div class="hidden grid-cols-[1.3fr_0.8fr_0.8fr_0.8fr_1.4fr] gap-4 border-b border-border/60 bg-muted/30 px-6 py-4 text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground md:grid">
                            <div>Appelant</div>
                            <div>Statut</div>
                            <div>Horodatage</div>
                            <div>Durée</div>
                            <div>Résumé</div>
                        </div>
                        <div class="divide-y divide-border/60">
                            <div v-for="call in calls" :key="call.id" class="grid gap-4 px-6 py-5 md:grid-cols-[1.3fr_0.8fr_0.8fr_0.8fr_1.4fr] md:items-start">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-medium">{{ call.from_number ?? 'Numéro inconnu' }}</p>
                                        <ToneBadge :label="call.direction ?? 'inbound'" tone="neutral" />
                                    </div>
                                    <p class="text-sm text-muted-foreground">Vers {{ call.to_number ?? 'numéro principal' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ call.phone_label ?? 'Ligne standard' }}</p>
                                </div>
                                <div>
                                    <ToneBadge :label="call.status_label" :tone="call.tone" />
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ call.started_at ? new Date(call.started_at).toLocaleString('fr-BE') : 'n/a' }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ call.duration_seconds !== null ? `${call.duration_seconds}s` : 'n/a' }}
                                </div>
                                <div class="space-y-2 text-sm text-muted-foreground">
                                    <p>{{ call.summary ?? 'Aucun résumé disponible.' }}</p>
                                    <p v-if="call.message" class="rounded-2xl bg-muted/30 px-3 py-2 text-xs">
                                        Message: {{ call.message.message_text ?? 'Enregistrement vocal disponible.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

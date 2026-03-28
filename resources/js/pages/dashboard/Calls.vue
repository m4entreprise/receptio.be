<script setup lang="ts">
import BackofficePageHeader from '@/components/dashboard/BackofficePageHeader.vue';
import EmptyStateCard from '@/components/dashboard/EmptyStateCard.vue';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import ToneBadge from '@/components/dashboard/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppliedFilters, CallItem, PaginationData, SelectOption, ServiceStatus, TenantSummary, WorkspaceSummary } from '@/types/backoffice';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

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
    filterOptions: {
        statuses: SelectOption[];
    };
    appliedFilters: AppliedFilters;
    pagination: PaginationData;
}

const props = defineProps<Props>();

const filters = reactive({
    status: props.appliedFilters.status,
    search: props.appliedFilters.search,
    date_from: props.appliedFilters.date_from,
    date_to: props.appliedFilters.date_to,
});

const applyFilters = () => {
    router.get(route('dashboard.calls'), filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filters.status = '';
    filters.search = '';
    filters.date_from = '';
    filters.date_to = '';
    applyFilters();
};
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
                    <CardTitle>Recherche opérationnelle</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-4 lg:grid-cols-[1fr_1fr_0.8fr_0.8fr_auto]" @submit.prevent="applyFilters">
                        <div class="grid gap-2">
                            <Label for="search">Numéro ou CallSid</Label>
                            <Input id="search" v-model="filters.search" placeholder="+32..., CA..., résumé..." />
                        </div>
                        <div class="grid gap-2">
                            <Label for="status">Statut</Label>
                            <select
                                id="status"
                                v-model="filters.status"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                            >
                                <option v-for="option in filterOptions.statuses" :key="option.value || 'all'" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="date_from">Du</Label>
                            <Input id="date_from" v-model="filters.date_from" type="date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="date_to">Au</Label>
                            <Input id="date_to" v-model="filters.date_to" type="date" />
                        </div>
                        <div class="flex items-end gap-2">
                            <Button type="submit">Filtrer</Button>
                            <Button type="button" variant="outline" @click="resetFilters">Réinitialiser</Button>
                        </div>
                    </form>
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
                        title="Aucun appel trouvé"
                        description="Ajuste les filtres ou attends les prochaines interactions pour retrouver l’historique détaillé."
                        action-label="Ouvrir les numéros"
                        :action-href="route('dashboard.numbers')"
                    />
                    <div v-else class="space-y-4">
                        <div class="overflow-hidden rounded-3xl border border-border/60">
                            <div
                                class="hidden grid-cols-[1.2fr_0.8fr_0.8fr_0.8fr_1.3fr_0.7fr] gap-4 border-b border-border/60 bg-muted/30 px-6 py-4 text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground md:grid"
                            >
                                <div>Appelant</div>
                                <div>Statut</div>
                                <div>Début</div>
                                <div>Durée</div>
                                <div>Résumé</div>
                                <div>Action</div>
                            </div>
                            <div class="divide-y divide-border/60">
                                <div
                                    v-for="call in calls"
                                    :key="call.id"
                                    class="grid gap-4 px-6 py-5 md:grid-cols-[1.2fr_0.8fr_0.8fr_0.8fr_1.3fr_0.7fr] md:items-start"
                                >
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-medium">{{ call.from_number ?? 'Numéro inconnu' }}</p>
                                            <ToneBadge :label="call.direction ?? 'inbound'" tone="neutral" />
                                        </div>
                                        <p class="text-sm text-muted-foreground">Vers {{ call.to_number ?? 'numéro principal' }}</p>
                                        <p class="text-xs text-muted-foreground">{{ call.phone_label ?? 'Ligne standard' }}</p>
                                    </div>
                                    <div class="space-y-2">
                                        <ToneBadge :label="call.status_label" :tone="call.tone" />
                                        <ToneBadge
                                            v-if="call.channel === 'conversation_ai' && call.channel_label"
                                            :label="call.channel_label"
                                            tone="neutral"
                                        />
                                        <ToneBadge
                                            v-if="call.channel === 'conversation_ai' && call.resolution_label"
                                            :label="call.resolution_label"
                                            :tone="call.tone"
                                        />
                                        <ToneBadge
                                            v-if="call.message?.workflow_status_label"
                                            :label="call.message.workflow_status_label"
                                            :tone="call.message.workflow_status_tone ?? 'neutral'"
                                        />
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ call.started_at ? new Date(call.started_at).toLocaleString('fr-BE') : 'n/a' }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ call.duration_seconds !== null ? `${call.duration_seconds}s` : 'n/a' }}
                                    </div>
                                    <div class="space-y-2 text-sm text-muted-foreground">
                                        <p>{{ call.summary ?? 'Aucun résumé disponible.' }}</p>
                                        <p v-if="call.escalation_reason" class="text-xs text-amber-700">
                                            Escalade: {{ call.escalation_reason }}
                                        </p>
                                        <p
                                            v-if="call.transfer_failure_status && call.fallback_target === 'voicemail'"
                                            class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"
                                        >
                                            Échec de transfert: {{ call.transfer_failure_status }}. Bascule vers messagerie.
                                        </p>
                                        <p v-if="call.message" class="rounded-2xl bg-muted/30 px-3 py-2 text-xs">
                                            Message: {{ call.message.message_text ?? 'Enregistrement vocal disponible.' }}
                                        </p>
                                    </div>
                                    <div>
                                        <Button as-child variant="outline" size="sm">
                                            <Link :href="route('dashboard.calls.show', call.id)">Voir la fiche</Link>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-3 rounded-2xl border border-border/60 bg-background px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between"
                        >
                            <p>{{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} sur {{ pagination.total }} appels</p>
                            <div class="flex gap-2">
                                <Button v-if="pagination.previous_page_url" as-child variant="outline" size="sm">
                                    <Link :href="pagination.previous_page_url">Précédent</Link>
                                </Button>
                                <Button v-if="pagination.next_page_url" as-child variant="outline" size="sm">
                                    <Link :href="pagination.next_page_url">Suivant</Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

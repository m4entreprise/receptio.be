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
import { type SharedData } from '@/types';
import type {
    AssigneeOption,
    AppliedFilters,
    InboxMessageItem,
    PaginationData,
    SelectOption,
    ServiceStatus,
    TenantSummary,
    WorkspaceSummary,
} from '@/types/backoffice';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive, ref, watchEffect } from 'vue';

const breadcrumbs = [
    { title: 'Vue d’ensemble', href: '/dashboard' },
    { title: 'Messages', href: '/dashboard/messages' },
];

interface Props {
    tenant: TenantSummary | null;
    summary: WorkspaceSummary;
    serviceStatus: ServiceStatus;
    pageMeta: {
        title: string;
        description: string;
    };
    messages: InboxMessageItem[];
    inboxStats: Array<{
        label: string;
        value: number | string;
        tone: 'default' | 'success' | 'warning' | 'info' | 'neutral';
    }>;
    filterOptions: {
        statuses: SelectOption[];
    };
    appliedFilters: AppliedFilters;
    serviceRules: string[];
    assignees: AssigneeOption[];
    pagination: PaginationData;
}

const props = defineProps<Props>();
const page = usePage<SharedData>();
const flashSuccess = page.props.flash?.success;

const filters = reactive({
    status: props.appliedFilters.status,
    search: props.appliedFilters.search,
    date_from: props.appliedFilters.date_from,
    date_to: props.appliedFilters.date_to,
});

const updatingId = ref<number | null>(null);
const workflowDrafts = reactive(
    Object.fromEntries(
        props.messages.map((message) => [
            message.id,
            {
                assigned_to_user_id: message.assigned_to_user_id ? String(message.assigned_to_user_id) : '',
                callback_due_at: message.callback_due_at ? message.callback_due_at.slice(0, 16) : '',
            },
        ]),
    ) as Record<number, { assigned_to_user_id: string; callback_due_at: string }>,
);

watchEffect(() => {
    props.messages.forEach((message) => {
        if (!workflowDrafts[message.id]) {
            workflowDrafts[message.id] = {
                assigned_to_user_id: message.assigned_to_user_id ? String(message.assigned_to_user_id) : '',
                callback_due_at: message.callback_due_at ? message.callback_due_at.slice(0, 16) : '',
            };
        }
    });
});

const applyFilters = () => {
    router.get(route('dashboard.messages'), filters, {
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

const updateStatus = (messageId: number, status: string) => {
    updatingId.value = messageId;

    const draft = workflowDrafts[messageId] ?? {
        assigned_to_user_id: '',
        callback_due_at: '',
    };

    router.patch(
        route('dashboard.messages.update', messageId),
        {
            status,
            assigned_to_user_id: draft.assigned_to_user_id ? Number(draft.assigned_to_user_id) : null,
            callback_due_at: status === 'in_progress' && draft.callback_due_at ? draft.callback_due_at : null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                updatingId.value = null;
            },
        },
    );
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
                action-label="Voir les appels"
                :action-href="route('dashboard.calls')"
            />

            <div v-if="flashSuccess" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div v-for="stat in inboxStats" :key="stat.label">
                    <MetricCard :title="stat.label" :value="stat.value" :description="tenant?.name ?? 'Organisation active'" />
                </div>
            </div>

            <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                <CardHeader>
                    <CardDescription>Filtres</CardDescription>
                    <CardTitle>Inbox messages exploitable</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-4 lg:grid-cols-[1fr_1fr_0.8fr_0.8fr_auto]" @submit.prevent="applyFilters">
                        <div class="grid gap-2">
                            <Label for="search">Recherche</Label>
                            <Input id="search" v-model="filters.search" placeholder="+32..., nom, contenu..." />
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

            <div class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>Boîte de réception</CardDescription>
                        <CardTitle>Demandes de rappel et messages vocaux</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EmptyStateCard
                            v-if="messages.length === 0"
                            title="Aucun message à afficher"
                            description="Ajuste les filtres ou attends les prochains messages pour recharger l’inbox."
                            action-label="Ouvrir le routage"
                            :action-href="route('dashboard.numbers')"
                        />
                        <div v-else class="space-y-4">
                            <div
                                v-for="message in messages"
                                :key="message.id"
                                class="rounded-[1.75rem] border border-border/60 bg-background p-5 shadow-[0_12px_36px_-30px_rgba(15,23,42,0.35)]"
                            >
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-base font-medium">{{ message.caller }}</p>
                                            <ToneBadge :label="message.priority" :tone="message.priority === 'Élevée' ? 'warning' : 'neutral'" />
                                            <ToneBadge :label="message.status_label" :tone="message.status_tone" />
                                            <ToneBadge v-if="message.call_status_label" :label="message.call_status_label" tone="info" />
                                        </div>
                                        <p class="text-sm text-muted-foreground">{{ message.phone }}</p>
                                    </div>
                                    <div class="space-y-1 text-right text-sm text-muted-foreground">
                                        <p>{{ message.created_at ? new Date(message.created_at).toLocaleString('fr-BE') : 'Date inconnue' }}</p>
                                        <p v-if="message.recording_duration !== null">{{ message.recording_duration }}s d’enregistrement</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 lg:grid-cols-[1.2fr_0.8fr]">
                                    <div class="space-y-3 rounded-2xl border border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
                                        <p class="leading-6">{{ message.excerpt }}</p>
                                        <audio v-if="message.recording_url" :src="message.recording_url" controls class="w-full" preload="none" />
                                        <p v-else class="text-xs">Aucun enregistrement audio disponible.</p>
                                    </div>
                                    <div class="space-y-3 rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                                        <div>
                                            <p class="font-medium text-foreground">Résumé d’exploitation</p>
                                            <p class="mt-2 leading-6">{{ message.summary ?? 'Le message n’a pas encore de résumé enrichi.' }}</p>
                                        </div>
                                        <div class="space-y-1 text-xs">
                                            <p>Assigné à: {{ message.assigned_to_name ?? 'Personne' }}</p>
                                            <p>Traité par: {{ message.handled_by_name ?? 'Pas encore traité' }}</p>
                                            <p v-if="message.handled_at">Traité le: {{ new Date(message.handled_at).toLocaleString('fr-BE') }}</p>
                                            <p v-if="message.callback_due_at">Rappel prevu: {{ new Date(message.callback_due_at).toLocaleString('fr-BE') }}</p>
                                        </div>
                                        <div class="grid gap-3 rounded-2xl border border-border/60 bg-muted/20 p-3">
                                            <div class="grid gap-2">
                                                <Label :for="`assignee-${message.id}`">Assigner a</Label>
                                                <select
                                                    :id="`assignee-${message.id}`"
                                                    v-model="workflowDrafts[message.id].assigned_to_user_id"
                                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                                                >
                                                    <option value="">Personne</option>
                                                    <option v-for="assignee in assignees" :key="assignee.id" :value="String(assignee.id)">
                                                        {{ assignee.name }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="grid gap-2">
                                                <Label :for="`callback-${message.id}`">Rappeler plus tard</Label>
                                                <Input :id="`callback-${message.id}`" v-model="workflowDrafts[message.id].callback_due_at" type="datetime-local" />
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                :disabled="updatingId === message.id || message.status === 'in_progress'"
                                                @click="updateStatus(message.id, 'in_progress')"
                                            >
                                                Prendre en charge
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                :disabled="updatingId === message.id"
                                                @click="updateStatus(message.id, 'in_progress')"
                                            >
                                                Planifier rappel
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                :disabled="updatingId === message.id || message.status === 'called_back'"
                                                @click="updateStatus(message.id, 'called_back')"
                                            >
                                                Marquer rappelé
                                            </Button>
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                :disabled="updatingId === message.id || message.status === 'closed'"
                                                @click="updateStatus(message.id, 'closed')"
                                            >
                                                Clôturer
                                            </Button>
                                            <Button as-child variant="ghost" size="sm">
                                                <Link :href="route('dashboard.calls.show', message.call_id)">Voir l’appel</Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-col gap-3 rounded-2xl border border-border/60 bg-background px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between"
                            >
                                <p>{{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} sur {{ pagination.total }} messages</p>
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

                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>Cadre opératoire</CardDescription>
                        <CardTitle>Règles de service</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="rule in serviceRules"
                            :key="rule"
                            class="rounded-2xl border border-border/60 px-4 py-4 text-sm leading-6 text-muted-foreground"
                        >
                            {{ rule }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

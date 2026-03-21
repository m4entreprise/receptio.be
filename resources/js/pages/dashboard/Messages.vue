<script setup lang="ts">
import BackofficePageHeader from '@/components/dashboard/BackofficePageHeader.vue';
import EmptyStateCard from '@/components/dashboard/EmptyStateCard.vue';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import ToneBadge from '@/components/dashboard/ToneBadge.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ServiceStatus, TenantSummary, WorkspaceSummary } from '@/types/backoffice';
import { Head } from '@inertiajs/vue3';

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
    messages: Array<{
        id: number;
        caller: string;
        phone: string;
        excerpt: string;
        recording_url: string | null;
        status: string;
        status_label: string;
        priority: string;
        created_at: string | null;
        summary: string | null;
    }>;
    inboxStats: Array<{
        label: string;
        value: number | string;
        tone: 'default' | 'success' | 'warning' | 'info' | 'neutral';
    }>;
    serviceRules: string[];
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
                action-label="Configurer les notifications"
                :action-href="route('dashboard.agent')"
            />

            <div class="grid gap-4 md:grid-cols-3">
                <div v-for="stat in inboxStats" :key="stat.label">
                    <MetricCard :title="stat.label" :value="stat.value" :description="tenant?.name ?? 'Organisation active'" />
                </div>
            </div>

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
                            description="Les messages, leurs priorités et leurs résumés apparaîtront ici dans une boîte de réception centralisée."
                            action-label="Ouvrir le routage"
                            :action-href="route('dashboard.numbers')"
                        />
                        <div v-else class="space-y-4">
                            <div v-for="message in messages" :key="message.id" class="rounded-[1.75rem] border border-border/60 bg-background p-5 shadow-[0_12px_36px_-30px_rgba(15,23,42,0.35)]">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-base font-medium">{{ message.caller }}</p>
                                            <ToneBadge :label="message.priority" :tone="message.priority === 'Élevée' ? 'warning' : 'neutral'" />
                                            <ToneBadge :label="message.status_label" tone="info" />
                                        </div>
                                        <p class="text-sm text-muted-foreground">{{ message.phone }}</p>
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ message.created_at ? new Date(message.created_at).toLocaleString('fr-BE') : 'Date inconnue' }}
                                    </p>
                                </div>
                                <div class="mt-4 grid gap-3 lg:grid-cols-[1.2fr_0.8fr]">
                                    <div class="rounded-2xl border border-border/60 bg-muted/20 p-4 text-sm leading-6 text-muted-foreground">
                                        {{ message.excerpt }}
                                    </div>
                                    <div class="rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                                        <p class="font-medium text-foreground">Résumé d’exploitation</p>
                                        <p class="mt-2 leading-6">{{ message.summary ?? 'Le message n’a pas encore de résumé enrichi.' }}</p>
                                        <a
                                            v-if="message.recording_url"
                                            :href="message.recording_url"
                                            target="_blank"
                                            class="mt-4 inline-flex text-sm font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            Écouter l’enregistrement
                                        </a>
                                    </div>
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
                        <div v-for="rule in serviceRules" :key="rule" class="rounded-2xl border border-border/60 px-4 py-4 text-sm leading-6 text-muted-foreground">
                            {{ rule }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

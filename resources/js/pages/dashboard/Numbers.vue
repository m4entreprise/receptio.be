<script setup lang="ts">
import BackofficePageHeader from '@/components/dashboard/BackofficePageHeader.vue';
import EmptyStateCard from '@/components/dashboard/EmptyStateCard.vue';
import ToneBadge from '@/components/dashboard/ToneBadge.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ServiceStatus, TenantSummary, WorkspaceSummary } from '@/types/backoffice';
import { Head } from '@inertiajs/vue3';

const breadcrumbs = [
    { title: 'Vue d’ensemble', href: '/dashboard' },
    { title: 'Numéros & routage', href: '/dashboard/numbers' },
];

interface Props {
    tenant: TenantSummary | null;
    summary: WorkspaceSummary;
    serviceStatus: ServiceStatus;
    pageMeta: {
        title: string;
        description: string;
    };
    numbers: Array<{
        id: number;
        label: string;
        phone_number: string;
        provider: string;
        status: string;
        tone: 'default' | 'success' | 'warning' | 'info' | 'neutral';
    }>;
    webhooks: {
        incoming: string;
        menu: string;
        recording: string;
    };
    routingSteps: Array<{
        title: string;
        description: string;
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
                action-label="Ouvrir les intégrations"
                :action-href="route('dashboard.integrations')"
            />

            <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>Parc téléphonique</CardDescription>
                        <CardTitle>Numéros actifs</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EmptyStateCard
                            v-if="numbers.length === 0"
                            title="Aucun numéro relié"
                            description="Les lignes téléphoniques actives apparaissent ici avec leur état, leur provider et leur routage associé."
                            action-label="Ouvrir l’agent"
                            :action-href="route('dashboard.agent')"
                        />
                        <div v-else class="grid gap-4 md:grid-cols-2">
                            <div v-for="number in numbers" :key="number.id" class="rounded-[1.75rem] border border-border/60 bg-background p-5 shadow-[0_12px_36px_-30px_rgba(15,23,42,0.35)]">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium">{{ number.label }}</p>
                                    <ToneBadge :label="number.status" :tone="number.tone" />
                                </div>
                                <p class="mt-3 text-2xl font-semibold tracking-tight">{{ number.phone_number }}</p>
                                <p class="mt-2 text-sm text-muted-foreground">Provider : {{ number.provider }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>État du flux</CardDescription>
                        <CardTitle>Résumé de routage</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="rounded-[1.5rem] border border-border/60 bg-muted/20 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Horaires</p>
                            <p class="mt-2 text-lg font-semibold">{{ summary.open_hours }}</p>
                        </div>
                        <div class="rounded-[1.5rem] border border-border/60 bg-muted/20 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Transfert humain</p>
                            <p class="mt-2 text-lg font-semibold">{{ summary.transfer_enabled ? 'Activé' : 'Non renseigné' }}</p>
                        </div>
                        <div class="rounded-[1.5rem] border border-border/60 bg-muted/20 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Notifications</p>
                            <p class="mt-2 text-lg font-semibold">{{ summary.notification_ready ? 'Prêtes' : 'Non renseignées' }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>Endpoints</CardDescription>
                        <CardTitle>Webhooks Twilio</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="rounded-[1.5rem] border border-border/60 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Incoming</p>
                            <p class="mt-2 break-all font-mono text-sm">{{ webhooks.incoming }}</p>
                        </div>
                        <div class="rounded-[1.5rem] border border-border/60 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Menu</p>
                            <p class="mt-2 break-all font-mono text-sm">{{ webhooks.menu }}</p>
                        </div>
                        <div class="rounded-[1.5rem] border border-border/60 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Recording</p>
                            <p class="mt-2 break-all font-mono text-sm">{{ webhooks.recording }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>Logique de distribution</CardDescription>
                        <CardTitle>Étapes de routage</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-for="step in routingSteps" :key="step.title" class="rounded-[1.5rem] border border-border/60 p-4">
                            <p class="text-sm font-medium">{{ step.title }}</p>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ step.description }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

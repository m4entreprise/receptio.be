<script setup lang="ts">
import BackofficePageHeader from '@/components/dashboard/BackofficePageHeader.vue';
import ToneBadge from '@/components/dashboard/ToneBadge.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { IntegrationItem, ServiceStatus, TenantSummary, WorkspaceSummary } from '@/types/backoffice';
import { Head } from '@inertiajs/vue3';

const breadcrumbs = [
    { title: 'Vue d’ensemble', href: '/dashboard' },
    { title: 'Intégrations', href: '/dashboard/integrations' },
];

interface Props {
    tenant: TenantSummary | null;
    summary: WorkspaceSummary;
    serviceStatus: ServiceStatus;
    pageMeta: {
        title: string;
        description: string;
    };
    integrations: IntegrationItem[];
    deploymentChecklist: Array<{
        label: string;
        done: boolean;
    }>;
    healthFeed: Array<{
        title: string;
        description: string;
        tone: 'default' | 'success' | 'warning' | 'info' | 'neutral';
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
                action-label="Voir les numéros"
                :action-href="route('dashboard.numbers')"
            />

            <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>Connecteurs</CardDescription>
                        <CardTitle>Panneau d’intégrations</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2">
                        <div
                            v-for="integration in integrations"
                            :key="integration.name"
                            class="rounded-[1.75rem] border border-border/60 bg-background p-5 shadow-[0_12px_36px_-30px_rgba(15,23,42,0.35)]"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium">{{ integration.name }}</p>
                                <ToneBadge :label="integration.status" :tone="integration.tone" />
                            </div>
                            <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ integration.description }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>Conformité opérationnelle</CardDescription>
                        <CardTitle>Points de contrôle</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="item in deploymentChecklist"
                            :key="item.label"
                            class="flex items-center justify-between rounded-[1.5rem] border border-border/60 px-4 py-3"
                        >
                            <span class="text-sm font-medium">{{ item.label }}</span>
                            <ToneBadge :label="item.done ? 'OK' : 'À faire'" :tone="item.done ? 'success' : 'warning'" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                <CardHeader>
                    <CardDescription>État de santé</CardDescription>
                    <CardTitle>Lecture de service</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-3">
                    <div
                        v-for="item in healthFeed"
                        :key="item.title"
                        class="rounded-[1.75rem] border border-border/60 bg-background p-5 shadow-[0_12px_36px_-30px_rgba(15,23,42,0.35)]"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium">{{ item.title }}</p>
                            <ToneBadge
                                :label="item.tone === 'success' ? 'Stable' : item.tone === 'warning' ? 'Attention' : 'Info'"
                                :tone="item.tone"
                            />
                        </div>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ item.description }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import BackofficePageHeader from '@/components/dashboard/BackofficePageHeader.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ServiceStatus, TenantSummary, WorkspaceSummary } from '@/types/backoffice';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';

const breadcrumbs = [
    { title: 'Vue d’ensemble', href: '/dashboard' },
    { title: 'Paramètres', href: '/dashboard/workspace' },
];

interface Props {
    tenant: TenantSummary | null;
    summary: WorkspaceSummary;
    serviceStatus: ServiceStatus;
    pageMeta: {
        title: string;
        description: string;
    };
    workspacePanels: Array<{
        title: string;
        items: Array<{
            label: string;
            value: string;
        }>;
    }>;
    accountLinks: Array<{
        label: string;
        href: string;
    }>;
    governance: string[];
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
                action-label="Modifier l’agent"
                :action-href="route('dashboard.agent')"
            />

            <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="space-y-6">
                    <Card
                        v-for="panel in workspacePanels"
                        :key="panel.title"
                        class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]"
                    >
                        <CardHeader>
                            <CardDescription>Organisation</CardDescription>
                            <CardTitle>{{ panel.title }}</CardTitle>
                        </CardHeader>
                        <CardContent class="grid gap-3 md:grid-cols-2">
                            <div v-for="item in panel.items" :key="item.label" class="rounded-2xl border border-border/60 p-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">{{ item.label }}</p>
                                <p class="mt-2 text-base font-medium text-foreground">{{ item.value }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                        <CardHeader>
                            <CardDescription>Compte & préférences</CardDescription>
                            <CardTitle>Accès rapides</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Link
                                v-for="link in accountLinks"
                                :key="link.label"
                                :href="link.href"
                                class="flex items-center justify-between rounded-2xl border border-border/60 px-4 py-4 transition hover:border-primary/30 hover:bg-primary/5"
                            >
                                <span class="text-sm font-medium">{{ link.label }}</span>
                                <ArrowRight class="size-4 text-muted-foreground" />
                            </Link>
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                        <CardHeader>
                            <CardDescription>Cadre de gouvernance</CardDescription>
                            <CardTitle>Principes de service</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="rule in governance"
                                :key="rule"
                                class="rounded-2xl border border-border/60 px-4 py-4 text-sm leading-6 text-muted-foreground"
                            >
                                {{ rule }}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

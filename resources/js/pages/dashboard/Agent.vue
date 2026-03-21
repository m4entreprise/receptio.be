<script setup lang="ts">
import BackofficePageHeader from '@/components/dashboard/BackofficePageHeader.vue';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import ToneBadge from '@/components/dashboard/ToneBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type SharedData } from '@/types';
import type { ServiceStatus, SettingsFormData, TenantSummary, WorkspaceSummary } from '@/types/backoffice';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const breadcrumbs = [
    { title: 'Vue d’ensemble', href: '/dashboard' },
    { title: 'Agent', href: '/dashboard/agent' },
];

interface Props {
    tenant: TenantSummary | null;
    summary: WorkspaceSummary;
    serviceStatus: ServiceStatus;
    pageMeta: {
        title: string;
        description: string;
    };
    settings: SettingsFormData;
    readiness: Array<{
        label: string;
        value: string;
        description: string;
    }>;
    capabilities: Array<{
        title: string;
        items: string[];
    }>;
}

const props = defineProps<Props>();
const page = usePage<SharedData>();
const flashSuccess = page.props.flash?.success;

const businessDays = [
    { label: 'Lun', value: 'monday' },
    { label: 'Mar', value: 'tuesday' },
    { label: 'Mer', value: 'wednesday' },
    { label: 'Jeu', value: 'thursday' },
    { label: 'Ven', value: 'friday' },
    { label: 'Sam', value: 'saturday' },
    { label: 'Dim', value: 'sunday' },
];

const form = useForm({
    tenant_name: props.tenant?.name ?? 'Receptio',
    agent_name: props.settings.agent_name,
    welcome_message: props.settings.welcome_message,
    after_hours_message: props.settings.after_hours_message,
    faq_content: props.settings.faq_content,
    transfer_phone_number: props.settings.transfer_phone_number,
    notification_email: props.settings.notification_email,
    opens_at: props.settings.opens_at ?? '',
    closes_at: props.settings.closes_at ?? '',
    phone_number: props.settings.phone_number,
    business_days: props.settings.business_days,
});

const submit = () => {
    form.put(route('dashboard.settings.update'), {
        preserveScroll: true,
    });
};

const presets = [
    { label: 'Ton conversationnel', value: 'Sobre et rassurant', tone: 'default' as const },
    { label: 'Langue principale', value: props.tenant?.locale ?? 'fr-BE', tone: 'neutral' as const },
    { label: 'Escalade humaine', value: props.summary.transfer_enabled ? 'Active' : 'Non renseignée', tone: props.summary.transfer_enabled ? 'success' as const : 'warning' as const },
    { label: 'Notification', value: props.summary.notification_ready ? 'Active' : 'Non renseignée', tone: props.summary.notification_ready ? 'success' as const : 'warning' as const },
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
                action-label="Voir les intégrations"
                :action-href="route('dashboard.integrations')"
            />

            <div v-if="flashSuccess" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <MetricCard v-for="item in readiness" :key="item.label" :title="item.label" :value="item.value" :description="item.description" />
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>Configuration éditable</CardDescription>
                        <CardTitle>Identité et comportement de l’agent</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-6" @submit.prevent="submit">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="tenant_name">Entreprise</Label>
                                    <Input id="tenant_name" v-model="form.tenant_name" placeholder="Nom de l’entreprise" />
                                    <InputError :message="form.errors.tenant_name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="agent_name">Nom de l’agent</Label>
                                    <Input id="agent_name" v-model="form.agent_name" placeholder="Nom affiché" />
                                    <InputError :message="form.errors.agent_name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="phone_number">Numéro principal</Label>
                                    <Input id="phone_number" v-model="form.phone_number" placeholder="Numéro principal" />
                                    <InputError :message="form.errors.phone_number" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="transfer_phone_number">Numéro de transfert</Label>
                                    <Input id="transfer_phone_number" v-model="form.transfer_phone_number" placeholder="Ligne de reprise" />
                                    <InputError :message="form.errors.transfer_phone_number" />
                                </div>
                                <div class="grid gap-2 md:col-span-2">
                                    <Label for="notification_email">Email de notification</Label>
                                    <Input id="notification_email" type="email" v-model="form.notification_email" placeholder="reception@entreprise.be" />
                                    <InputError :message="form.errors.notification_email" />
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
                                <div class="grid gap-2">
                                    <Label for="welcome_message">Message d’accueil</Label>
                                    <textarea
                                        id="welcome_message"
                                        v-model="form.welcome_message"
                                        class="min-h-32 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                                        placeholder="Bonjour, vous êtes bien chez Receptio. Comment puis-je vous aider ?"
                                    ></textarea>
                                    <InputError :message="form.errors.welcome_message" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="after_hours_message">Message hors horaires</Label>
                                    <textarea
                                        id="after_hours_message"
                                        v-model="form.after_hours_message"
                                        class="min-h-32 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                                        placeholder="Nous sommes actuellement indisponibles. Merci de laisser votre message après le signal sonore."
                                    ></textarea>
                                    <InputError :message="form.errors.after_hours_message" />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="faq_content">Base de réponses / FAQ</Label>
                                <textarea
                                    id="faq_content"
                                    v-model="form.faq_content"
                                    class="min-h-44 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                                    placeholder="Horaires, adresse, accès, urgences, informations utiles..."
                                ></textarea>
                                <InputError :message="form.errors.faq_content" />
                            </div>

                            <div class="grid gap-4 lg:grid-cols-[0.8fr_1.2fr]">
                                <div class="grid gap-4 rounded-[1.75rem] border border-border/60 bg-muted/15 p-5">
                                    <div class="grid gap-2">
                                        <Label for="opens_at">Ouverture</Label>
                                        <Input id="opens_at" type="time" v-model="form.opens_at" />
                                        <InputError :message="form.errors.opens_at" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="closes_at">Fermeture</Label>
                                        <Input id="closes_at" type="time" v-model="form.closes_at" />
                                        <InputError :message="form.errors.closes_at" />
                                    </div>
                                </div>
                                <div class="grid gap-2 rounded-[1.75rem] border border-border/60 bg-muted/15 p-5">
                                    <Label>Jours ouvrés</Label>
                                    <div class="flex flex-wrap gap-3">
                                        <label v-for="day in businessDays" :key="day.value" class="flex items-center gap-2 rounded-full border border-border/60 px-3 py-2 text-sm">
                                            <input v-model="form.business_days" :value="day.value" type="checkbox" class="rounded border-neutral-300" />
                                            <span>{{ day.label }}</span>
                                        </label>
                                    </div>
                                    <InputError :message="form.errors.business_days" />
                                </div>
                            </div>

                            <Button :disabled="form.processing">Enregistrer la configuration</Button>
                        </form>
                    </CardContent>
                </Card>

                <div class="space-y-6">
                    <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                        <CardHeader>
                            <CardDescription>Cadre actuel</CardDescription>
                            <CardTitle>Présets opérationnels</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="preset in presets" :key="preset.label" class="rounded-[1.5rem] border border-border/60 bg-background p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium">{{ preset.label }}</p>
                                    <ToneBadge :label="preset.value" :tone="preset.tone" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                        <CardHeader>
                            <CardDescription>Capacités</CardDescription>
                            <CardTitle>Cadre conversationnel</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-for="capability in capabilities" :key="capability.title" class="rounded-[1.5rem] border border-border/60 bg-background p-4">
                                <p class="text-sm font-medium">{{ capability.title }}</p>
                                <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
                                    <li v-for="item in capability.items" :key="item">{{ item }}</li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

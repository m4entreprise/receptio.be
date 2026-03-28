<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface DashboardProps {
    tenant: {
        id: number;
        name: string;
        slug: string;
        locale: string;
        timezone: string;
    } | null;
    stats: {
        total_calls: number;
        missed_calls: number;
        open_hours: string;
        transfer_enabled: boolean;
    };
    settings: {
        agent_name: string;
        welcome_message: string;
        after_hours_message: string;
        faq_content: string;
        transfer_phone_number: string;
        notification_email: string;
        opens_at: string | null;
        closes_at: string | null;
        business_days: string[];
        phone_number: string;
    };
    recentCalls: Array<{
        id: number;
        status: string;
        from_number: string | null;
        to_number: string | null;
        started_at: string | null;
        summary: string | null;
        message: {
            caller_name: string | null;
            caller_number: string | null;
            message_text: string | null;
            recording_url: string | null;
        } | null;
    }>;
    webhooks: {
        incoming: string;
    };
}

const props = defineProps<DashboardProps>();
const { recentCalls, settings, stats, tenant, webhooks } = props;
const page = usePage<SharedData & { flash?: { success?: string } }>();
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
    tenant_name: tenant?.name ?? 'Receptio',
    agent_name: settings.agent_name,
    welcome_message: settings.welcome_message,
    after_hours_message: settings.after_hours_message,
    faq_content: settings.faq_content,
    transfer_phone_number: settings.transfer_phone_number,
    notification_email: settings.notification_email,
    opens_at: settings.opens_at ?? '',
    closes_at: settings.closes_at ?? '',
    phone_number: settings.phone_number,
    business_days: settings.business_days,
});

const submit = () => {
    form.put(route('dashboard.settings.update'), {
        preserveScroll: true,
    });
};

const flashSuccess = page.props.flash?.success;
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 rounded-xl p-4">
            <div v-if="flashSuccess" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Appels totaux</CardDescription>
                        <CardTitle class="text-3xl">{{ stats.total_calls }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Messages / appels manqués</CardDescription>
                        <CardTitle class="text-3xl">{{ stats.missed_calls }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Horaires</CardDescription>
                        <CardTitle class="text-lg">{{ stats.open_hours }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Transfert humain</CardDescription>
                        <CardTitle class="text-lg">{{ stats.transfer_enabled ? 'Activé' : 'Désactivé' }}</CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <div class="grid gap-6 xl:grid-cols-[2fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Configuration de l’agent</CardTitle>
                        <CardDescription>Paramètre ton accueil, les horaires, le transfert humain et l’email de notification.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-6" @submit.prevent="submit">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="tenant_name">Entreprise</Label>
                                    <Input id="tenant_name" v-model="form.tenant_name" placeholder="Nom de la PME" />
                                    <InputError :message="form.errors.tenant_name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="agent_name">Nom de l’agent</Label>
                                    <Input id="agent_name" v-model="form.agent_name" placeholder="Receptio" />
                                    <InputError :message="form.errors.agent_name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="phone_number">Numéro Twilio</Label>
                                    <Input id="phone_number" v-model="form.phone_number" placeholder="+32..." />
                                    <InputError :message="form.errors.phone_number" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="transfer_phone_number">Numéro de transfert</Label>
                                    <Input id="transfer_phone_number" v-model="form.transfer_phone_number" placeholder="+32..." />
                                    <InputError :message="form.errors.transfer_phone_number" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="notification_email">Email de notification</Label>
                                    <Input
                                        id="notification_email"
                                        type="email"
                                        v-model="form.notification_email"
                                        placeholder="contact@entreprise.be"
                                    />
                                    <InputError :message="form.errors.notification_email" />
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
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
                            </div>

                            <div class="grid gap-2">
                                <Label>Jours ouvrés</Label>
                                <div class="flex flex-wrap gap-3 rounded-xl border p-4">
                                    <label v-for="day in businessDays" :key="day.value" class="flex items-center gap-2 text-sm">
                                        <input v-model="form.business_days" :value="day.value" type="checkbox" class="rounded border-neutral-300" />
                                        <span>{{ day.label }}</span>
                                    </label>
                                </div>
                                <InputError :message="form.errors.business_days" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="welcome_message">Message d’accueil</Label>
                                <textarea
                                    id="welcome_message"
                                    v-model="form.welcome_message"
                                    class="min-h-28 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                                    placeholder="Bonjour, vous êtes bien chez ..."
                                ></textarea>
                                <InputError :message="form.errors.welcome_message" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="after_hours_message">Message hors horaires</Label>
                                <textarea
                                    id="after_hours_message"
                                    v-model="form.after_hours_message"
                                    class="min-h-28 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                                    placeholder="Nous sommes actuellement indisponibles..."
                                ></textarea>
                                <InputError :message="form.errors.after_hours_message" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="faq_content">FAQ rapide</Label>
                                <textarea
                                    id="faq_content"
                                    v-model="form.faq_content"
                                    class="min-h-40 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                                    placeholder="Horaires, adresse, parking, délais, urgences..."
                                ></textarea>
                                <InputError :message="form.errors.faq_content" />
                            </div>

                            <Button :disabled="form.processing">Enregistrer la configuration</Button>
                        </form>
                    </CardContent>
                </Card>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Canal entrant</CardTitle>
                            <CardDescription>Utilise cette URL pour relier le numéro entrant au standard.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div class="break-all rounded-lg border bg-muted/40 p-3 font-mono">
                                {{ webhooks.incoming }}
                            </div>
                            <p class="text-muted-foreground">
                                Ce point d’entrée alimente l’accueil, le routage, l’historique d’appels et la boîte de messages.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Entreprise active</CardTitle>
                            <CardDescription>Contexte chargé dans le dashboard.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div><span class="font-medium">Nom :</span> {{ tenant?.name ?? 'Aucun tenant configuré' }}</div>
                            <div><span class="font-medium">Slug :</span> {{ tenant?.slug ?? 'n/a' }}</div>
                            <div><span class="font-medium">Locale :</span> {{ tenant?.locale ?? 'fr-BE' }}</div>
                            <div><span class="font-medium">Timezone :</span> {{ tenant?.timezone ?? 'Europe/Brussels' }}</div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Historique récent des appels</CardTitle>
                    <CardDescription>Vue rapide des derniers appels et messages reçus.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="recentCalls.length === 0" class="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                        Aucun appel enregistré pour le moment.
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="call in recentCalls" :key="call.id" class="rounded-xl border p-4">
                            <div class="flex flex-col justify-between gap-2 md:flex-row md:items-start">
                                <div class="space-y-1">
                                    <div class="text-sm font-medium">
                                        {{ call.from_number ?? 'Numéro inconnu' }} -> {{ call.to_number ?? 'Numéro principal' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ call.started_at ? new Date(call.started_at).toLocaleString('fr-BE') : 'Date inconnue' }}
                                    </div>
                                </div>
                                <div class="rounded-full bg-muted px-3 py-1 text-xs font-medium uppercase tracking-wide">
                                    {{ call.status }}
                                </div>
                            </div>
                            <div class="mt-3 text-sm text-muted-foreground">{{ call.summary ?? 'Aucun résumé disponible.' }}</div>
                            <div v-if="call.message" class="mt-3 rounded-lg bg-muted/40 p-3 text-sm">
                                <div>
                                    <span class="font-medium">Appelant :</span>
                                    {{ call.message.caller_name ?? call.message.caller_number ?? 'Inconnu' }}
                                </div>
                                <div><span class="font-medium">Message :</span> {{ call.message.message_text ?? 'Message vocal enregistré.' }}</div>
                                <a
                                    v-if="call.message.recording_url"
                                    :href="call.message.recording_url"
                                    target="_blank"
                                    class="mt-2 inline-flex text-sm font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    Écouter l’enregistrement
                                </a>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

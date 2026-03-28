<script setup lang="ts">
import BackofficePageHeader from '@/components/dashboard/BackofficePageHeader.vue';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import ToneBadge from '@/components/dashboard/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ActivityItem, CallDetailItem, ServiceStatus, TenantSummary, WorkspaceSummary } from '@/types/backoffice';
import { Head, Link } from '@inertiajs/vue3';

const breadcrumbs = [
    { title: 'Vue d’ensemble', href: '/dashboard' },
    { title: 'Appels', href: '/dashboard/calls' },
    { title: 'Fiche appel', href: '#' },
];

interface Props {
    tenant: TenantSummary | null;
    summary: WorkspaceSummary;
    serviceStatus: ServiceStatus;
    pageMeta: {
        title: string;
        description: string;
    };
    call: CallDetailItem;
    activityFeed: ActivityItem[];
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
                :badge-label="call.status_label"
                :badge-tone="call.tone"
                action-label="Retour aux appels"
                :action-href="route('dashboard.calls')"
            />

            <div class="grid gap-4 md:grid-cols-3">
                <MetricCard title="Appelant" :value="call.from_number ?? 'Inconnu'" :description="call.tenant_name" />
                <MetricCard
                    title="Durée"
                    :value="call.duration_seconds !== null ? `${call.duration_seconds}s` : 'n/a'"
                    :description="call.phone_label ?? 'Ligne standard'"
                />
                <MetricCard title="CallSid" :value="call.external_sid ?? 'n/a'" description="Identifiant Twilio de référence" />
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>Contexte de l’appel</CardDescription>
                        <CardTitle>Données opérationnelles</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                                <p class="font-medium text-foreground">Trajet</p>
                                <p class="mt-2">{{ call.from_number ?? 'Numéro inconnu' }} → {{ call.to_number ?? 'Numéro principal' }}</p>
                                <p class="mt-1">Direction: {{ call.direction ?? 'inbound' }}</p>
                            </div>
                            <div class="rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                                <p class="font-medium text-foreground">Horodatage</p>
                                <p class="mt-2">Début: {{ call.started_at ? new Date(call.started_at).toLocaleString('fr-BE') : 'n/a' }}</p>
                                <p class="mt-1">Fin: {{ call.ended_at ? new Date(call.ended_at).toLocaleString('fr-BE') : 'n/a' }}</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                            <p class="font-medium text-foreground">Résumé</p>
                            <p class="mt-2 leading-6">{{ call.summary ?? 'Aucun résumé disponible.' }}</p>
                            <p
                                v-if="call.transfer_failure_status && call.fallback_target === 'voicemail'"
                                class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"
                            >
                                Échec de transfert: {{ call.transfer_failure_status }}. Bascule vers messagerie.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                            <p class="font-medium text-foreground">Derniers événements Twilio</p>
                            <div v-if="call.recent_status_events.length > 0" class="mt-3 space-y-2">
                                <div
                                    v-for="(event, index) in call.recent_status_events"
                                    :key="`${call.id}-${index}`"
                                    class="rounded-xl bg-muted/20 px-3 py-3"
                                >
                                    <p>{{ event.received_at ? new Date(event.received_at).toLocaleString('fr-BE') : 'Horodatage inconnu' }}</p>
                                    <p class="mt-1">Call: {{ event.call_status ?? 'n/a' }}</p>
                                    <p v-if="event.dial_call_status" class="mt-1">Dial: {{ event.dial_call_status }}</p>
                                    <p v-if="event.callback_source" class="mt-1">Source: {{ event.callback_source }}</p>
                                </div>
                            </div>
                            <p v-else class="mt-3">Aucun événement de statut enregistré.</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                    <CardHeader>
                        <CardDescription>Message vocal</CardDescription>
                        <CardTitle>Suivi lié à l’appel</CardTitle>
                    </CardHeader>
                    <CardContent v-if="call.message" class="space-y-4">
                        <div class="flex flex-wrap gap-2">
                            <ToneBadge
                                :label="call.message.workflow_status_label ?? 'Sans statut'"
                                :tone="call.message.workflow_status_tone ?? 'neutral'"
                            />
                            <ToneBadge
                                v-if="call.message.transcription_status_label"
                                :label="call.message.transcription_status_label"
                                :tone="call.message.transcription_status_tone ?? 'neutral'"
                            />
                            <ToneBadge
                                v-if="call.message.recording_duration !== null"
                                :label="`${call.message.recording_duration}s`"
                                tone="neutral"
                            />
                            <ToneBadge
                                v-if="call.message.urgency_level"
                                :label="`Urgence ${call.message.urgency_level}`"
                                :tone="call.message.urgency_level === 'high' ? 'warning' : call.message.urgency_level === 'medium' ? 'info' : 'neutral'"
                            />
                        </div>
                        <div class="rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                            <p class="font-medium text-foreground">Contact</p>
                            <p class="mt-2">{{ call.message.caller_name ?? 'Contact non identifié' }}</p>
                            <p class="mt-1">{{ call.message.caller_number ?? call.from_number ?? 'n/a' }}</p>
                        </div>
                        <div class="rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                            <p class="font-medium text-foreground">Contenu</p>
                            <p class="mt-2 leading-6">{{ call.message.message_text ?? 'Aucune transcription manuelle disponible.' }}</p>
                            <p class="mt-3 text-xs">Intent détectée: {{ call.message.ai_intent ?? 'Non définie' }}</p>
                            <p class="mt-1 text-xs">Provider transcription: {{ call.message.transcript_provider ?? 'Aucun' }}</p>
                        </div>
                        <div class="rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                            <p class="font-medium text-foreground">Résumé automatique</p>
                            <p class="mt-2 leading-6">{{ call.message.ai_summary ?? call.summary ?? 'Aucun résumé automatique disponible.' }}</p>
                        </div>
                        <audio v-if="call.message.recording_url" :src="call.message.recording_url" controls class="w-full" preload="none" />
                        <div class="rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                            <p>Assigné à: {{ call.message.assigned_to_name ?? 'Personne' }}</p>
                            <p class="mt-1">Traité par: {{ call.message.handled_by_name ?? 'Pas encore traité' }}</p>
                            <p v-if="call.message.handled_at" class="mt-1">
                                Traité le: {{ new Date(call.message.handled_at).toLocaleString('fr-BE') }}
                            </p>
                            <p v-if="call.message.callback_due_at" class="mt-1">
                                Rappel prevu le: {{ new Date(call.message.callback_due_at).toLocaleString('fr-BE') }}
                            </p>
                        </div>
                        <Button as-child variant="outline" size="sm">
                            <Link :href="route('dashboard.messages')">Ouvrir l’inbox</Link>
                        </Button>
                    </CardContent>
                    <CardContent v-else>
                        <p class="text-sm text-muted-foreground">Aucun message vocal n’est rattaché à cet appel.</p>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-border/70 bg-background/95 shadow-[0_18px_50px_-30px_rgba(15,23,42,0.32)]">
                <CardHeader>
                    <CardDescription>Journal</CardDescription>
                    <CardTitle>Activite liee a cet appel</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-if="activityFeed.length === 0" class="rounded-2xl border border-border/60 px-4 py-4 text-sm text-muted-foreground">
                        Aucun evenement metier rattache a cet appel.
                    </div>
                    <div v-for="item in activityFeed" :key="item.id" class="rounded-2xl border border-border/60 px-4 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-medium">{{ item.title }}</p>
                            <ToneBadge :label="item.tone === 'warning' ? 'Attention' : 'Journal'" :tone="item.tone" />
                        </div>
                        <p v-if="item.description" class="mt-2 text-sm leading-6 text-muted-foreground">{{ item.description }}</p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ item.happened_at ? new Date(item.happened_at).toLocaleString('fr-BE') : 'Horodatage inconnu' }}
                            <span v-if="item.user_name"> · {{ item.user_name }}</span>
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

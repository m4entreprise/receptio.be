<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarClock,
    CheckCircle2,
    ChefHat,
    ClipboardList,
    HeartHandshake,
    PhoneCall,
    ShieldCheck,
    Stethoscope,
} from 'lucide-vue-next';

const proofPoints = [
    'Accueil téléphonique professionnel et constant',
    'Messages qualifiés transmis sans friction',
    'Configuration simple des horaires, FAQ et transferts',
];

const featureBlocks = [
    {
        title: 'Ne laissez plus un appel important se perdre',
        description:
            'Receptio répond avec le bon ton, filtre les demandes courantes et remonte uniquement les appels qui nécessitent une intervention humaine.',
        icon: PhoneCall,
    },
    {
        title: 'Un cadre rassurant pour votre image de marque',
        description:
            'Scripts d’accueil, horaires, réponses fréquentes et transferts sont cadrés pour offrir une expérience cohérente à chaque appelant.',
        icon: ShieldCheck,
    },
    {
        title: 'Un suivi clair pour votre équipe',
        description: 'Chaque message, appel manqué ou demande urgente est centralisé pour accélérer la reprise et éviter les oublis.',
        icon: ClipboardList,
    },
];

const sectors = [
    {
        title: 'Restaurants',
        description:
            'Réduisez les interruptions en salle et en cuisine. Receptio capte les demandes de réservation, d’horaires, de groupe ou d’informations pratiques sans saturer l’équipe.',
        points: [
            'Moins d’allers-retours pendant le service',
            'Réponses rapides sur les horaires et accès',
            'Escalade des demandes sensibles au bon moment',
        ],
        icon: ChefHat,
    },
    {
        title: 'Professions libérales',
        description:
            'Cabinets médicaux, avocats, comptables ou consultants: proposez un accueil posé, confidentiel et structuré, même lorsque vous êtes en rendez-vous.',
        points: ['Image sérieuse et disponibilité perçue', 'Messages mieux qualifiés', 'Traitement priorisé des urgences et rappels'],
        icon: Stethoscope,
    },
];

const steps = [
    {
        title: 'Cadrez votre accueil',
        description: 'Définissez votre message d’accueil, vos horaires, vos réponses fréquentes et vos règles de transfert.',
        icon: HeartHandshake,
    },
    {
        title: 'Receptio répond pour vous',
        description: 'Le standard prend les appels entrants, rassure l’appelant et collecte les bonnes informations.',
        icon: CalendarClock,
    },
    {
        title: 'Votre équipe reprend la main',
        description: 'Vous recevez un résumé clair pour rappeler rapidement, prioriser et garder le contrôle.',
        icon: CheckCircle2,
    },
];
</script>

<template>
    <Head title="Receptio | Standard téléphonique intelligent pour PME" />

    <div class="min-h-screen bg-[linear-gradient(180deg,#f7fbff_0%,#eef5ff_38%,#ffffff_100%)] text-slate-900">
        <div
            class="absolute inset-x-0 top-0 -z-10 h-[38rem] bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.22),transparent_34%),radial-gradient(circle_at_top_right,rgba(14,165,233,0.18),transparent_30%)]"
        />

        <header class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
            <div class="flex items-center gap-3">
                <div
                    class="flex size-11 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-700 to-cyan-500 text-white shadow-lg shadow-blue-200"
                >
                    <PhoneCall class="size-5" />
                </div>
                <div>
                    <div class="text-lg font-semibold tracking-tight">Receptio</div>
                    <div class="text-sm text-slate-600">Accueil téléphonique structuré pour PME exigeantes</div>
                </div>
            </div>

            <nav class="flex items-center gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="hidden text-sm font-medium text-slate-600 transition hover:text-slate-900 sm:inline-flex"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link :href="route('login')" class="hidden text-sm font-medium text-slate-600 transition hover:text-slate-900 sm:inline-flex">
                        Connexion
                    </Link>
                    <Button as-child class="rounded-full bg-slate-950 px-5 text-white hover:bg-slate-800">
                        <Link :href="route('register')">Demander un accès</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main>
            <section class="mx-auto grid max-w-7xl gap-12 px-6 pb-16 pt-8 lg:grid-cols-[1.15fr_0.85fr] lg:items-center lg:px-8 lg:pb-24 lg:pt-14">
                <div>
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white/80 px-4 py-2 text-sm font-medium text-blue-900 shadow-sm shadow-blue-100 backdrop-blur"
                    >
                        <ShieldCheck class="size-4" />
                        Corporate, rassurant et pensé pour les métiers à forte sollicitation
                    </div>

                    <h1 class="mt-6 max-w-3xl text-5xl font-semibold leading-tight tracking-[-0.04em] text-slate-950 md:text-6xl">
                        Un accueil téléphonique fiable qui protège votre image et votre temps.
                    </h1>

                    <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                        Receptio aide les restaurateurs et les professions libérales à répondre avec constance, filtrer les demandes et transmettre
                        l’essentiel sans alourdir l’organisation.
                    </p>

                    <div class="mt-8 flex flex-col gap-4 sm:flex-row">
                        <Button
                            as-child
                            size="lg"
                            class="h-12 rounded-full bg-blue-700 px-6 text-base text-white shadow-lg shadow-blue-200 hover:bg-blue-800"
                        >
                            <Link :href="$page.props.auth.user ? route('dashboard') : route('register')">
                                {{ $page.props.auth.user ? 'Accéder au dashboard' : 'Ouvrir votre espace' }}
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                        <Button
                            as-child
                            size="lg"
                            variant="outline"
                            class="h-12 rounded-full border-blue-200 bg-white/80 px-6 text-base text-blue-950 hover:bg-blue-50"
                        >
                            <Link :href="route('login')">Voir la démonstration produit</Link>
                        </Button>
                    </div>

                    <div class="mt-8 grid gap-3 text-sm text-slate-700 sm:grid-cols-3">
                        <div
                            v-for="point in proofPoints"
                            :key="point"
                            class="rounded-2xl border border-white/70 bg-white/80 px-4 py-4 shadow-[0_16px_50px_-30px_rgba(15,23,42,0.55)] backdrop-blur"
                        >
                            <CheckCircle2 class="mb-3 size-5 text-blue-700" />
                            <p class="leading-6">{{ point }}</p>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div class="absolute -inset-6 rounded-[2rem] bg-gradient-to-br from-blue-200/40 via-white to-cyan-200/40 blur-3xl" />
                    <Card
                        class="relative overflow-hidden rounded-[2rem] border border-white/80 bg-white/90 shadow-[0_30px_80px_-30px_rgba(37,99,235,0.45)] backdrop-blur"
                    >
                        <CardContent class="p-0">
                            <div class="border-b border-slate-200 bg-slate-950 px-6 py-5 text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.28em] text-blue-200">Vue synthèse</p>
                                        <h2 class="mt-2 text-2xl font-semibold">Standard toujours cadré</h2>
                                    </div>
                                    <div class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-blue-100">Disponible 7j/7</div>
                                </div>
                            </div>

                            <div class="grid gap-4 p-6">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="rounded-2xl bg-blue-50 p-5">
                                        <p class="text-sm font-medium text-blue-900">Appels traités avec le bon niveau de priorité</p>
                                        <p class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">100%</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-600">
                                            Accueil homogène, même pendant le coup de feu ou en consultation.
                                        </p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 p-5">
                                        <p class="text-sm font-medium text-slate-700">Messages exploitables</p>
                                        <p class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">Clairs</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-600">
                                            Nom, contexte, urgence et demande sont structurés avant transmission.
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-3xl border border-slate-200 bg-white p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-950">Exemple d’accueil</p>
                                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                                “Bonjour, vous êtes bien chez Maison Lambert. Je peux vous aider pour une réservation, un horaire, ou
                                                transmettre une demande à l’équipe.”
                                            </p>
                                        </div>
                                        <div class="rounded-2xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">Ton posé</div>
                                    </div>
                                </div>

                                <div class="grid gap-3 rounded-3xl bg-slate-50 p-5">
                                    <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3">
                                        <span class="text-sm font-medium text-slate-700">Réservation de groupe</span>
                                        <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">À rappeler</span>
                                    </div>
                                    <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3">
                                        <span class="text-sm font-medium text-slate-700">Question sur un dossier urgent</span>
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Prioritaire</span>
                                    </div>
                                    <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3">
                                        <span class="text-sm font-medium text-slate-700">Demande d’information classique</span>
                                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Traité</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
                <div class="grid gap-5 lg:grid-cols-3">
                    <Card
                        v-for="feature in featureBlocks"
                        :key="feature.title"
                        class="rounded-[1.75rem] border border-blue-100 bg-white/85 shadow-[0_24px_60px_-35px_rgba(15,23,42,0.35)]"
                    >
                        <CardContent class="p-7">
                            <component :is="feature.icon" class="size-10 rounded-2xl bg-blue-100 p-2 text-blue-700" />
                            <h2 class="mt-5 text-xl font-semibold tracking-tight text-slate-950">{{ feature.title }}</h2>
                            <p class="mt-3 text-sm leading-7 text-slate-600">{{ feature.description }}</p>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-blue-700">Secteurs visés</p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 md:text-4xl">
                        Une posture professionnelle adaptée aux métiers où chaque appel compte.
                    </h2>
                </div>

                <div class="mt-10 grid gap-6 lg:grid-cols-2">
                    <Card
                        v-for="sector in sectors"
                        :key="sector.title"
                        class="rounded-[2rem] border border-slate-200 bg-white shadow-[0_28px_70px_-45px_rgba(37,99,235,0.45)]"
                    >
                        <CardContent class="p-8">
                            <div class="flex items-center gap-4">
                                <div class="rounded-2xl bg-gradient-to-br from-blue-600 to-cyan-500 p-3 text-white shadow-lg shadow-blue-200">
                                    <component :is="sector.icon" class="size-6" />
                                </div>
                                <h3 class="text-2xl font-semibold tracking-tight text-slate-950">{{ sector.title }}</h3>
                            </div>

                            <p class="mt-6 text-base leading-8 text-slate-600">{{ sector.description }}</p>

                            <div class="mt-6 space-y-3">
                                <div
                                    v-for="point in sector.points"
                                    :key="point"
                                    class="flex items-start gap-3 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700"
                                >
                                    <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-blue-700" />
                                    <span class="leading-6">{{ point }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
                <div
                    class="rounded-[2rem] border border-blue-100 bg-[linear-gradient(135deg,#eff6ff_0%,#ffffff_45%,#f0f9ff_100%)] p-8 shadow-[0_28px_70px_-50px_rgba(37,99,235,0.5)] md:p-10"
                >
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-blue-700">Méthode simple</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 md:text-4xl">
                            Une mise en place rapide, sans complexifier votre quotidien.
                        </h2>
                    </div>

                    <div class="mt-10 grid gap-5 lg:grid-cols-3">
                        <div v-for="step in steps" :key="step.title" class="rounded-[1.75rem] border border-white bg-white/80 p-6 shadow-sm">
                            <component :is="step.icon" class="size-10 rounded-2xl bg-blue-100 p-2 text-blue-700" />
                            <h3 class="mt-5 text-xl font-semibold text-slate-950">{{ step.title }}</h3>
                            <p class="mt-3 text-sm leading-7 text-slate-600">{{ step.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 pb-20 pt-8 lg:px-8">
                <div class="rounded-[2.25rem] bg-slate-950 px-8 py-10 text-white shadow-[0_34px_90px_-40px_rgba(15,23,42,0.85)] md:px-12 md:py-14">
                    <div class="flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-blue-200">Prêt à structurer votre accueil ?</p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight md:text-4xl">
                                Offrez un premier contact plus professionnel, sans mobiliser votre équipe en permanence.
                            </h2>
                            <p class="mt-4 text-base leading-8 text-slate-300">
                                Receptio s’adresse aux structures qui veulent rassurer dès la première sonnerie et reprendre la main avec des
                                informations utiles.
                            </p>
                        </div>

                        <div class="flex flex-col gap-4 sm:flex-row">
                            <Button
                                as-child
                                size="lg"
                                class="h-12 rounded-full bg-white px-6 text-base font-semibold text-slate-950 hover:bg-slate-100"
                            >
                                <Link :href="$page.props.auth.user ? route('dashboard') : route('register')">
                                    {{ $page.props.auth.user ? 'Entrer dans le dashboard' : 'Créer mon espace' }}
                                </Link>
                            </Button>
                            <Button
                                as-child
                                size="lg"
                                variant="outline"
                                class="h-12 rounded-full border-white/20 bg-white/5 px-6 text-base text-white hover:bg-white/10"
                            >
                                <Link :href="route('login')">Me connecter</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-blue-100/80 bg-white/70 backdrop-blur">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-5 px-6 py-5 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between lg:px-8"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-9 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-700 to-cyan-500 text-white shadow-md shadow-blue-200"
                    >
                        <PhoneCall class="size-4" />
                    </div>
                    <div>
                        <p class="font-semibold text-slate-900">Receptio</p>
                        <p>Standard téléphonique structuré pour restaurants et professions libérales.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <Link :href="$page.props.auth.user ? route('dashboard') : route('register')" class="transition hover:text-slate-900">
                        {{ $page.props.auth.user ? 'Dashboard' : 'Créer un espace' }}
                    </Link>
                    <Link :href="route('login')" class="transition hover:text-slate-900">Connexion</Link>
                    <span class="text-slate-400">© 2026 Receptio</span>
                </div>
            </div>
        </footer>
    </div>
</template>

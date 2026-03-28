<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, PhoneCall } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();

const highlights = [
    'Accueil telephonique cadre et coherent',
    'Messages qualifies transmis sans friction',
    'Configuration simple des horaires et transferts',
];
</script>

<template>
    <div
        class="relative min-h-svh overflow-hidden bg-[linear-gradient(180deg,#f7fbff_0%,#eef5ff_40%,#ffffff_100%)] px-6 py-10 text-slate-950 md:px-10"
    >
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-[34rem] bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.2),transparent_34%),radial-gradient(circle_at_top_right,rgba(14,165,233,0.18),transparent_28%)]"
        />

        <div class="relative mx-auto grid min-h-[calc(100svh-5rem)] w-full max-w-6xl items-center gap-10 lg:grid-cols-[1.05fr_0.95fr]">
            <section class="hidden lg:block">
                <div class="max-w-xl">
                    <Link
                        :href="route('home')"
                        class="inline-flex items-center gap-3 rounded-full border border-white/70 bg-white/70 px-4 py-2 shadow-sm backdrop-blur"
                    >
                        <div
                            class="flex size-11 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-700 to-cyan-500 text-white shadow-lg shadow-blue-200"
                        >
                            <PhoneCall class="size-5" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold tracking-tight text-slate-950">Receptio</p>
                            <p class="text-xs text-slate-600">Accueil telephonique structure pour PME exigeantes</p>
                        </div>
                    </Link>

                    <p class="mt-10 text-sm font-semibold uppercase tracking-[0.28em] text-blue-700">Espace securise</p>
                    <h1 class="mt-5 text-5xl font-semibold leading-tight tracking-[-0.04em] text-slate-950">
                        Reprenez la main sur vos appels sans alourdir votre organisation.
                    </h1>
                    <p class="mt-6 text-lg leading-8 text-slate-600">
                        Connectez-vous pour piloter vos horaires, vos messages et la priorisation des demandes depuis un espace clair.
                    </p>

                    <div class="mt-10 grid gap-4">
                        <div
                            v-for="highlight in highlights"
                            :key="highlight"
                            class="flex items-start gap-3 rounded-3xl border border-white/80 bg-white/80 px-5 py-4 shadow-[0_20px_60px_-35px_rgba(37,99,235,0.4)] backdrop-blur"
                        >
                            <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-blue-700" />
                            <p class="text-sm leading-6 text-slate-700">{{ highlight }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto w-full max-w-md">
                <div
                    class="bg-white/88 rounded-[2rem] border border-white/80 p-8 shadow-[0_30px_80px_-30px_rgba(37,99,235,0.35)] backdrop-blur md:p-10"
                >
                    <div class="flex flex-col gap-8">
                        <div class="flex flex-col items-center gap-4 text-center">
                            <Link :href="route('home')" class="flex flex-col items-center gap-3 font-medium">
                                <div
                                    class="flex size-14 items-center justify-center rounded-[1.25rem] bg-gradient-to-br from-slate-950 via-blue-800 to-cyan-500 text-white shadow-lg shadow-blue-200"
                                >
                                    <AppLogoIcon class="size-8 fill-current" />
                                </div>
                                <span class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">Receptio</span>
                            </Link>
                            <div class="space-y-3">
                                <h2 class="text-3xl font-semibold tracking-tight text-slate-950">{{ title }}</h2>
                                <p class="text-sm leading-6 text-slate-600">{{ description }}</p>
                            </div>
                        </div>
                        <slot />
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup lang="ts">
import ToneBadge from '@/components/dashboard/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        badgeLabel?: string;
        badgeTone?: 'default' | 'success' | 'warning' | 'info' | 'neutral';
        actionLabel?: string;
        actionHref?: string;
    }>(),
    {
        badgeLabel: '',
        badgeTone: 'default',
        actionLabel: '',
        actionHref: '',
    },
);
</script>

<template>
    <div
        class="relative overflow-hidden rounded-[2rem] border border-border/70 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 px-6 py-7 text-slate-50 shadow-[0_24px_80px_-32px_rgba(15,23,42,0.65)]"
    >
        <div
            class="pointer-events-none absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.18),transparent_48%)]"
        ></div>
        <div class="bg-white/8 pointer-events-none absolute -right-12 top-8 h-40 w-40 rounded-full blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 left-0 h-24 w-24 rounded-full bg-primary/20 blur-2xl"></div>
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl space-y-3">
                <ToneBadge v-if="badgeLabel" :label="badgeLabel" :tone="badgeTone" />
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-tight text-white md:text-4xl">{{ title }}</h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-300 md:text-base">
                        {{ description }}
                    </p>
                </div>
            </div>
            <div v-if="actionLabel && actionHref" class="shrink-0">
                <Button as-child class="border border-white/10 bg-white text-slate-950 shadow-sm hover:bg-slate-100">
                    <Link :href="actionHref">{{ actionLabel }}</Link>
                </Button>
            </div>
        </div>
    </div>
</template>

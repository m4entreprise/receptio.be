<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        tone?: 'default' | 'success' | 'warning' | 'info' | 'neutral';
    }>(),
    {
        tone: 'default',
    },
);

const toneClass = computed(() => {
    switch (props.tone) {
        case 'success':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700';
        case 'warning':
            return 'border-amber-200 bg-amber-50 text-amber-700';
        case 'info':
            return 'border-sky-200 bg-sky-50 text-sky-700';
        case 'neutral':
            return 'border-slate-200 bg-slate-100 text-slate-700';
        default:
            return 'border-primary/10 bg-primary/5 text-primary';
    }
});
</script>

<template>
    <span :class="toneClass" class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium tracking-wide">
        {{ label }}
    </span>
</template>

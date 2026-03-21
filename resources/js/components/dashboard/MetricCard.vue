<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

withDefaults(
    defineProps<{
        title: string;
        value: number | string;
        description?: string;
    }>(),
    {
        description: '',
    },
);
</script>

<template>
    <Card class="overflow-hidden border-border/70 bg-background/95 shadow-[0_18px_50px_-28px_rgba(15,23,42,0.35)]">
        <div class="h-1 w-full bg-gradient-to-r from-primary/80 via-primary/40 to-transparent"></div>
        <CardHeader class="space-y-2 pb-3">
            <CardDescription class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                {{ title }}
            </CardDescription>
            <CardTitle class="text-3xl font-semibold tracking-tight text-foreground md:text-[2rem]">
                {{ value }}
            </CardTitle>
        </CardHeader>
        <CardContent v-if="description" class="pt-0 text-sm text-muted-foreground">
            {{ description }}
        </CardContent>
    </Card>
</template>

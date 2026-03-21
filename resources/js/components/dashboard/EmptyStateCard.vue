<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        actionLabel?: string;
        actionHref?: string;
    }>(),
    {
        actionLabel: '',
        actionHref: '',
    },
);
</script>

<template>
    <Card class="overflow-hidden border-dashed border-border/80 bg-gradient-to-br from-background to-muted/30 shadow-none">
        <div class="h-1 w-full bg-gradient-to-r from-primary/70 via-primary/25 to-transparent"></div>
        <CardHeader class="pt-6">
            <CardTitle class="text-xl tracking-tight">{{ title }}</CardTitle>
            <CardDescription class="max-w-2xl text-sm leading-6">{{ description }}</CardDescription>
        </CardHeader>
        <CardContent v-if="actionLabel && actionHref">
            <Button as-child variant="outline">
                <Link :href="actionHref">{{ actionLabel }}</Link>
            </Button>
        </CardContent>
    </Card>
</template>

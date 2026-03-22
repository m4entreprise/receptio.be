<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, LockKeyhole, Mail } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const firstErrorMessage = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;
    const key = Object.keys(errors).find((k) => Boolean(errors[k]));

    return key ? errors[key] : undefined;
});

const emailInputClass = computed(() => {
    return [
        'h-12 rounded-2xl border-slate-200 bg-slate-50 pl-11 text-slate-950 placeholder:text-slate-400 focus-visible:border-blue-400 focus-visible:ring-blue-500/30',
        form.errors.email ? 'border-red-300 focus-visible:border-red-400 focus-visible:ring-red-500/30' : '',
    ].join(' ');
});

const passwordInputClass = computed(() => {
    return [
        'h-12 rounded-2xl border-slate-200 bg-slate-50 pl-11 text-slate-950 placeholder:text-slate-400 focus-visible:border-blue-400 focus-visible:ring-blue-500/30',
        form.errors.password ? 'border-red-300 focus-visible:border-red-400 focus-visible:ring-red-500/30' : '',
    ].join(' ');
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Connexion a votre espace" description="Retrouvez vos appels, messages et reglages depuis une interface securisee.">
        <Head title="Log in" />

        <div v-if="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700">
            {{ status }}
        </div>

        <div
            v-if="form.hasErrors && firstErrorMessage"
            class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
            role="alert"
            aria-live="polite"
        >
            {{ firstErrorMessage }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6" :aria-busy="form.processing">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email" class="text-sm font-medium text-slate-700">Adresse email</Label>
                    <div class="relative">
                        <Mail class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            tabindex="1"
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="vous@entreprise.be"
                            :disabled="form.processing"
                            :class="emailInputClass"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-sm font-medium text-slate-700">Mot de passe</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm text-slate-500 hover:text-slate-900" tabindex="5">
                            Mot de passe oublie ?
                        </TextLink>
                    </div>
                    <div class="relative">
                        <LockKeyhole class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            id="password"
                            type="password"
                            required
                            tabindex="2"
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="Votre mot de passe"
                            :disabled="form.processing"
                            :class="passwordInputClass"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3" tabindex="3">
                    <Label for="remember" class="flex items-center gap-3 text-sm font-medium text-slate-700">
                        <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" class="border-slate-300 data-[state=checked]:border-blue-700" />
                        <span>Rester connecte</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-2 h-12 w-full rounded-2xl bg-slate-950 text-base font-semibold text-white shadow-lg shadow-slate-300 hover:bg-blue-800"
                    tabindex="4"
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ form.processing ? 'Connexion…' : 'Se connecter' }}
                </Button>
            </div>

            <div class="text-center text-sm text-slate-600">
                Vous n'avez pas encore d'acces ?
                <TextLink :href="route('home')" :tabindex="5" class="font-medium text-slate-950 hover:text-blue-700">Demander une demo</TextLink>
            </div>
        </form>
    </AuthBase>
</template>

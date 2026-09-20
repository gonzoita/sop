<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';

const props = defineProps({
    error: String,
    invitation: Object,
});

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    if (!props.invitation) return;
    form.post(route('portal.invitations.process', { token: props.invitation.token }), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 font-sans text-slate-800">
        <Head title="Aceptar Invitación - Portal de Cliente" />

        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
            <div class="flex justify-center mb-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-amber-600 to-indigo-600 flex items-center justify-center text-white font-black text-2xl shadow-md">
                    S
                </div>
            </div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">
                Portal de Clientes
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Acceso a procedimientos y entrega de información
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <!-- Caso de Error o Invitación Inválida -->
            <div v-if="error" class="bg-white py-8 px-4 shadow-sm border border-slate-200 sm:rounded-2xl sm:px-10 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 mx-auto flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-slate-800 mb-2">Enlace no disponible</h3>
                <p class="text-sm text-slate-600 mb-6">{{ error }}</p>
                <a
                    :href="route('login')"
                    class="inline-flex items-center justify-center px-4 py-2 border border-slate-300 rounded-lg shadow-sm text-sm font-medium text-slate-700 bg-white hover:bg-slate-50"
                >
                    Ir al inicio de sesión
                </a>
            </div>

            <!-- Formulario de Aceptación -->
            <div v-else class="bg-white py-8 px-4 shadow-sm border border-slate-200 sm:rounded-2xl sm:px-10">
                <div class="mb-6 pb-4 border-b border-slate-100">
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-600">Invitación Recibida</span>
                    <h3 class="text-lg font-bold text-slate-900 mt-0.5">
                        {{ invitation.client_name }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Estás aceptando la invitación asociada al correo: <strong class="text-slate-700">{{ invitation.email }}</strong>
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Tu Nombre Completo
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Ej. María Pérez"
                            class="w-full rounded-lg border-slate-300 text-sm focus:ring-amber-500 focus:border-amber-500 shadow-sm"
                        />
                        <div v-if="form.errors.name" class="text-xs text-red-600 mt-1">
                            {{ form.errors.name }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Crea una Contraseña
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="Mínimo 8 caracteres"
                            class="w-full rounded-lg border-slate-300 text-sm focus:ring-amber-500 focus:border-amber-500 shadow-sm"
                        />
                        <div v-if="form.errors.password" class="text-xs text-red-600 mt-1">
                            {{ form.errors.password }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Confirmar Contraseña
                        </label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="Repite la contraseña"
                            class="w-full rounded-lg border-slate-300 text-sm focus:ring-amber-500 focus:border-amber-500 shadow-sm"
                        />
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-2.5 px-4 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-medium text-sm shadow-sm transition-colors flex items-center justify-center gap-2 disabled:opacity-50"
                        >
                            <svg v-if="form.processing" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Aceptar y Acceder al Portal</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

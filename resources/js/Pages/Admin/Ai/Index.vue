<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    credentials: Array,
    budget: Object,
});

// Modal y formulario para nueva credencial
const showModal = ref(false);
const credentialForm = useForm({
    provider: 'openrouter',
    label: '',
    api_key: '',
});

const openModal = () => {
    credentialForm.reset();
    credentialForm.provider = 'openrouter';
    showModal.value = true;
};

const submitCredential = () => {
    credentialForm.post(route('admin.ai-settings.credentials.store'), {
        onSuccess: () => {
            showModal.value = false;
            credentialForm.reset();
        },
    });
};

const toggleCredential = (credential) => {
    router.patch(route('admin.ai-settings.credentials.toggle', { credential: credential.id }), {}, {
        preserveScroll: true,
    });
};

const deleteCredential = (credential) => {
    if (confirm(`¿Estás seguro de eliminar la credencial "${credential.label || credential.provider}"? Las tareas de IA asociadas fallarán si no hay otra credencial activa.`)) {
        router.delete(route('admin.ai-settings.credentials.destroy', { credential: credential.id }));
    }
};

// Formulario de presupuesto
const budgetForm = useForm({
    monthly_limit_usd: props.budget?.monthly_limit_usd ?? 50.00,
    alert_at_percent: props.budget?.alert_at_percent ?? 80,
});

const submitBudget = () => {
    budgetForm.put(route('admin.ai-settings.budget.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="Configuración de IA y Presupuesto - Admin">
        <div class="space-y-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-purple-100 text-purple-800 border border-purple-200">
                            Administración
                        </span>
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Motor de IA y Presupuesto</h1>
                    </div>
                    <p class="text-sm text-slate-500 mt-1">
                        Gestiona las claves de acceso para OpenRouter y define límites preventivos de gasto mensual por equipo.
                    </p>
                </div>
                <button
                    @click="openModal"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nueva Credencial</span>
                </button>
            </div>

            <!-- Grid de Configuración -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Columna Izquierda: Presupuesto y Control de Gastos -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Control de Presupuesto</h2>
                                <p class="text-xs text-slate-500">Límites mensuales de seguridad</p>
                            </div>
                        </div>

                        <!-- Tarjeta de Gasto Actual -->
                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4 mb-6">
                            <div class="flex justify-between items-baseline mb-2">
                                <span class="text-xs font-medium text-slate-600">Consumo Mes Actual</span>
                                <span class="text-lg font-bold text-slate-900 font-mono">
                                    ${{ Number(budget.current_month_cost_usd || 0).toFixed(2) }} <span class="text-xs font-normal text-slate-400">/ ${{ Number(budget.monthly_limit_usd).toFixed(2) }} USD</span>
                                </span>
                            </div>
                            <!-- Barra de progreso -->
                            <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="h-2.5 rounded-full transition-all duration-500"
                                    :class="[
                                        (budget.current_month_cost_usd / (budget.monthly_limit_usd || 1)) >= (budget.alert_at_percent / 100)
                                            ? 'bg-amber-500'
                                            : 'bg-indigo-600'
                                    ]"
                                    :style="{ width: Math.min(100, Math.round((budget.current_month_cost_usd / (budget.monthly_limit_usd || 1)) * 100)) + '%' }"
                                ></div>
                            </div>
                            <div class="flex justify-between items-center mt-2 text-[11px] text-slate-500">
                                <span>Alerta al {{ budget.alert_at_percent }}%</span>
                                <span>{{ Math.min(100, Math.round((budget.current_month_cost_usd / (budget.monthly_limit_usd || 1)) * 100)) }}% consumido</span>
                            </div>
                        </div>

                        <!-- Formulario de Edición de Presupuesto -->
                        <form @submit.prevent="submitBudget" class="space-y-4">
                            <div>
                                <InputLabel for="monthly_limit_usd" value="Límite Mensual Máximo (USD)" class="text-xs font-semibold text-slate-700" />
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-sm">$</div>
                                    <TextInput
                                        id="monthly_limit_usd"
                                        v-model="budgetForm.monthly_limit_usd"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full pl-7 text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                </div>
                                <InputError :message="budgetForm.errors.monthly_limit_usd" class="mt-1 text-xs" />
                            </div>

                            <div>
                                <InputLabel for="alert_at_percent" value="Umbral de Alerta Temprana (%)" class="text-xs font-semibold text-slate-700" />
                                <div class="relative mt-1">
                                    <TextInput
                                        id="alert_at_percent"
                                        v-model="budgetForm.alert_at_percent"
                                        type="number"
                                        min="1"
                                        max="100"
                                        class="w-full pr-8 text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-sm">%</div>
                                </div>
                                <InputError :message="budgetForm.errors.alert_at_percent" class="mt-1 text-xs" />
                            </div>

                            <PrimaryButton
                                :disabled="budgetForm.processing"
                                class="w-full justify-center !rounded-xl text-xs uppercase tracking-wider py-2.5"
                            >
                                Actualizar Presupuesto
                            </PrimaryButton>
                        </form>

                        <!-- Nota de Seguridad -->
                        <div class="mt-6 p-3 bg-amber-50 border border-amber-200/80 rounded-xl text-xs text-amber-900 flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="leading-relaxed">
                                <strong class="font-semibold">Regla de corte preventivo:</strong> Si se alcanza el límite mensual, las tareas de IA pasarán a estado de espera y no se encolarán llamadas a OpenRouter hasta renovar o ampliar el presupuesto.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Credenciales de IA -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-slate-900">Credenciales de API</h2>
                                    <p class="text-xs text-slate-500">Claves de proveedores para ejecución en segundo plano</p>
                                </div>
                            </div>
                        </div>

                        <!-- Listado de Credenciales -->
                        <div v-if="credentials && credentials.length > 0" class="divide-y divide-slate-100">
                            <div
                                v-for="cred in credentials"
                                :key="cred.id"
                                class="py-4 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                            >
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-900 text-white">
                                            {{ cred.provider }}
                                        </span>
                                        <span class="font-semibold text-slate-900 text-sm">
                                            {{ cred.label || 'Credencial Principal' }}
                                        </span>
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider',
                                                cred.is_active
                                                    ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                                    : 'bg-slate-100 text-slate-600 border border-slate-200'
                                            ]"
                                        >
                                            {{ cred.is_active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-slate-500">
                                        <span class="font-mono bg-slate-100 text-slate-700 px-2 py-0.5 rounded border border-slate-200">
                                            {{ cred.masked_api_key }}
                                        </span>
                                        <span>• Registrada por {{ cred.creator_name }} el {{ cred.created_at }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        @click="toggleCredential(cred)"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
                                        :class="[
                                            cred.is_active
                                                ? 'border-slate-300 text-slate-700 hover:bg-slate-50'
                                                : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50'
                                        ]"
                                    >
                                        {{ cred.is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                    <button
                                        @click="deleteCredential(cred)"
                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                        title="Eliminar credencial"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Estado Vacío -->
                        <div v-else class="text-center py-10 px-4 border-2 border-dashed border-slate-200 rounded-xl">
                            <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 mx-auto flex items-center justify-center mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 mb-1">No hay credenciales configuradas</h3>
                            <p class="text-xs text-slate-500 max-w-sm mx-auto mb-4">
                                Agrega tu clave de API de OpenRouter para permitir la generación automática de contenidos y entregables en tus SOPs.
                            </p>
                            <button
                                @click="openModal"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Agregar Clave OpenRouter</span>
                            </button>
                        </div>

                        <!-- Garantía de Seguridad -->
                        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center gap-3 text-xs text-slate-500">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span>Las claves se almacenan cifradas en base de datos (AES-256) y nunca se muestran en texto plano ni se envían en logs.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Crear Credencial -->
            <DialogModal :show="showModal" @close="showModal = false">
                <template #title>
                    Configurar Credencial de IA
                </template>

                <template #content>
                    <div class="space-y-4">
                        <p class="text-xs text-slate-500">
                            Ingresa tu API Key de OpenRouter. Esta clave permitirá ejecutar tareas de IA en segundo plano mediante jobs en cola.
                        </p>

                        <div>
                            <InputLabel for="cred_provider" value="Proveedor" class="text-xs font-semibold text-slate-700" />
                            <select
                                id="cred_provider"
                                v-model="credentialForm.provider"
                                class="mt-1 w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 font-medium text-slate-700"
                                disabled
                            >
                                <option value="openrouter">OpenRouter (Compatible con OpenAI API)</option>
                            </select>
                        </div>

                        <div>
                            <InputLabel for="cred_label" value="Etiqueta identificadora (Opcional)" class="text-xs font-semibold text-slate-700" />
                            <TextInput
                                id="cred_label"
                                v-model="credentialForm.label"
                                type="text"
                                placeholder="Ej: OpenRouter Producción - Agencia"
                                class="mt-1 w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError :message="credentialForm.errors.label" class="mt-1 text-xs" />
                        </div>

                        <div>
                            <InputLabel for="cred_api_key" value="Clave de API (Secret Key)" class="text-xs font-semibold text-slate-700" />
                            <TextInput
                                id="cred_api_key"
                                v-model="credentialForm.api_key"
                                type="password"
                                placeholder="sk-or-v1-..."
                                class="mt-1 w-full text-sm font-mono rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            />
                            <InputError :message="credentialForm.errors.api_key" class="mt-1 text-xs" />
                            <p class="text-[11px] text-slate-400 mt-1">
                                La clave será cifrada inmediatamente y nunca se enviará completa al frontend.
                            </p>
                        </div>
                    </div>
                </template>

                <template #footer>
                    <SecondaryButton @click="showModal = false" class="!rounded-xl text-xs uppercase tracking-wider mr-2">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        @click="submitCredential"
                        :disabled="credentialForm.processing"
                        class="!rounded-xl text-xs uppercase tracking-wider"
                    >
                        Guardar Credencial
                    </PrimaryButton>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>

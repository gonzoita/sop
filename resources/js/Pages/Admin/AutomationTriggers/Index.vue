<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    triggers: Array,
    sops: Array,
});

const showModal = ref(false);
const form = useForm({
    event: 'client.created',
    sop_id: props.sops?.[0]?.id || '',
    is_active: true,
});

const openModal = () => {
    form.reset();
    form.sop_id = props.sops?.[0]?.id || '';
    showModal.value = true;
};

const submitCreate = () => {
    form.post(route('admin.automation-triggers.store'), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};

const toggleTrigger = (trigger) => {
    router.put(route('admin.automation-triggers.update', { automation_trigger: trigger.id }), {
        is_active: !trigger.is_active,
    }, {
        preserveScroll: true,
    });
};

const deleteTrigger = (trigger) => {
    if (confirm(`¿Estás seguro de eliminar este disparador de automatización?`)) {
        router.delete(route('admin.automation-triggers.destroy', { automation_trigger: trigger.id }));
    }
};

const getEventBadge = (event) => {
    switch (event) {
        case 'client.created':
            return { label: 'Cliente Creado', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
        case 'run.completed':
            return { label: 'Ejecución Completada', class: 'bg-indigo-100 text-indigo-800 border-indigo-200' };
        case 'run.step.approved':
            return { label: 'Paso Aprobado', class: 'bg-blue-100 text-blue-800 border-blue-200' };
        default:
            return { label: event, class: 'bg-slate-100 text-slate-700 border-slate-200' };
    }
};
</script>

<template>
    <AppLayout title="Disparadores de Automatización - Admin">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-purple-100 text-purple-800 border border-purple-200">
                            Administración
                        </span>
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Disparadores de Automatización</h1>
                    </div>
                    <p class="text-sm text-slate-500 mt-1">
                        Configura qué SOPs deben instanciarse automáticamente en segundo plano cuando ocurren eventos del sistema.
                    </p>
                </div>
                <button
                    @click="openModal"
                    :disabled="!sops || sops.length === 0"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-colors disabled:opacity-50"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nuevo Disparador</span>
                </button>
            </div>

            <!-- Warning if no published SOPs -->
            <div v-if="!sops || sops.length === 0" class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>Para crear automatizaciones, primero debes tener al menos un SOP publicado con versión activa.</span>
            </div>

            <!-- List of triggers -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div v-if="!triggers || triggers.length === 0" class="p-12 text-center text-slate-500">
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-800">No hay disparadores configurados</h3>
                    <p class="text-sm text-slate-500 mt-1">Crea un disparador para que la creación de clientes inicie automáticamente su onboarding.</p>
                </div>

                <table v-else class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-3.5">Estado</th>
                            <th class="px-6 py-3.5">Evento Disparador</th>
                            <th class="px-6 py-3.5">SOP a Instanciar</th>
                            <th class="px-6 py-3.5">Creador</th>
                            <th class="px-6 py-3.5">Fecha</th>
                            <th class="px-6 py-3.5 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="trigger in triggers" :key="trigger.id" class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4">
                                <button
                                    @click="toggleTrigger(trigger)"
                                    type="button"
                                    :class="trigger.is_active ? 'bg-emerald-600' : 'bg-slate-300'"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                >
                                    <span
                                        :class="trigger.is_active ? 'translate-x-5' : 'translate-x-0'"
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    />
                                </button>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    :class="getEventBadge(trigger.event).class"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                                >
                                    {{ getEventBadge(trigger.event).label }}
                                </span>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">{{ trigger.event }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ trigger.sop_title }}</div>
                                <div class="text-xs text-slate-400">ID SOP: #{{ trigger.sop_id }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                {{ trigger.creator_name }}
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-xs">
                                {{ trigger.created_at }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button
                                    @click="deleteTrigger(trigger)"
                                    class="text-xs text-red-600 hover:text-red-800 p-1.5 rounded hover:bg-red-50 transition-colors"
                                    title="Eliminar disparador"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Modal Nuevo Disparador -->
            <DialogModal :show="showModal" @close="showModal = false">
                <template #title>Nuevo Disparador de Automatización</template>
                <template #content>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Evento del Sistema</label>
                            <select v-model="form.event" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="client.created">Cliente Creado (client.created)</option>
                                <option value="run.completed">Ejecución Completada (run.completed)</option>
                                <option value="run.step.approved">Paso de SOP Aprobado (run.step.approved)</option>
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1">
                                Evento de dominio que desencadenará la ejecución en cola.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">SOP a Instanciar</label>
                            <select v-model="form.sop_id" required class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="" disabled>Selecciona un SOP publicado...</option>
                                <option v-for="sop in sops" :key="sop.id" :value="sop.id">
                                    {{ sop.title }}
                                </option>
                            </select>
                            <div v-if="form.errors.sop_id" class="text-xs text-red-600 mt-1">{{ form.errors.sop_id }}</div>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                id="is_active"
                                class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            />
                            <label for="is_active" class="text-xs font-medium text-slate-700">Activar disparador inmediatamente</label>
                        </div>
                    </div>
                </template>
                <template #footer>
                    <SecondaryButton @click="showModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton class="ml-3" :disabled="form.processing" @click="submitCreate">
                        Guardar Disparador
                    </PrimaryButton>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    clients: Object,
});

// Modal Nuevo Cliente
const showCreateModal = ref(false);
const createForm = useForm({
    name: '',
    contact_email: '',
    status: 'active',
});

const submitCreate = () => {
    createForm.post(route('clients.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

// Modal Invitar Cliente
const showInviteModal = ref(false);
const selectedClient = ref(null);
const inviteForm = useForm({
    email: '',
    role: 'collaborator',
});

const openInviteModal = (client) => {
    selectedClient.value = client;
    inviteForm.email = client.contact_email || '';
    showInviteModal.value = true;
};

const submitInvite = () => {
    if (!selectedClient.value) return;
    inviteForm.post(route('clients.invitations.store', { client: selectedClient.value.id }), {
        onSuccess: () => {
            showInviteModal.value = false;
            inviteForm.reset();
            selectedClient.value = null;
        },
    });
};

const deleteClient = (client) => {
    if (confirm(`¿Estás seguro de eliminar el cliente "${client.name}"?`)) {
        router.delete(route('clients.destroy', { client: client.id }));
    }
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'active':
            return { label: 'Activo', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
        case 'paused':
            return { label: 'Pausado', class: 'bg-amber-100 text-amber-800 border-amber-200' };
        case 'archived':
            return { label: 'Archivado', class: 'bg-slate-100 text-slate-700 border-slate-200' };
        default:
            return { label: status, class: 'bg-gray-100 text-gray-800 border-gray-200' };
    }
};
</script>

<template>
    <AppLayout title="Clientes - SOPForge">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Clientes de la Agencia</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        Administra clientes, envía invitaciones a su portal e inicia procedimientos.
                    </p>
                </div>
                <button
                    @click="showCreateModal = true"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nuevo Cliente</span>
                </button>
            </div>

            <!-- Table / List -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div v-if="!clients.data || clients.data.length === 0" class="p-12 text-center text-slate-500">
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-800">No hay clientes registrados</h3>
                    <p class="text-sm text-slate-500 mt-1">Registra tu primer cliente para habilitar su portal e invitarlo.</p>
                </div>

                <table v-else class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-3.5">Cliente</th>
                            <th class="px-6 py-3.5">Contacto</th>
                            <th class="px-6 py-3.5">Estado</th>
                            <th class="px-6 py-3.5">Usuarios Portal</th>
                            <th class="px-6 py-3.5">Ejecuciones</th>
                            <th class="px-6 py-3.5 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="client in clients.data" :key="client.id" class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ client.name }}</div>
                                <div class="text-xs text-slate-400 font-mono">slug: {{ client.slug }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                {{ client.contact_email || '—' }}
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    :class="getStatusBadge(client.status).class"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                                >
                                    {{ getStatusBadge(client.status).label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <span class="font-semibold text-slate-800">{{ client.users_count }}</span> activos
                                <span v-if="client.invitations_count > 0" class="text-xs text-amber-600 ml-1">
                                    ({{ client.invitations_count }} pend.)
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <span class="font-semibold text-slate-800">{{ client.runs_count }}</span> SOPs
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button
                                    @click="openInviteModal(client)"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <span>Invitar</span>
                                </button>
                                <button
                                    @click="deleteClient(client)"
                                    class="text-xs text-red-600 hover:text-red-800 p-1.5 rounded hover:bg-red-50 transition-colors"
                                    title="Eliminar cliente"
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

            <!-- Modal Nuevo Cliente -->
            <DialogModal :show="showCreateModal" @close="showCreateModal = false">
                <template #title>Registrar Nuevo Cliente</template>
                <template #content>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nombre de la Empresa / Marca</label>
                            <input
                                v-model="createForm.name"
                                type="text"
                                required
                                class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500"
                                placeholder="Ej. Acme Marketing"
                            />
                            <div v-if="createForm.errors.name" class="text-xs text-red-600 mt-1">{{ createForm.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Correo de Contacto Principal</label>
                            <input
                                v-model="createForm.contact_email"
                                type="email"
                                class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500"
                                placeholder="contacto@acme.com"
                            />
                            <div v-if="createForm.errors.contact_email" class="text-xs text-red-600 mt-1">{{ createForm.errors.contact_email }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Estado</label>
                            <select v-model="createForm.status" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="active">Activo</option>
                                <option value="paused">Pausado</option>
                                <option value="archived">Archivado</option>
                            </select>
                        </div>
                    </div>
                </template>
                <template #footer>
                    <SecondaryButton @click="showCreateModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton class="ml-3" :disabled="createForm.processing" @click="submitCreate">
                        Guardar Cliente
                    </PrimaryButton>
                </template>
            </DialogModal>

            <!-- Modal Invitar al Portal -->
            <DialogModal :show="showInviteModal" @close="showInviteModal = false">
                <template #title>Invitar al Portal de Cliente</template>
                <template #content>
                    <div class="space-y-4">
                        <p class="text-xs text-slate-600">
                            Se enviará un correo con un enlace único y temporal (48 horas) para acceder a los SOPs asignados a
                            <strong class="text-slate-900">{{ selectedClient?.name }}</strong>.
                        </p>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Correo del Contacto</label>
                            <input
                                v-model="inviteForm.email"
                                type="email"
                                required
                                class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500"
                                placeholder="usuario@cliente.com"
                            />
                            <div v-if="inviteForm.errors.email" class="text-xs text-red-600 mt-1">{{ inviteForm.errors.email }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Rol en el Portal</label>
                            <select v-model="inviteForm.role" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="collaborator">Colaborador (completar datos)</option>
                                <option value="owner">Propietario de Cuenta</option>
                            </select>
                        </div>
                    </div>
                </template>
                <template #footer>
                    <SecondaryButton @click="showInviteModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton class="ml-3" :disabled="inviteForm.processing" @click="submitInvite">
                        Enviar Invitación
                    </PrimaryButton>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>

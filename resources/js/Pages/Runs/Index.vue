<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    runs: {
        type: Object,
        required: true,
    },
    clients: {
        type: Array,
        default: () => [],
    },
    teamMembers: {
        type: Array,
        default: () => [],
    },
    availableSops: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const searchQuery = ref(props.filters.search || '');
const selectedStatus = ref(props.filters.status || '');
const selectedClient = ref(props.filters.client_id || '');
const selectedAssignee = ref(props.filters.assigned_to || '');

const applyFilters = () => {
    router.get(
        route('runs.index'),
        {
            search: searchQuery.value || undefined,
            status: selectedStatus.value || undefined,
            client_id: selectedClient.value || undefined,
            assigned_to: selectedAssignee.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

// Modal Nueva Ejecución
const showCreateModal = ref(false);
const createForm = useForm({
    sop_id: '',
    client_id: '',
    assigned_to: '',
    title: '',
});

const submitCreateRun = () => {
    createForm.post(route('runs.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const deleteRun = (runId) => {
    if (confirm('¿Estás seguro de eliminar esta ejecución?')) {
        router.delete(route('runs.destroy', runId));
    }
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'completed':
            return { label: 'Completado', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
        case 'awaiting_approval':
            return { label: 'Esperando Aprobación', class: 'bg-amber-50 text-amber-700 border-amber-200' };
        case 'awaiting_input':
            return { label: 'Esperando Datos', class: 'bg-purple-50 text-purple-700 border-purple-200' };
        case 'cancelled':
            return { label: 'Cancelado', class: 'bg-slate-100 text-slate-600 border-slate-200' };
        case 'in_progress':
        default:
            return { label: 'En Progreso', class: 'bg-indigo-50 text-indigo-700 border-indigo-200' };
    }
};
</script>

<template>
    <AppLayout title="Ejecuciones de SOPs">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="font-bold text-xl text-slate-900 leading-tight">
                        Ejecuciones de SOPs
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Flujos de trabajo activos, seguimiento de entregables y asignaciones del equipo.
                    </p>
                </div>

                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm transition shrink-0 cursor-pointer"
                >
                    <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Iniciar Ejecución
                </button>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Barra de Filtros -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="flex flex-1 flex-wrap items-center gap-3 w-full md:w-auto">
                    <div class="relative flex-1 min-w-[200px]">
                        <input
                            type="text"
                            v-model="searchQuery"
                            @keyup.enter="applyFilters"
                            placeholder="Buscar por título de ejecución o SOP..."
                            class="w-full text-xs pl-9 pr-4 py-2 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl placeholder:text-slate-400"
                        />
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                    </div>

                    <select
                        v-model="selectedStatus"
                        @change="applyFilters"
                        class="text-xs border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl py-2"
                    >
                        <option value="">Todos los estados</option>
                        <option value="in_progress">En Progreso</option>
                        <option value="awaiting_approval">Esperando Aprobación</option>
                        <option value="awaiting_input">Esperando Datos</option>
                        <option value="completed">Completados</option>
                    </select>

                    <select
                        v-if="clients.length > 0"
                        v-model="selectedClient"
                        @change="applyFilters"
                        class="text-xs border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl py-2"
                    >
                        <option value="">Todos los clientes</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>

                    <select
                        v-if="teamMembers.length > 0"
                        v-model="selectedAssignee"
                        @change="applyFilters"
                        class="text-xs border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl py-2"
                    >
                        <option value="">Todos los responsables</option>
                        <option v-for="m in teamMembers" :key="m.id" :value="m.id">
                            {{ m.name }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center space-x-2 shrink-0">
                    <button
                        type="button"
                        @click="applyFilters"
                        class="px-3 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition"
                    >
                        Filtrar
                    </button>
                </div>
            </div>

            <!-- Listado de Ejecuciones -->
            <div v-if="runs.data.length === 0" class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-800">No hay ejecuciones activas</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
                    Inicia la primera ejecución seleccionando uno de tus SOPs publicados.
                </p>
                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700 transition"
                >
                    Iniciar primera ejecución
                </button>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="run in runs.data"
                    :key="run.id"
                    class="bg-white rounded-2xl border border-slate-200 hover:border-indigo-300 hover:shadow-md transition p-5 flex flex-col justify-between space-y-4 group"
                >
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider border', getStatusBadge(run.status).class]">
                                {{ getStatusBadge(run.status).label }}
                            </span>

                            <span v-if="run.client" class="text-[11px] font-medium text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">
                                {{ run.client.name }}
                            </span>
                        </div>

                        <Link :href="route('runs.show', run.id)" class="block">
                            <h2 class="text-base font-bold text-slate-900 group-hover:text-indigo-600 transition leading-snug line-clamp-1">
                                {{ run.title }}
                            </h2>
                        </Link>

                        <p class="text-xs text-slate-500 flex items-center space-x-1">
                            <span class="font-medium text-slate-700">{{ run.sop?.title }}</span>
                            <span>·</span>
                            <span>v{{ run.sop_version?.version_number || 1 }}</span>
                        </p>

                        <!-- Barra de Progreso -->
                        <div class="pt-2 space-y-1">
                            <div class="flex items-center justify-between text-[11px] text-slate-500">
                                <span>Progreso</span>
                                <span class="font-semibold text-slate-700">
                                    {{ run.completed_steps_count || 0 }} / {{ run.steps_count || 0 }} pasos
                                </span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div
                                    class="bg-indigo-600 h-full rounded-full transition-all duration-300"
                                    :style="{ width: `${run.steps_count ? Math.round(((run.completed_steps_count || 0) / run.steps_count) * 100) : 0}%` }"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <div class="text-[11px] text-slate-400">
                            <span v-if="run.assignee">
                                Resp: <strong class="text-slate-600">{{ run.assignee.name }}</strong>
                            </span>
                            <span v-else>
                                Sin responsable
                            </span>
                        </div>

                        <div class="flex items-center space-x-1">
                            <Link
                                :href="route('runs.show', run.id)"
                                class="px-2.5 py-1 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition"
                            >
                                Abrir
                            </Link>

                            <button
                                type="button"
                                @click="deleteRun(run.id)"
                                class="p-1 text-slate-400 hover:text-rose-600 rounded-lg transition"
                                title="Eliminar ejecución"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="runs.links && runs.links.length > 3" class="flex items-center justify-center space-x-1 pt-4">
                <Link
                    v-for="(link, i) in runs.links"
                    :key="i"
                    :href="link.url || '#'"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                        link.active
                            ? 'bg-indigo-600 text-white shadow-2xs'
                            : (link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 cursor-not-allowed')
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>

        <!-- Modal Iniciar Nueva Ejecución -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4 border border-slate-100">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">
                        Iniciar Nueva Ejecución de SOP
                    </h3>
                    <button
                        type="button"
                        @click="showCreateModal = false"
                        class="text-slate-400 hover:text-slate-600 p-1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submitCreateRun" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Seleccionar SOP <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="createForm.sop_id"
                            required
                            class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                        >
                            <option value="" disabled>Selecciona un procedimiento publicado...</option>
                            <option v-for="s in availableSops" :key="s.id" :value="s.id">
                                {{ s.title }}
                            </option>
                        </select>
                        <p v-if="createForm.errors.sop_id" class="text-[11px] text-rose-600 mt-1">
                            {{ createForm.errors.sop_id }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Título personalizado (opcional)
                        </label>
                        <input
                            type="text"
                            v-model="createForm.title"
                            placeholder="ej. Campaña Meta Ads - Cliente Acme Q3"
                            class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Cliente asociado (opcional)
                        </label>
                        <select
                            v-model="createForm.client_id"
                            class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                        >
                            <option value="">Sin cliente asociado (Interno)</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Responsable principal (opcional)
                        </label>
                        <select
                            v-model="createForm.assigned_to"
                            class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                        >
                            <option value="">Sin asignar</option>
                            <option v-for="m in teamMembers" :key="m.id" :value="m.id">
                                {{ m.name }}
                            </option>
                        </select>
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-xl transition"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing || !createForm.sop_id"
                            class="inline-flex items-center px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-sm disabled:opacity-50 cursor-pointer"
                        >
                            Iniciar Flujo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

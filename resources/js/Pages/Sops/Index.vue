<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    sops: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

const searchQuery = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');
const selectedStatus = ref(props.filters.status || '');

const applyFilters = () => {
    router.get(
        route('sops.index'),
        {
            search: searchQuery.value || undefined,
            category: selectedCategory.value || undefined,
            status: selectedStatus.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

// Create SOP Modal
const showCreateModal = ref(false);
const createForm = useForm({
    title: '',
    description: '',
    category: '',
    is_template: false,
});

const submitCreateSop = () => {
    createForm.post(route('sops.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const duplicateSop = (sopId) => {
    if (confirm('¿Deseas duplicar este SOP con todos sus bloques?')) {
        router.post(route('sops.duplicate', sopId));
    }
};

const deleteSop = (sopId) => {
    if (confirm('¿Estás seguro de eliminar este SOP?')) {
        router.delete(route('sops.destroy', sopId));
    }
};
</script>

<template>
    <AppLayout title="Biblioteca de SOPs">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="font-bold text-xl text-slate-900 leading-tight">
                        Procedimientos Operativos Estándar (SOP)
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Biblioteca de procesos interactivos, tareas de IA y flujos de trabajo de tu agencia.
                    </p>
                </div>

                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm transition shrink-0"
                >
                    <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Nuevo SOP
                </button>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Barra de Filtros -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="flex flex-1 items-center space-x-3 w-full md:w-auto">
                    <div class="relative flex-1">
                        <input
                            type="text"
                            v-model="searchQuery"
                            @keyup.enter="applyFilters"
                            placeholder="Buscar por título o descripción..."
                            class="w-full text-xs pl-9 pr-4 py-2 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl placeholder:text-slate-400"
                        />
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                    </div>

                    <select
                        v-model="selectedCategory"
                        @change="applyFilters"
                        class="text-xs border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl py-2"
                    >
                        <option value="">Todas las categorías</option>
                        <option v-for="cat in categories" :key="cat" :value="cat">
                            {{ cat }}
                        </option>
                    </select>

                    <select
                        v-model="selectedStatus"
                        @change="applyFilters"
                        class="text-xs border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl py-2"
                    >
                        <option value="">Todos los estados</option>
                        <option value="published">Publicados</option>
                        <option value="draft">Borradores</option>
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

            <!-- Listado de Tarjetas -->
            <div v-if="sops.data.length === 0" class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-800">No se encontraron SOPs</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
                    Comienza creando el primer procedimiento operativo estándar para tu equipo o ajusta los filtros de búsqueda.
                </p>
                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700 transition"
                >
                    Crear mi primer SOP
                </button>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="sop in sops.data"
                    :key="sop.id"
                    class="bg-white rounded-2xl border border-slate-200 hover:border-indigo-300 hover:shadow-md transition p-5 flex flex-col justify-between space-y-4 group"
                >
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider border',
                                    sop.status === 'published'
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                        : 'bg-amber-50 text-amber-700 border-amber-200'
                                ]"
                            >
                                {{ sop.status === 'published' ? 'Publicado' : 'Borrador' }}
                            </span>

                            <span v-if="sop.category" class="text-[11px] font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">
                                {{ sop.category }}
                            </span>
                        </div>

                        <Link :href="route('sops.edit', sop.id)" class="block">
                            <h2 class="text-base font-bold text-slate-900 group-hover:text-indigo-600 transition leading-snug line-clamp-1">
                                {{ sop.title }}
                            </h2>
                        </Link>

                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                            {{ sop.description || 'Sin descripción adicional para este procedimiento.' }}
                        </p>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <div class="text-[11px] text-slate-400">
                            <span v-if="sop.current_version">
                                Versión v{{ sop.current_version.version_number }}
                            </span>
                            <span v-else>
                                En edición (borrador)
                            </span>
                        </div>

                        <div class="flex items-center space-x-1">
                            <Link
                                :href="route('sops.edit', sop.id)"
                                class="px-2.5 py-1 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition"
                                title="Abrir constructor"
                            >
                                Editar
                            </Link>

                            <button
                                type="button"
                                @click="duplicateSop(sop.id)"
                                class="p-1 text-slate-400 hover:text-indigo-600 rounded-lg transition"
                                title="Duplicar SOP"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                            </button>

                            <button
                                type="button"
                                @click="deleteSop(sop.id)"
                                class="p-1 text-slate-400 hover:text-rose-600 rounded-lg transition"
                                title="Eliminar SOP"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="sops.links && sops.links.length > 3" class="flex items-center justify-center space-x-1 pt-4">
                <Link
                    v-for="(link, i) in sops.links"
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

        <!-- Modal Crear SOP -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4 border border-slate-100">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">
                        Crear Nuevo Procedimiento Operativo
                    </h3>
                    <button
                        type="button"
                        @click="showCreateModal = false"
                        class="text-slate-400 hover:text-slate-600 p-1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submitCreateSop" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Título del SOP <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            v-model="createForm.title"
                            placeholder="ej. Onboarding de Nuevos Clientes de Meta Ads"
                            required
                            class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                        />
                        <p v-if="createForm.errors.title" class="text-[11px] text-rose-600 mt-1">
                            {{ createForm.errors.title }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Categoría (opcional)
                        </label>
                        <input
                            type="text"
                            v-model="createForm.category"
                            placeholder="ej. Marketing, Operaciones, Ventas..."
                            class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Descripción
                        </label>
                        <textarea
                            v-model="createForm.description"
                            rows="2"
                            placeholder="Propósito del procedimiento, responsables y alcance..."
                            class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                        />
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
                            :disabled="createForm.processing"
                            class="inline-flex items-center px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-sm disabled:opacity-50"
                        >
                            Crear y Abrir Constructor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    users: Array,
    logNames: Array,
});

const filterForm = ref({
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    user_id: props.filters.user_id || '',
    log_name: props.filters.log_name || '',
});

const expandedRow = ref(null);

const toggleDetails = (id) => {
    expandedRow.value = expandedRow.value === id ? null : id;
};

const applyFilters = () => {
    router.get(route('admin.audit-logs.index'), filterForm.value, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.value = {
        date_from: '',
        date_to: '',
        user_id: '',
        log_name: '',
    };
    applyFilters();
};

const formatDate = (isoString) => {
    if (!isoString) return '-';
    const date = new Date(isoString);
    return date.toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};
</script>

<template>
    <AppLayout title="Registro de Auditoría">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Registro de Auditoría
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Historial inmutable de eventos, cambios y accesos de seguridad del equipo.
                    </p>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    Solo lectura
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Filtros -->
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <form @submit.prevent="applyFilters" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha desde</label>
                            <input
                                v-model="filterForm.date_from"
                                type="date"
                                class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha hasta</label>
                            <input
                                v-model="filterForm.date_to"
                                type="date"
                                class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Usuario</label>
                            <select
                                v-model="filterForm.user_id"
                                class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Todos los usuarios</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Módulo / Tipo</label>
                            <select
                                v-model="filterForm.log_name"
                                class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Todos los tipos</option>
                                <option v-for="name in logNames" :key="name" :value="name">
                                    {{ name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex space-x-2">
                            <button
                                type="submit"
                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition"
                            >
                                Filtrar
                            </button>
                            <button
                                type="button"
                                @click="resetFilters"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-3 rounded-lg text-sm transition"
                            >
                                Limpiar
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tabla de registros -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Fecha / Hora</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Módulo</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Usuario</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Descripción</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider">Detalles</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <template v-for="log in logs.data" :key="log.id">
                                    <tr class="hover:bg-gray-50/70 transition">
                                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 font-mono">
                                            {{ formatDate(log.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                :class="{
                                                    'bg-red-100 text-red-800': log.log_name === 'auth',
                                                    'bg-blue-100 text-blue-800': log.log_name === 'clients',
                                                    'bg-amber-100 text-amber-800': log.log_name === 'roles',
                                                    'bg-gray-100 text-gray-800': !['auth', 'clients', 'roles'].includes(log.log_name),
                                                }"
                                                class="px-2.5 py-0.5 rounded-full text-xs font-medium uppercase tracking-wide"
                                            >
                                                {{ log.log_name || 'general' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-800 font-medium">
                                            <div v-if="log.causer">
                                                <div>{{ log.causer.name }}</div>
                                                <div class="text-xs text-gray-400 font-normal">{{ log.causer.email }}</div>
                                            </div>
                                            <span v-else class="text-xs text-gray-400 italic">
                                                {{ log.properties?.email ? log.properties.email : 'Sistema / Anónimo' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-700">
                                            {{ log.description }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <button
                                                v-if="log.properties && Object.keys(log.properties).length > 0"
                                                @click="toggleDetails(log.id)"
                                                class="text-indigo-600 hover:text-indigo-900 font-medium text-xs focus:outline-none"
                                            >
                                                {{ expandedRow === log.id ? 'Ocultar' : 'Ver datos' }}
                                            </button>
                                            <span v-else class="text-gray-300 text-xs">—</span>
                                        </td>
                                    </tr>

                                    <!-- Fila expandible de propiedades -->
                                    <tr v-if="expandedRow === log.id" class="bg-gray-50/50">
                                        <td colspan="5" class="px-6 py-3">
                                            <div class="text-xs font-mono bg-gray-900 text-emerald-400 p-4 rounded-lg overflow-x-auto shadow-inner">
                                                <pre>{{ JSON.stringify(log.properties, null, 2) }}</pre>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <tr v-if="!logs.data || logs.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">
                                        No se encontraron registros de auditoría que coincidan con los filtros seleccionados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="logs.links && logs.links.length > 3" class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                        <div class="text-xs text-gray-500">
                            Mostrando {{ logs.from || 0 }} a {{ logs.to || 0 }} de {{ logs.total }} registros
                        </div>
                        <div class="flex space-x-1">
                            <component
                                :is="link.url ? 'a' : 'span'"
                                v-for="(link, i) in logs.links"
                                :key="i"
                                :href="link.url"
                                :class="[
                                    'px-3 py-1 rounded-lg text-xs font-medium transition',
                                    link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200' : 'text-gray-300 cursor-not-allowed'
                                ]"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
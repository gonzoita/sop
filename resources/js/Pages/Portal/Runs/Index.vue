<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';

defineProps({
    runs: Array,
    clients: Array,
});

const getStatusBadge = (status) => {
    switch (status) {
        case 'completed':
            return { label: 'Completado', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
        case 'in_progress':
            return { label: 'En Progreso', class: 'bg-blue-100 text-blue-800 border-blue-200' };
        case 'awaiting_input':
            return { label: 'Esperando Datos', class: 'bg-amber-100 text-amber-800 border-amber-200' };
        default:
            return { label: 'Pendiente', class: 'bg-slate-100 text-slate-700 border-slate-200' };
    }
};
</script>

<template>
    <ClientPortalLayout title="Mis Procedimientos - Portal de Cliente">
        <div class="space-y-6">
            <!-- Header section -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Procedimientos Asignados</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Revisa y completa la información solicitada por el equipo para avanzar en tus proyectos.
                    </p>
                </div>
                <div v-if="clients && clients.length" class="flex items-center gap-2 text-xs text-slate-500 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg">
                    <span class="font-medium text-slate-700">Organización:</span>
                    <span>{{ clients.map(c => c.name).join(', ') }}</span>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!runs || runs.length === 0" class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-slate-800">No hay procedimientos pendientes</h3>
                <p class="text-sm text-slate-500 mt-1">Actualmente no tienes tareas o procedimientos activos requeridos por la agencia.</p>
            </div>

            <!-- Runs Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="run in runs"
                    :key="run.id"
                    class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between"
                >
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <span
                                :class="getStatusBadge(run.status).class"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                            >
                                {{ getStatusBadge(run.status).label }}
                            </span>
                            <span class="text-xs text-slate-400">{{ run.created_at }}</span>
                        </div>

                        <h2 class="text-base font-bold text-slate-900 leading-snug">
                            {{ run.title }}
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">
                            Procedimiento base: <span class="font-medium text-slate-700">{{ run.sop_title }}</span>
                        </p>
                    </div>

                    <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs text-slate-500">{{ run.client_name }}</span>
                        <Link
                            :href="route('portal.runs.show', { run: run.id })"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white shadow-sm transition-colors"
                        >
                            <span>Completar Información</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </ClientPortalLayout>
</template>

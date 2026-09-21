<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';

defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
    clients: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <ClientPortalLayout title="Mis Documentos - Portal de Cliente">
        <div class="space-y-6">
            <!-- Header section -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Mis Documentos</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Accede a los entregables oficiales, reportes y estrategias publicados por la agencia.
                    </p>
                </div>
                <div v-if="clients && clients.length" class="flex items-center gap-2 text-xs text-slate-500 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl">
                    <span class="font-medium text-slate-700">Organización:</span>
                    <span>{{ clients.map(c => c.name).join(', ') }}</span>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!documents || documents.length === 0" class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-slate-800">No hay documentos publicados</h3>
                <p class="text-sm text-slate-500 mt-1">Los entregables y documentos revisados por el equipo aparecerán aquí cuando estén listos.</p>
            </div>

            <!-- Documents Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="doc in documents"
                    :key="doc.id"
                    class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between"
                >
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200">
                                Oficial
                            </span>
                            <span class="text-xs text-slate-400">{{ doc.published_at }}</span>
                        </div>

                        <h2 class="text-base font-bold text-slate-900 leading-snug">
                            {{ doc.title }}
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">
                            Procedimiento de origen: <span class="font-medium text-slate-700">{{ doc.sop_title }}</span>
                        </p>
                    </div>

                    <div class="mt-5 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs text-slate-500">{{ doc.client_name }}</span>

                        <div class="flex items-center space-x-2">
                            <Link
                                :href="route('portal.documents.show', doc.id)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white shadow-xs transition-colors"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span>Ver</span>
                            </Link>

                            <a
                                :href="route('portal.documents.download.md', doc.id)"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-medium text-slate-700 hover:text-slate-900 bg-slate-50 hover:bg-slate-100 border border-slate-200 transition-colors"
                                title="Descargar en formato Markdown (.md)"
                            >
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>.MD</span>
                            </a>

                            <a
                                :href="route('portal.documents.download.pdf', doc.id)"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-semibold text-rose-700 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors"
                                title="Descargar en formato PDF (.pdf)"
                            >
                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>PDF</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ClientPortalLayout>
</template>

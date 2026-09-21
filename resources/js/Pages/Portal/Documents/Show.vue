<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';

defineProps({
    document: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <ClientPortalLayout :title="`${document.title} - Mis Documentos`">
        <div class="space-y-6 max-w-4xl mx-auto">
            <!-- Breadcrumbs / Back button -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route('portal.documents.index')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Volver a Mis Documentos</span>
                </Link>

                <div class="flex items-center space-x-2">
                    <a
                        :href="route('portal.documents.download.md', document.id)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium text-slate-700 hover:text-slate-900 bg-white hover:bg-slate-50 border border-slate-200 shadow-xs transition-colors"
                        title="Descargar en formato Markdown (.md)"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Descargar Markdown</span>
                    </a>

                    <a
                        :href="route('portal.documents.download.pdf', document.id)"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 shadow-xs transition-colors"
                        title="Descargar en formato PDF (.pdf)"
                    >
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Descargar PDF</span>
                    </a>
                </div>
            </div>

            <!-- Document Card View -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <!-- Document Header -->
                <div class="p-6 sm:p-8 border-b border-slate-100 bg-gradient-to-b from-slate-50/50 to-white">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Documento Oficial
                        </span>
                        <span class="text-xs text-slate-400">&bull;</span>
                        <span class="text-xs text-slate-500">Publicado el {{ document.published_at }}</span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight leading-tight">
                        {{ document.title }}
                    </h1>

                    <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-500">
                        <div>
                            <span>Procedimiento:</span>
                            <strong class="text-slate-700 ml-1">{{ document.sop_title }}</strong>
                        </div>
                        <span class="text-slate-300">&bull;</span>
                        <div>
                            <span>Organización:</span>
                            <strong class="text-slate-700 ml-1">{{ document.client_name }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Document Content (Sanitized Markdown HTML) -->
                <div class="p-6 sm:p-8">
                    <div
                        class="prose prose-slate max-w-none prose-headings:font-bold prose-headings:tracking-tight prose-h1:text-xl prose-h2:text-lg prose-h3:text-base prose-p:leading-relaxed prose-table:border-collapse prose-th:bg-slate-50 prose-th:p-2 prose-td:p-2 prose-td:border prose-th:border prose-td:border-slate-200 prose-th:border-slate-200 prose-blockquote:border-l-amber-500 prose-blockquote:bg-amber-50/50 prose-blockquote:py-1 prose-blockquote:px-3 prose-blockquote:rounded-r-lg prose-code:bg-slate-100 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:text-xs"
                        v-html="document.html"
                    />
                </div>
            </div>
        </div>
    </ClientPortalLayout>
</template>

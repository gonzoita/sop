<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import draggable from 'vuedraggable';

import { useSopBuilder } from '@/Composables/useSopBuilder';

import HeadingBlock from '@/Components/Sop/Blocks/HeadingBlock.vue';
import TextBlock from '@/Components/Sop/Blocks/TextBlock.vue';
import ChecklistBlock from '@/Components/Sop/Blocks/ChecklistBlock.vue';
import InputBlock from '@/Components/Sop/Blocks/InputBlock.vue';
import MediaBlock from '@/Components/Sop/Blocks/MediaBlock.vue';
import DecisionBlock from '@/Components/Sop/Blocks/DecisionBlock.vue';
import AiTaskBlock from '@/Components/Sop/Blocks/AiTaskBlock.vue';
import ApprovalBlock from '@/Components/Sop/Blocks/ApprovalBlock.vue';
import HandoffBlock from '@/Components/Sop/Blocks/HandoffBlock.vue';

const props = defineProps({
    sop: {
        type: Object,
        required: true,
    },
    activeDraft: {
        type: Object,
        default: null,
    },
    initialBlocks: {
        type: Object,
        default: () => ({ schema_version: 1, blocks: [] }),
    },
    versionsHistory: {
        type: Array,
        default: () => [],
    },
});

const {
    blocks,
    status,
    lastSaved,
    errorMessage,
    hasChanges,
    addBlock,
    removeBlock,
    updateBlock,
    saveDraft,
    publish,
} = useSopBuilder(props.sop, props.initialBlocks);

// Mapping from block type to Vue component
const blockComponents = {
    heading: HeadingBlock,
    text: TextBlock,
    checklist: ChecklistBlock,
    input: InputBlock,
    media: MediaBlock,
    decision: DecisionBlock,
    ai_task: AiTaskBlock,
    approval: ApprovalBlock,
    handoff: HandoffBlock,
};

// Modal for publishing
const showPublishModal = ref(false);
const changelogText = ref('');
const isPublishing = ref(false);
const publishErrors = ref([]);
const publishSuccessMessage = ref('');

const openPublishModal = () => {
    publishErrors.value = [];
    publishSuccessMessage.value = '';
    changelogText.value = '';
    showPublishModal.value = true;
};

const handleConfirmPublish = async () => {
    isPublishing.value = true;
    publishErrors.value = [];

    try {
        const res = await publish(changelogText.value);
        if (res.success) {
            publishSuccessMessage.value = res.message || 'SOP publicado exitosamente.';
            setTimeout(() => {
                showPublishModal.value = false;
                router.reload();
            }, 1200);
        }
    } catch (err) {
        const data = err.response?.data;
        if (data?.errors && Array.isArray(data.errors)) {
            publishErrors.value = data.errors;
        } else if (data?.message) {
            publishErrors.value = [data.message];
        } else {
            publishErrors.value = ['Ocurrió un error inesperado al publicar el SOP.'];
        }
    } finally {
        isPublishing.value = false;
    }
};

// Available block definitions for the sidebar
const availableBlockCategories = [
    {
        name: 'Contenido y Estructura',
        items: [
            { type: 'heading', label: 'Encabezado', desc: 'Título de sección o fase', icon: 'H' },
            { type: 'text', label: 'Texto / Instrucción', desc: 'Guía detallada en HTML/texto', icon: '¶' },
        ],
    },
    {
        name: 'Datos y Verificación',
        items: [
            { type: 'input', label: 'Campo de Entrada', desc: 'Define una variable {{clave}}', icon: '⌗' },
            { type: 'checklist', label: 'Checklist', desc: 'Lista de verificación manual', icon: '✓' },
            { type: 'media', label: 'Multimedia / Enlace', desc: 'Video de Loom, imagen o archivo', icon: '▶' },
        ],
    },
    {
        name: 'Inteligencia Artificial',
        items: [
            { type: 'ai_task', label: 'Tarea de IA', desc: 'Ejecuta un modelo y produce variables', icon: '⚡' },
        ],
    },
    {
        name: 'Control y Flujo',
        items: [
            { type: 'approval', label: 'Aprobación Humana', desc: 'Punto de revisión y control', icon: '🛡' },
            { type: 'decision', label: 'Bifurcación', desc: 'Pregunta condicional y salto', icon: '⌥' },
            { type: 'handoff', label: 'Entrega (Handoff)', desc: 'Pase a cliente o equipo', icon: '⇄' },
        ],
    },
];

// Formatting helper for last saved
const formattedLastSaved = computed(() => {
    if (!lastSaved.value) return '';
    return new Intl.DateTimeFormat('es-ES', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(lastSaved.value);
});
</script>

<template>
    <AppLayout :title="`Editor: ${sop.title}`">
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <!-- Título y Metadatos -->
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('sops.index')"
                        class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                        title="Volver al listado"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    </Link>

                    <div>
                        <div class="flex items-center space-x-2">
                            <h1 class="text-lg font-bold text-slate-900 leading-tight">
                                {{ sop.title }}
                            </h1>
                            <span v-if="sop.category" class="px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-600">
                                {{ sop.category }}
                            </span>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[11px] font-semibold border',
                                    sop.status === 'published'
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                        : 'bg-amber-50 text-amber-700 border-amber-200'
                                ]"
                            >
                                {{ sop.status === 'published' ? 'Publicado' : 'Borrador' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5 font-mono">
                            slug: {{ sop.slug }} · {{ blocks.length }} bloques definidos
                        </p>
                    </div>
                </div>

                <!-- Indicador de Autoguardado y Botones de Acción -->
                <div class="flex items-center space-x-3 shrink-0">
                    <!-- Estado de Guardado -->
                    <div class="text-xs flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-white border border-slate-200 shadow-2xs">
                        <span v-if="status === 'saving'" class="flex items-center text-amber-600 font-medium">
                            <svg class="animate-spin w-3.5 h-3.5 me-1.5 text-amber-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Guardando borrador...
                        </span>
                        <span v-else-if="status === 'saved'" class="flex items-center text-emerald-600 font-medium">
                            <svg class="w-3.5 h-3.5 me-1 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Guardado ({{ formattedLastSaved }})
                        </span>
                        <span v-else-if="status === 'error'" class="flex items-center text-rose-600 font-medium" :title="errorMessage">
                            <svg class="w-3.5 h-3.5 me-1 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Error al guardar
                        </span>
                        <span v-else class="text-slate-400">
                            {{ hasChanges ? 'Cambios pendientes...' : 'Borrador al día' }}
                        </span>
                    </div>

                    <!-- Botón Guardar Ahora -->
                    <button
                        type="button"
                        @click="saveDraft"
                        :disabled="status === 'saving'"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition shadow-2xs disabled:opacity-50"
                    >
                        Guardar ahora
                    </button>

                    <!-- Botón Publicar -->
                    <button
                        type="button"
                        @click="openPublishModal"
                        class="inline-flex items-center px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Publicar versión
                    </button>
                </div>
            </div>
        </template>

        <!-- Cuerpo del Constructor: Lienzo a la Izquierda y Paleta a la Derecha -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">

            <!-- Lienzo de Bloques (3 columnas en desktop) -->
            <div class="lg:col-span-3 space-y-4">
                <div v-if="blocks.length === 0" class="bg-white border-2 border-dashed border-slate-300 rounded-2xl p-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-800">El lienzo de SOP está vacío</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
                        Selecciona un tipo de bloque en el panel lateral derecho para comenzar a estructurar tu procedimiento operativo.
                    </p>
                    <button
                        type="button"
                        @click="addBlock('heading')"
                        class="inline-flex items-center px-3.5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm transition"
                    >
                        Añadir primer encabezado
                    </button>
                </div>

                <draggable
                    v-model="blocks"
                    item-key="id"
                    handle=".drag-handle"
                    animation="200"
                    ghost-class="opacity-40"
                    class="space-y-4"
                >
                    <template #item="{ element, index }">
                        <component
                            :is="blockComponents[element.type]"
                            :block="element"
                            @update:block="updateBlock(index, $event)"
                            @remove="removeBlock(index)"
                        />
                    </template>
                </draggable>
            </div>

            <!-- Panel Lateral de Bloques Disponibles (1 columna en desktop) -->
            <div class="lg:col-span-1 space-y-4 sticky top-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                        Añadir Bloque
                    </h3>

                    <div class="space-y-4">
                        <div v-for="cat in availableBlockCategories" :key="cat.name" class="space-y-1.5">
                            <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                                {{ cat.name }}
                            </div>

                            <div class="grid grid-cols-1 gap-1.5">
                                <button
                                    v-for="item in cat.items"
                                    :key="item.type"
                                    type="button"
                                    @click="addBlock(item.type)"
                                    class="w-full text-start px-3 py-2 rounded-xl border border-slate-200/80 hover:border-indigo-300 hover:bg-indigo-50/40 transition flex items-center space-x-2.5 group"
                                >
                                    <span class="w-6 h-6 rounded-lg bg-slate-100 group-hover:bg-indigo-100 text-slate-600 group-hover:text-indigo-700 text-xs font-mono font-bold flex items-center justify-center shrink-0">
                                        {{ item.icon }}
                                    </span>
                                    <div class="truncate">
                                        <div class="text-xs font-semibold text-slate-800 group-hover:text-indigo-950 truncate">
                                            {{ item.label }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 truncate">
                                            {{ item.desc }}
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial de Versiones Rápido -->
                <div v-if="versionsHistory.length > 0" class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                        Historial de Versiones
                    </h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        <div
                            v-for="v in versionsHistory"
                            :key="v.id"
                            class="p-2 rounded-lg bg-slate-50 border border-slate-100 text-xs"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-800">v{{ v.version_number }}</span>
                                <span
                                    :class="[
                                        'text-[10px] px-1.5 py-0.2 rounded font-medium',
                                        v.is_draft ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'
                                    ]"
                                >
                                    {{ v.is_draft ? 'Borrador' : 'Publicada' }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1 line-clamp-1">
                                {{ v.changelog || 'Sin notas de versión' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal de Confirmación de Publicación -->
        <div v-if="showPublishModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 space-y-4 border border-slate-100">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">
                            Publicar nueva versión de SOP
                        </h3>
                    </div>
                    <button
                        type="button"
                        @click="showPublishModal = false"
                        class="text-slate-400 hover:text-slate-600 p-1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <p class="text-xs text-slate-600">
                    Al publicar, el backend validará que no existan IDs duplicados, que todas las variables <code v-pre class="bg-slate-100 px-1 py-0.5 rounded font-mono">{{clave}}</code> estén definidas previamente y que no haya referencias hacia adelante.
                </p>

                <!-- Mensaje de Éxito -->
                <div v-if="publishSuccessMessage" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 flex items-center space-x-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span>{{ publishSuccessMessage }}</span>
                </div>

                <!-- Lista de Errores de Validación -->
                <div v-if="publishErrors.length > 0" class="p-3 bg-rose-50 border border-rose-200 rounded-xl space-y-1">
                    <div class="text-xs font-bold text-rose-800 flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>No se puede publicar: Corrige los siguientes errores</span>
                    </div>
                    <ul class="text-xs text-rose-700 list-disc list-inside space-y-0.5">
                        <li v-for="(err, i) in publishErrors" :key="i">
                            {{ err }}
                        </li>
                    </ul>
                </div>

                <!-- Changelog -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Notas de la versión / Changelog
                    </label>
                    <textarea
                        v-model="changelogText"
                        rows="3"
                        placeholder="ej. Se añadieron pasos para configuración de Pixel y tarea de IA para redacción de copys..."
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                    />
                </div>

                <!-- Botones -->
                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="showPublishModal = false"
                        class="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-xl transition"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="handleConfirmPublish"
                        :disabled="isPublishing"
                        class="inline-flex items-center px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-sm disabled:opacity-50"
                    >
                        <svg v-if="isPublishing" class="animate-spin w-3.5 h-3.5 me-1.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Confirmar y Publicar
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

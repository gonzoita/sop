<script setup>
import { computed } from 'vue';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
    readonly: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:block', 'remove']);

const text = computed({
    get: () => props.block.props?.text || '',
    set: (val) => {
        emit('update:block', {
            ...props.block,
            props: {
                ...props.block.props,
                text: val,
            },
        });
    },
});
</script>

<template>
    <div class="bg-white rounded-xl border border-slate-200/90 shadow-xs hover:border-slate-300 transition group">
        <!-- Encabezado del Bloque -->
        <div class="px-4 py-3 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between rounded-t-xl">
            <div class="flex items-center space-x-2.5">
                <div class="drag-handle cursor-grab active:cursor-grabbing p-1 text-slate-400 hover:text-slate-600 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                    Encabezado
                </span>
                <span class="text-xs font-mono text-slate-400">#{{ block.id }}</span>
            </div>

            <button
                v-if="!readonly"
                type="button"
                @click="emit('remove')"
                class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                title="Eliminar bloque"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
        </div>

        <!-- Contenido del Bloque -->
        <div class="p-4">
            <div v-if="!readonly">
                <input
                    type="text"
                    v-model="text"
                    placeholder="Título de la sección o fase (ej. Fase 1: Recolección de Datos)..."
                    class="w-full text-base font-bold text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg placeholder:text-slate-400 placeholder:font-normal"
                />
            </div>
            <div v-else class="text-lg font-bold text-slate-800">
                {{ text || 'Sin título' }}
            </div>
        </div>
    </div>
</template>

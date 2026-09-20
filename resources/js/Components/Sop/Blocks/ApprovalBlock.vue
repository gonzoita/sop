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

const approvalProps = computed(() => props.block.props || {});

const updateProp = (key, value) => {
    emit('update:block', {
        ...props.block,
        props: {
            ...approvalProps.value,
            [key]: value,
        },
    });
};
</script>

<template>
    <div class="bg-white rounded-xl border border-rose-200/90 shadow-xs hover:border-rose-300 transition group ring-1 ring-rose-500/10">
        <!-- Encabezado del Bloque -->
        <div class="px-4 py-3 bg-rose-50/50 border-b border-rose-100 flex items-center justify-between rounded-t-xl">
            <div class="flex items-center space-x-2.5">
                <div class="drag-handle cursor-grab active:cursor-grabbing p-1 text-slate-400 hover:text-slate-600 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-800 border border-rose-200">
                    <svg class="w-3 h-3 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    Punto de Aprobación Humana
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
        <div class="p-4 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Rol responsable <span class="text-rose-500">*</span>
                    </label>
                    <select
                        v-if="!readonly"
                        :value="approvalProps.role || 'editor'"
                        @change="updateProp('role', $event.target.value)"
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    >
                        <option value="editor">Editor</option>
                        <option value="admin">Administrador</option>
                        <option value="ejecutor">Ejecutor</option>
                    </select>
                    <span v-else class="text-xs text-slate-800">{{ approvalProps.role }}</span>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Criterio de aprobación / Instrucciones
                    </label>
                    <input
                        v-if="!readonly"
                        type="text"
                        :value="approvalProps.instructions"
                        @input="updateProp('instructions', $event.target.value)"
                        placeholder="ej. Verificar tono de marca y coherencia del brief antes de continuar..."
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <span v-else class="text-xs text-slate-700">{{ approvalProps.instructions }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

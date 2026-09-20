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
    availableVariables: {
        type: Array,
        default: () => [],
    },
    futureVariables: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:block', 'remove']);

const html = computed({
    get: () => props.block.props?.html || '',
    set: (val) => {
        emit('update:block', {
            ...props.block,
            props: {
                ...props.block.props,
                html: val,
            },
        });
    },
});

// Extract all {{key}} in html
const referencedVariables = computed(() => {
    const matches = html.value.match(/\{\{\s*([a-z0-9_]+)\s*\}\}/g) || [];
    return [...new Set(matches.map(m => m.replace(/[\{\}\s]/g, '')))];
});

// Identify invalid variables and forward references
const variableValidationErrors = computed(() => {
    const errors = [];
    const availableKeys = props.availableVariables.map(v => v.key);
    const futureKeysMap = {};
    props.futureVariables.forEach(v => {
        futureKeysMap[v.key] = v;
    });

    referencedVariables.value.forEach(varKey => {
        if (!availableKeys.includes(varKey)) {
            if (futureKeysMap[varKey]) {
                const future = futureKeysMap[varKey];
                errors.push({
                    key: varKey,
                    type: 'forward_reference',
                    message: `Referencia hacia adelante: {{${varKey}}} se define después en el bloque #${future.blockId}. Muévelo antes de este bloque.`,
                });
            } else {
                errors.push({
                    key: varKey,
                    type: 'undefined',
                    message: `Variable no definida: {{${varKey}}} no existe en los bloques anteriores de este SOP.`,
                });
            }
        }
    });

    return errors;
});

const insertVariable = (varKey) => {
    html.value = html.value ? `${html.value} {{${varKey}}}` : `{{${varKey}}}`;
};
</script>

<template>
    <div class="bg-white rounded-xl border border-slate-200/90 shadow-xs hover:border-slate-300 transition group">
        <!-- Encabezado del Bloque -->
        <div class="px-4 py-3 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between rounded-t-xl">
            <div class="flex items-center space-x-2.5">
                <div class="drag-handle cursor-grab active:cursor-grabbing p-1 text-slate-400 hover:text-slate-600 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                    Texto / Instrucción
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
            <div v-if="!readonly" class="space-y-2">
                <textarea
                    v-model="html"
                    rows="3"
                    placeholder="Instrucciones para el ejecutor o cliente. Puedes usar variables como {{nombre_marca}}..."
                    :class="[
                        'w-full text-sm rounded-lg transition',
                        variableValidationErrors.length > 0
                            ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500 bg-rose-50/20 text-slate-800'
                            : 'border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-slate-700'
                    ]"
                />

                <!-- Errores de variables marcados en rojo en vivo -->
                <div v-if="variableValidationErrors.length > 0" class="space-y-1">
                    <div
                        v-for="(err, i) in variableValidationErrors"
                        :key="i"
                        class="px-2.5 py-1.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center space-x-2"
                    >
                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="font-medium">{{ err.message }}</span>
                    </div>
                </div>

                <!-- Barra de autocompletado rápido de variables -->
                <div v-if="availableVariables.length > 0" class="flex flex-wrap items-center gap-1.5 pt-1">
                    <span class="text-[11px] text-slate-400 font-medium me-1">Insertar variable:</span>
                    <button
                        v-for="v in availableVariables"
                        :key="v.key"
                        type="button"
                        @click="insertVariable(v.key)"
                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-mono bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200/60 transition cursor-pointer"
                        :title="`Insertar {{${v.key}}}`"
                        v-text="'+ {{' + v.key + '}}'"
                    />
                </div>
            </div>

            <div v-else class="text-sm text-slate-700 prose max-w-none" v-html="html || '<em>Sin contenido</em>'" />
        </div>
    </div>
</template>

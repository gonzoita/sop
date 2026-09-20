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

const aiProps = computed(() => props.block.props || {});

const updateProp = (key, value) => {
    emit('update:block', {
        ...props.block,
        props: {
            ...aiProps.value,
            [key]: value,
        },
    });
};

const inputsString = computed({
    get: () => {
        const inp = aiProps.value.inputs || {};
        return Object.entries(inp).map(([k, v]) => `${k}: ${v}`).join('\n');
    },
    set: (val) => {
        const lines = val.split('\n');
        const map = {};
        for (const line of lines) {
            const parts = line.split(':');
            if (parts.length >= 2) {
                const k = parts[0].trim();
                const v = parts.slice(1).join(':').trim();
                if (k) map[k] = v;
            }
        }
        updateProp('inputs', map);
    },
});
</script>

<template>
    <div class="bg-white rounded-xl border border-indigo-200 shadow-xs hover:border-indigo-300 transition group ring-1 ring-indigo-500/10">
        <!-- Encabezado del Bloque -->
        <div class="px-4 py-3 bg-gradient-to-r from-indigo-50/80 to-purple-50/60 border-b border-indigo-100 flex items-center justify-between rounded-t-xl">
            <div class="flex items-center space-x-2.5">
                <div class="drag-handle cursor-grab active:cursor-grabbing p-1 text-indigo-400 hover:text-indigo-600 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-600 text-white shadow-xs">
                    <svg class="w-3 h-3 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    Tarea de IA
                </span>
                <span v-if="aiProps.output_key" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-emerald-100 text-emerald-800" v-text="'Salida: {{' + aiProps.output_key + '}}'" />
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
                <!-- Skill Slug -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Skill de IA <span class="text-rose-500">*</span>
                    </label>
                    <input
                        v-if="!readonly"
                        type="text"
                        :value="aiProps.skill_slug"
                        @input="updateProp('skill_slug', $event.target.value)"
                        placeholder="ej. brief-de-marca"
                        class="w-full text-xs font-mono text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <span v-else class="text-xs font-mono text-slate-800">{{ aiProps.skill_slug }}</span>
                </div>

                <!-- Modo -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Modo de Ejecución
                    </label>
                    <select
                        v-if="!readonly"
                        :value="aiProps.mode || 'internal'"
                        @change="updateProp('mode', $event.target.value)"
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    >
                        <option value="internal">Interno (OpenRouter API)</option>
                        <option value="export">Exportar Markdown (IA externa)</option>
                    </select>
                    <span v-else class="text-xs text-slate-800">{{ aiProps.mode === 'export' ? 'Exportar Markdown' : 'Interno (OpenRouter)' }}</span>
                </div>

                <!-- Modelo -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Modelo de IA <span class="text-rose-500">*</span>
                    </label>
                    <input
                        v-if="!readonly"
                        type="text"
                        :value="aiProps.model || 'openrouter/auto'"
                        @input="updateProp('model', $event.target.value)"
                        placeholder="ej. openrouter/auto o anthropic/claude-3.5-sonnet"
                        class="w-full text-xs font-mono text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <span v-else class="text-xs font-mono text-slate-800">{{ aiProps.model }}</span>
                </div>
            </div>

            <!-- Mapeo de Entradas (inputs) -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                    Variables de Entrada (un par por línea, ej: <code v-pre class="font-mono text-indigo-600">marca: {{nombre_marca}}</code>)
                </label>
                <textarea
                    v-if="!readonly"
                    v-model="inputsString"
                    rows="2"
                    placeholder="marca: {{nombre_marca}}&#10;objetivo: {{objetivo_campana}}"
                    class="w-full text-xs font-mono text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg placeholder:font-sans"
                />
                <pre v-else class="text-xs font-mono bg-slate-50 p-2 rounded">{{ inputsString || 'Sin entradas' }}</pre>
            </div>

            <!-- Output Key y Aprobación -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1 border-t border-slate-100">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Variable de Salida (output_key) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        v-if="!readonly"
                        type="text"
                        :value="aiProps.output_key"
                        @input="updateProp('output_key', $event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))"
                        placeholder="ej. brief_resultado"
                        class="w-full text-xs font-mono text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <span v-else class="text-xs font-mono text-slate-800">{{ aiProps.output_key }}</span>
                </div>

                <div class="flex items-center pt-5">
                    <label v-if="!readonly" class="flex items-center space-x-2 cursor-pointer text-xs text-slate-700">
                        <input
                            type="checkbox"
                            :checked="aiProps.requires_approval !== false"
                            @change="updateProp('requires_approval', $event.target.checked)"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 text-xs"
                        />
                        <span class="font-medium">Requiere aprobación humana antes de continuar</span>
                    </label>
                    <span v-else class="text-xs text-slate-600">
                        {{ aiProps.requires_approval !== false ? 'Requiere aprobación humana' : 'Sin aprobación explícita' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

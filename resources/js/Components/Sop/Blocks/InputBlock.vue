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

const inputProps = computed(() => props.block.props || {});

const updateProp = (key, value) => {
    emit('update:block', {
        ...props.block,
        props: {
            ...inputProps.value,
            [key]: value,
        },
    });
};

const optionsString = computed({
    get: () => (inputProps.value.options || []).join(', '),
    set: (val) => {
        const opts = val.split(',').map(s => s.trim()).filter(Boolean);
        updateProp('options', opts);
    },
});
</script>

<template>
    <div class="bg-white rounded-xl border border-slate-200/90 shadow-xs hover:border-slate-300 transition group">
        <!-- Encabezado del Bloque -->
        <div class="px-4 py-3 bg-amber-50/50 border-b border-amber-100 flex items-center justify-between rounded-t-xl">
            <div class="flex items-center space-x-2.5">
                <div class="drag-handle cursor-grab active:cursor-grabbing p-1 text-slate-400 hover:text-slate-600 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                    Campo de Entrada
                </span>
                <span v-if="inputProps.key" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-medium bg-slate-100 text-slate-700" v-text="'{{' + inputProps.key + '}}'" />
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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <!-- Clave Variable -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Clave de Variable (snake_case) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        v-if="!readonly"
                        type="text"
                        :value="inputProps.key"
                        @input="updateProp('key', $event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))"
                        placeholder="ej. nombre_marca"
                        class="w-full text-xs font-mono text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <span v-else class="text-xs font-mono text-slate-800">{{ inputProps.key }}</span>
                </div>

                <!-- Etiqueta Visible -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Etiqueta visible <span class="text-rose-500">*</span>
                    </label>
                    <input
                        v-if="!readonly"
                        type="text"
                        :value="inputProps.label"
                        @input="updateProp('label', $event.target.value)"
                        placeholder="ej. Nombre de la marca o empresa"
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <span v-else class="text-xs text-slate-800">{{ inputProps.label }}</span>
                </div>

                <!-- Tipo de Campo -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Tipo de dato
                    </label>
                    <select
                        v-if="!readonly"
                        :value="inputProps.field || 'text'"
                        @change="updateProp('field', $event.target.value)"
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    >
                        <option value="text">Texto corto (text)</option>
                        <option value="textarea">Texto largo (textarea)</option>
                        <option value="number">Número (number)</option>
                        <option value="date">Fecha (date)</option>
                        <option value="select">Selección única (select)</option>
                        <option value="multiselect">Selección múltiple (multiselect)</option>
                        <option value="url">Enlace URL (url)</option>
                        <option value="file">Archivo adjunto (file)</option>
                    </select>
                    <span v-else class="text-xs text-slate-800">{{ inputProps.field }}</span>
                </div>

                <!-- Quién llena el campo -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Completado por
                    </label>
                    <select
                        v-if="!readonly"
                        :value="inputProps.filled_by || 'team'"
                        @change="updateProp('filled_by', $event.target.value)"
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    >
                        <option value="team">Equipo interno</option>
                        <option value="client">Cliente externo</option>
                    </select>
                    <span v-else class="text-xs text-slate-800">{{ inputProps.filled_by === 'client' ? 'Cliente externo' : 'Equipo interno' }}</span>
                </div>
            </div>

            <!-- Opciones si es select o multiselect -->
            <div v-if="['select', 'multiselect'].includes(inputProps.field)">
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                    Opciones separadas por coma
                </label>
                <input
                    v-if="!readonly"
                    type="text"
                    v-model="optionsString"
                    placeholder="Opción 1, Opción 2, Opción 3..."
                    class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                />
                <span v-else class="text-xs text-slate-800">{{ optionsString }}</span>
            </div>

            <!-- Texto de ayuda y Obligatorio -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 pt-1 border-t border-slate-100">
                <div class="flex-1 w-full sm:w-auto">
                    <input
                        v-if="!readonly"
                        type="text"
                        :value="inputProps.help"
                        @input="updateProp('help', $event.target.value)"
                        placeholder="Instrucción de ayuda opcional para el usuario..."
                        class="w-full text-[11px] text-slate-600 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md"
                    />
                    <span v-else class="text-xs text-slate-500 italic">{{ inputProps.help }}</span>
                </div>

                <label v-if="!readonly" class="flex items-center space-x-1.5 cursor-pointer text-xs text-slate-600 shrink-0">
                    <input
                        type="checkbox"
                        :checked="inputProps.required !== false"
                        @change="updateProp('required', $event.target.checked)"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 text-xs"
                    />
                    <span>Campo obligatorio</span>
                </label>
            </div>
        </div>
    </div>
</template>

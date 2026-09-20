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

const mediaProps = computed(() => props.block.props || {});

const updateProp = (key, value) => {
    emit('update:block', {
        ...props.block,
        props: {
            ...mediaProps.value,
            [key]: value,
        },
    });
};
</script>

<template>
    <div class="bg-white rounded-xl border border-slate-200/90 shadow-xs hover:border-slate-300 transition group">
        <!-- Encabezado del Bloque -->
        <div class="px-4 py-3 bg-blue-50/50 border-b border-blue-100 flex items-center justify-between rounded-t-xl">
            <div class="flex items-center space-x-2.5">
                <div class="drag-handle cursor-grab active:cursor-grabbing p-1 text-slate-400 hover:text-slate-600 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                    Multimedia / Archivo
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
                        Tipo de medio
                    </label>
                    <select
                        v-if="!readonly"
                        :value="mediaProps.kind || 'video'"
                        @change="updateProp('kind', $event.target.value)"
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    >
                        <option value="video">Video (Loom / YouTube / Vimeo)</option>
                        <option value="image">Imagen de referencia</option>
                        <option value="file">Documento o enlace externo</option>
                    </select>
                    <span v-else class="text-xs text-slate-800">{{ mediaProps.kind }}</span>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        URL del recurso <span class="text-rose-500">*</span>
                    </label>
                    <input
                        v-if="!readonly"
                        type="url"
                        :value="mediaProps.url"
                        @input="updateProp('url', $event.target.value)"
                        placeholder="https://www.loom.com/share/... o https://..."
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <a v-else-if="mediaProps.url" :href="mediaProps.url" target="_blank" class="text-xs text-indigo-600 underline">
                        {{ mediaProps.url }}
                    </a>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                    Descripción o subtítulo
                </label>
                <input
                    v-if="!readonly"
                    type="text"
                    :value="mediaProps.caption"
                    @input="updateProp('caption', $event.target.value)"
                    placeholder="ej. Cómo solicitar accesos al Business Manager de Meta..."
                    class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                />
                <span v-else class="text-xs text-slate-600">{{ mediaProps.caption }}</span>
            </div>
        </div>
    </div>
</template>

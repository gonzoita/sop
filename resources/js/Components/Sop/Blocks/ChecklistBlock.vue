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

const items = computed({
    get: () => props.block.props?.items || [],
    set: (newItems) => {
        emit('update:block', {
            ...props.block,
            props: {
                ...props.block.props,
                items: newItems,
            },
        });
    },
});

const addItem = () => {
    const nextId = 'i' + (items.value.length + 1) + '_' + Math.random().toString(36).substring(2, 6);
    items.value = [
        ...items.value,
        { id: nextId, text: '', required: true },
    ];
};

const updateItem = (index, key, value) => {
    const updated = [...items.value];
    updated[index] = { ...updated[index], [key]: value };
    items.value = updated;
};

const removeItem = (index) => {
    const updated = items.value.filter((_, i) => i !== index);
    items.value = updated;
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
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                    Checklist
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
            <div v-if="items.length === 0" class="text-xs text-slate-400 italic py-1">
                No hay elementos en la lista de verificación.
            </div>

            <div v-for="(item, index) in items" :key="item.id" class="flex items-center space-x-2">
                <div class="p-1 text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>

                <div class="flex-1">
                    <input
                        v-if="!readonly"
                        type="text"
                        :value="item.text"
                        @input="updateItem(index, 'text', $event.target.value)"
                        placeholder="Descripción de la tarea a verificar..."
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <span v-else class="text-xs text-slate-800">{{ item.text }}</span>
                </div>

                <label v-if="!readonly" class="flex items-center space-x-1.5 cursor-pointer text-xs text-slate-500 hover:text-slate-700">
                    <input
                        type="checkbox"
                        :checked="item.required"
                        @change="updateItem(index, 'required', $event.target.checked)"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 text-xs"
                    />
                    <span class="text-[11px]">Obligatorio</span>
                </label>

                <button
                    v-if="!readonly"
                    type="button"
                    @click="removeItem(index)"
                    class="p-1 text-slate-300 hover:text-rose-500 rounded transition"
                    title="Eliminar ítem"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div v-if="!readonly" class="pt-1">
                <button
                    type="button"
                    @click="addItem"
                    class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition border border-emerald-200/50"
                >
                    <svg class="w-3 h-3 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Añadir verificación
                </button>
            </div>
        </div>
    </div>
</template>

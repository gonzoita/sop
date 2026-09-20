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

const decisionProps = computed(() => props.block.props || {});
const branches = computed(() => decisionProps.value.branches || []);

const updateProp = (key, value) => {
    emit('update:block', {
        ...props.block,
        props: {
            ...decisionProps.value,
            [key]: value,
        },
    });
};

const addBranch = () => {
    updateProp('branches', [
        ...branches.value,
        { label: '', goto: '' },
    ]);
};

const updateBranch = (index, key, val) => {
    const updated = [...branches.value];
    updated[index] = { ...updated[index], [key]: val };
    updateProp('branches', updated);
};

const removeBranch = (index) => {
    const updated = branches.value.filter((_, i) => i !== index);
    updateProp('branches', updated);
};
</script>

<template>
    <div class="bg-white rounded-xl border border-slate-200/90 shadow-xs hover:border-slate-300 transition group">
        <!-- Encabezado del Bloque -->
        <div class="px-4 py-3 bg-purple-50/50 border-b border-purple-100 flex items-center justify-between rounded-t-xl">
            <div class="flex items-center space-x-2.5">
                <div class="drag-handle cursor-grab active:cursor-grabbing p-1 text-slate-400 hover:text-slate-600 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-purple-100 text-purple-800 border border-purple-200">
                    Bifurcación / Decisión
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
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                    Pregunta condicional <span class="text-rose-500">*</span>
                </label>
                <input
                    v-if="!readonly"
                    type="text"
                    :value="decisionProps.question"
                    @input="updateProp('question', $event.target.value)"
                    placeholder="ej. ¿El cliente ya tiene Business Manager configurado?"
                    class="w-full text-xs font-medium text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                />
                <span v-else class="text-xs font-medium text-slate-800">{{ decisionProps.question }}</span>
            </div>

            <!-- Ramas -->
            <div class="space- salt-y-2">
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1.5">
                    Opciones y saltos de rama
                </label>

                <div v-for="(branch, index) in branches" :key="index" class="flex items-center space-x-2 mb-2">
                    <input
                        v-if="!readonly"
                        type="text"
                        :value="branch.label"
                        @input="updateBranch(index, 'label', $event.target.value)"
                        placeholder="ej. Sí / No / Otra opción"
                        class="flex-1 text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <span v-else class="flex-1 text-xs text-slate-700 font-medium">{{ branch.label }}</span>

                    <span class="text-xs text-slate-400">→ Saltar a:</span>

                    <input
                        v-if="!readonly"
                        type="text"
                        :value="branch.goto"
                        @input="updateBranch(index, 'goto', $event.target.value)"
                        placeholder="ID bloque (ej. b5)"
                        class="w-28 text-xs font-mono text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <span v-else class="text-xs font-mono text-slate-600">#{{ branch.goto || 'siguiente' }}</span>

                    <button
                        v-if="!readonly"
                        type="button"
                        @click="removeBranch(index)"
                        class="p-1 text-slate-300 hover:text-rose-500 rounded transition"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div v-if="!readonly">
                    <button
                        type="button"
                        @click="addBranch"
                        class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 transition border border-purple-200/50"
                    >
                        <svg class="w-3 h-3 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        Añadir opción
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

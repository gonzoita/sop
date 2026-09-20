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

const handoffProps = computed(() => props.block.props || {});

const updateProp = (key, value) => {
    emit('update:block', {
        ...props.block,
        props: {
            ...handoffProps.value,
            [key]: value,
        },
    });
};
</script>

<template>
    <div class="bg-white rounded-xl border border-teal-200/90 shadow-xs hover:border-teal-300 transition group ring-1 ring-teal-500/10">
        <!-- Encabezado del Bloque -->
        <div class="px-4 py-3 bg-teal-50/50 border-b border-teal-100 flex items-center justify-between rounded-t-xl">
            <div class="flex items-center space-x-2.5">
                <div class="drag-handle cursor-grab active:cursor-grabbing p-1 text-slate-400 hover:text-slate-600 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-teal-100 text-teal-800 border border-teal-200">
                    <svg class="w-3 h-3 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    Entrega / Handoff
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
                        Entregar control a <span class="text-rose-500">*</span>
                    </label>
                    <select
                        v-if="!readonly"
                        :value="handoffProps.to || 'client'"
                        @change="updateProp('to', $event.target.value)"
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    >
                        <option value="client">Cliente (Portal externo)</option>
                        <option value="team">Equipo de la agencia</option>
                    </select>
                    <span v-else class="text-xs text-slate-800">{{ handoffProps.to === 'client' ? 'Cliente' : 'Equipo' }}</span>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">
                        Mensaje explicativo (admite variables)
                    </label>
                    <input
                        v-if="!readonly"
                        type="text"
                        :value="handoffProps.message"
                        @input="updateProp('message', $event.target.value)"
                        placeholder="ej. Por favor revisa el brief generado y confirma para iniciar la campaña..."
                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg"
                    />
                    <span v-else class="text-xs text-slate-700">{{ handoffProps.message }}</span>
                </div>
            </div>

            <div class="pt-1">
                <label v-if="!readonly" class="flex items-center space-x-2 cursor-pointer text-xs text-slate-700">
                    <input
                        type="checkbox"
                        :checked="handoffProps.notify !== false"
                        @change="updateProp('notify', $event.target.checked)"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 text-xs"
                    />
                    <span>Enviar notificación por correo/portal a la parte receptora</span>
                </label>
                <span v-else class="text-xs text-slate-500">
                    {{ handoffProps.notify !== false ? 'Notificación activa' : 'Sin notificación' }}
                </span>
            </div>
        </div>
    </div>
</template>

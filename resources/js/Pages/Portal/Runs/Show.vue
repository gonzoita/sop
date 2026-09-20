<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import axios from 'axios';

const props = defineProps({
    run: Object,
    blocks: Array,
    steps: Object,
    inputs: Object,
});

// Almacén reactivo de valores locales
const localValues = ref({ ...props.inputs });
const uploadingBlockId = ref(null);
const uploadError = ref(null);
const savingStepId = ref(null);

// Mapeo de steps por block_id
const getStepForBlock = (blockId) => {
    return props.steps[blockId] || null;
};

// Progreso de llenado
const completedCount = computed(() => {
    let count = 0;
    props.blocks.forEach((b) => {
        const step = getStepForBlock(b.id);
        if (step && (step.status === 'completed' || step.status === 'running' || localValues.value[b.props.key])) {
            count++;
        }
    });
    return count;
});

const progressPercent = computed(() => {
    if (!props.blocks || props.blocks.length === 0) return 100;
    return Math.round((completedCount.value / props.blocks.length) * 100);
});

// Guardar valor de un input estándar
const saveStepValue = (block) => {
    const step = getStepForBlock(block.id);
    if (!step) return;

    savingStepId.value = step.id;
    const value = localValues.value[block.props.key];

    router.post(route('portal.runs.steps.advance', { run: props.run.id, step: step.id }), {
        value: value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            savingStepId.value = null;
        },
    });
};

// Subida de archivo
const handleFileUpload = async (event, block) => {
    const file = event.target.files[0];
    if (!file) return;

    uploadingBlockId.value = block.id;
    uploadError.value = null;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('block_id', block.id);

    try {
        const response = await axios.post(route('portal.runs.upload', { run: props.run.id }), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const fileData = {
            id: response.data.id,
            name: response.data.original_name,
            path: response.data.file_path,
            download_url: response.data.download_url,
        };

        localValues.value[block.props.key] = fileData;

        // Guardar el paso inmediatamente con los metadatos del archivo
        const step = getStepForBlock(block.id);
        if (step) {
            router.post(route('portal.runs.steps.advance', { run: props.run.id, step: step.id }), {
                value: fileData,
            }, {
                preserveScroll: true,
            });
        }
    } catch (err) {
        uploadError.value = err.response?.data?.message || 'Error al subir el archivo. Verifica el formato y tamaño (máx. 10MB).';
    } finally {
        uploadingBlockId.value = null;
    }
};
</script>

<template>
    <ClientPortalLayout :title="run.title + ' - Portal de Cliente'">
        <div class="max-w-3xl mx-auto space-y-6">
            <!-- Breadcrumbs / Top navigation -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route('portal.runs.index')"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    <span>Volver a mis procedimientos</span>
                </Link>
                <span class="text-xs text-slate-400">ID: #{{ run.id }}</span>
            </div>

            <!-- Header Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-amber-600">Requerimiento de Información</span>
                        <h1 class="text-xl font-bold text-slate-900 mt-0.5">{{ run.title }}</h1>
                        <p class="text-xs text-slate-500 mt-1">Organización: <strong class="text-slate-700">{{ run.client_name }}</strong></p>
                    </div>
                    <div class="text-right flex flex-col items-end">
                        <span class="text-xs font-semibold text-slate-700">{{ completedCount }} de {{ blocks.length }} completados</span>
                        <div class="w-32 bg-slate-100 rounded-full h-2 mt-1.5 overflow-hidden border border-slate-200">
                            <div class="bg-amber-500 h-2 rounded-full transition-all duration-300" :style="{ width: progressPercent + '%' }"></div>
                        </div>
                    </div>
                </div>

                <!-- Alert if all completed -->
                <div v-if="progressPercent === 100 && blocks.length > 0" class="mt-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>¡Excelente! Has completado todos los campos solicitados para este procedimiento.</span>
                </div>
            </div>

            <!-- Upload Error Alert -->
            <div v-if="uploadError" class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-medium flex items-center justify-between">
                <span>{{ uploadError }}</span>
                <button @click="uploadError = null" class="text-red-500 hover:text-red-700">&times;</button>
            </div>

            <!-- Empty Blocks Alert -->
            <div v-if="!blocks || blocks.length === 0" class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-500 text-sm">
                No hay campos pendientes asignados a tu usuario para este procedimiento.
            </div>

            <!-- Client Blocks List -->
            <div v-else class="space-y-4">
                <div
                    v-for="(block, index) in blocks"
                    :key="block.id"
                    class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-3"
                >
                    <!-- Block Header -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-600 text-xs font-bold flex items-center justify-center">
                                    {{ index + 1 }}
                                </span>
                                <h3 class="text-sm font-bold text-slate-900">
                                    {{ block.props.label || block.props.key }}
                                </h3>
                                <span v-if="block.props.required" class="text-[10px] font-bold text-red-600 uppercase">
                                    *Requerido
                                </span>
                            </div>
                            <p v-if="block.props.help" class="text-xs text-slate-500 mt-1 pl-7">
                                {{ block.props.help }}
                            </p>
                        </div>

                        <!-- Status badge of the step -->
                        <span
                            v-if="localValues[block.props.key]"
                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full"
                        >
                            <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Guardado
                        </span>
                    </div>

                    <!-- Input Fields depending on field type -->
                    <div class="pl-7 pt-1">
                        <!-- Text / Number / Date / URL -->
                        <div v-if="['text', 'number', 'date', 'url'].includes(block.props.field)" class="flex gap-2">
                            <input
                                :type="block.props.field"
                                v-model="localValues[block.props.key]"
                                class="flex-1 rounded-lg border-slate-300 text-sm focus:ring-amber-500 focus:border-amber-500 shadow-sm"
                                :placeholder="block.props.placeholder || 'Escribe aquí...'"
                            />
                            <button
                                @click="saveStepValue(block)"
                                :disabled="savingStepId === getStepForBlock(block.id)?.id"
                                type="button"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors disabled:opacity-50 flex items-center gap-1"
                            >
                                <span v-if="savingStepId === getStepForBlock(block.id)?.id">Guardando...</span>
                                <span v-else>Guardar</span>
                            </button>
                        </div>

                        <!-- Textarea -->
                        <div v-else-if="block.props.field === 'textarea'" class="space-y-2">
                            <textarea
                                v-model="localValues[block.props.key]"
                                rows="4"
                                class="w-full rounded-lg border-slate-300 text-sm focus:ring-amber-500 focus:border-amber-500 shadow-sm"
                                :placeholder="block.props.placeholder || 'Detalla la información aquí...'"
                            ></textarea>
                            <div class="flex justify-end">
                                <button
                                    @click="saveStepValue(block)"
                                    :disabled="savingStepId === getStepForBlock(block.id)?.id"
                                    type="button"
                                    class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors disabled:opacity-50"
                                >
                                    {{ savingStepId === getStepForBlock(block.id)?.id ? 'Guardando...' : 'Guardar' }}
                                </button>
                            </div>
                        </div>

                        <!-- Select -->
                        <div v-else-if="block.props.field === 'select'" class="flex gap-2">
                            <select
                                v-model="localValues[block.props.key]"
                                class="flex-1 rounded-lg border-slate-300 text-sm focus:ring-amber-500 focus:border-amber-500 shadow-sm"
                            >
                                <option value="">Selecciona una opción...</option>
                                <option
                                    v-for="opt in block.props.options || []"
                                    :key="opt"
                                    :value="opt"
                                >
                                    {{ opt }}
                                </option>
                            </select>
                            <button
                                @click="saveStepValue(block)"
                                :disabled="savingStepId === getStepForBlock(block.id)?.id"
                                type="button"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors disabled:opacity-50"
                            >
                                Guardar
                            </button>
                        </div>

                        <!-- File Upload -->
                        <div v-else-if="block.props.field === 'file'" class="space-y-2">
                            <!-- Si ya hay archivo subido -->
                            <div
                                v-if="localValues[block.props.key] && localValues[block.props.key].name"
                                class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between"
                            >
                                <div class="flex items-center gap-2 text-xs">
                                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="font-medium text-slate-800">{{ localValues[block.props.key].name }}</span>
                                </div>
                                <a
                                    v-if="localValues[block.props.key].download_url"
                                    :href="localValues[block.props.key].download_url"
                                    class="text-xs font-semibold text-amber-600 hover:text-amber-800 flex items-center gap-1"
                                    target="_blank"
                                >
                                    <span>Descargar</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </a>
                            </div>

                            <!-- Input selector para subir o reemplazar -->
                            <div class="flex items-center gap-3">
                                <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-xs font-medium text-slate-700 shadow-sm transition-colors">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <span>{{ localValues[block.props.key] ? 'Reemplazar archivo' : 'Adjuntar archivo' }}</span>
                                    <input
                                        type="file"
                                        class="hidden"
                                        @change="handleFileUpload($event, block)"
                                        :disabled="uploadingBlockId === block.id"
                                    />
                                </label>
                                <span v-if="uploadingBlockId === block.id" class="text-xs text-amber-600 font-medium animate-pulse">
                                    Subiendo archivo seguro...
                                </span>
                                <span class="text-[11px] text-slate-400">PDF, Office, imágenes, ZIP (máx. 10MB)</span>
                            </div>
                        </div>

                        <!-- Fallback / Otros -->
                        <div v-else class="flex gap-2">
                            <input
                                type="text"
                                v-model="localValues[block.props.key]"
                                class="flex-1 rounded-lg border-slate-300 text-sm"
                            />
                            <button
                                @click="saveStepValue(block)"
                                type="button"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold"
                            >
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ClientPortalLayout>
</template>

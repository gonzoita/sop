<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

import axios from 'axios';

const props = defineProps({
    skill: Object,
    versions: Array,
});

const selectedVersion = ref(props.versions?.[0] || null);

// Modal para publicar nueva versión
const showPublishModal = ref(false);
const publishForm = useForm({
    instructions: props.skill?.current_version?.instructions || '',
    changelog: '',
});

const openPublishModal = () => {
    publishForm.instructions = selectedVersion.value?.instructions || props.skill?.current_version?.instructions || '';
    publishForm.changelog = '';
    showPublishModal.value = true;
};

const submitPublish = () => {
    publishForm.post(route('skills.publish-version', props.skill.id), {
        onSuccess: () => {
            showPublishModal.value = false;
            publishForm.reset();
            // Actualizar vista con la versión más reciente
            selectedVersion.value = props.versions?.[0] || null;
        },
    });
};

// Modal y estado de Importación con Diff (Vía B)
const showImportModal = ref(false);
const importStep = ref('input'); // 'input' | 'preview'
const importContent = ref('');
const importFile = ref(null);
const isPreviewLoading = ref(false);
const previewError = ref('');
const previewData = ref(null);
const confirmChangelog = ref('');
const isConfirming = ref(false);
const confirmError = ref('');

const openImportModal = () => {
    importStep.value = 'input';
    importContent.value = '';
    importFile.value = null;
    previewError.value = '';
    previewData.value = null;
    confirmChangelog.value = '';
    confirmError.value = '';
    showImportModal.value = true;
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;
    importFile.value = file;

    const reader = new FileReader();
    reader.onload = (e) => {
        importContent.value = e.target.result;
    };
    reader.readAsText(file);
};

const requestPreview = async () => {
    previewError.value = '';
    isPreviewLoading.value = true;

    try {
        const formData = new FormData();
        if (importFile.value) {
            formData.append('file', importFile.value);
        } else {
            formData.append('content', importContent.value);
        }

        const response = await axios.post(route('skills.import.preview', props.skill.id), formData);
        previewData.value = response.data;
        confirmChangelog.value = '';
        importStep.value = 'preview';
    } catch (err) {
        if (err.response?.data?.message) {
            previewError.value = err.response.data.message;
        } else {
            previewError.value = 'Error al procesar el archivo. Revisa que sea un Markdown válido.';
        }
    } finally {
        isPreviewLoading.value = false;
    }
};

const submitConfirm = async () => {
    if (!confirmChangelog.value || confirmChangelog.value.trim().length < 3) {
        confirmError.value = 'El campo notas del cambio (changelog) es obligatorio (mínimo 3 caracteres).';
        return;
    }

    confirmError.value = '';
    isConfirming.value = true;

    try {
        await axios.post(route('skills.import.confirm', props.skill.id), {
            base_version_id: previewData.value.base_version_id,
            content: previewData.value.proposed.raw_body,
            changelog: confirmChangelog.value.trim(),
        });

        showImportModal.value = false;
        router.reload({
            preserveScroll: true,
            onSuccess: () => {
                selectedVersion.value = props.versions?.[0] || null;
            }
        });
    } catch (err) {
        if (err.response?.data?.message) {
            confirmError.value = err.response.data.message;
        } else {
            confirmError.value = 'Error al confirmar la importación.';
        }
    } finally {
        isConfirming.value = false;
    }
};

const cancelImport = () => {
    showImportModal.value = false;
    importStep.value = 'input';
    previewData.value = null;
    previewError.value = '';
    confirmError.value = '';
};

const setCurrentVersion = (version) => {
    router.post(route('skills.set-current', { skill: props.skill.id, version: version.id }), {}, {
        preserveScroll: true,
    });
};

const getSourceBadge = (source) => {
    switch (source) {
        case 'manual':
            return { label: 'Manual', class: 'bg-blue-50 text-blue-700 border-blue-200' };
        case 'openrouter':
            return { label: 'OpenRouter', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
        case 'import_markdown':
            return { label: 'Vía B (Markdown)', class: 'bg-purple-50 text-purple-700 border-purple-200' };
        default:
            return { label: source, class: 'bg-slate-50 text-slate-700 border-slate-200' };
    }
};
</script>

<template>
    <AppLayout :title="`${skill.name} - Skill de IA`">
        <div class="space-y-6">
            <!-- Header con Breadcrumbs y Acciones -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                        <Link :href="route('skills.index')" class="hover:text-indigo-600 transition-colors">Skills de IA</Link>
                        <span>/</span>
                        <span class="text-slate-900 font-medium">{{ skill.slug }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ skill.name }}</h1>
                        <span class="px-2.5 py-0.5 rounded text-xs font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                            v{{ skill.current_version?.version_number || 1 }} vigente
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ skill.description || 'Sin descripción.' }}
                    </p>
                </div>

                <div class="flex items-center flex-wrap gap-2">
                    <a
                        :href="route('skills.export.markdown', skill.id)"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 shadow-sm transition-colors"
                        title="Descargar archivo Markdown con front-matter para editar en ChatGPT / Claude"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Exportar Markdown (Vía B)</span>
                    </a>

                    <button
                        @click="openImportModal"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 shadow-sm transition-colors"
                    >
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span>Importar Markdown</span>
                    </button>

                    <button
                        @click="openPublishModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Nueva Versión</span>
                    </button>
                </div>
            </div>

            <!-- Grid: Visualizador de Instrucciones y Selector de Versiones -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Columna Izquierda: Instrucciones y Variables de la Versión Seleccionada -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-900 text-sm">
                                        Instrucciones de Versión v{{ selectedVersion?.version_number }}
                                    </span>
                                    <span
                                        v-if="selectedVersion?.is_current"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200"
                                    >
                                        Vigente
                                    </span>
                                    <span
                                        :class="['px-2 py-0.5 rounded text-[10px] font-medium border', getSourceBadge(selectedVersion?.source).class]"
                                    >
                                        {{ getSourceBadge(selectedVersion?.source).label }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">
                                    {{ selectedVersion?.changelog || 'Sin notas de cambio' }} • Por {{ selectedVersion?.creator_name }} el {{ selectedVersion?.created_at }}
                                </p>
                            </div>

                            <button
                                v-if="!selectedVersion?.is_current"
                                @click="setCurrentVersion(selectedVersion)"
                                class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-lg border border-indigo-200 transition-colors"
                            >
                                Establecer como Vigente
                            </button>
                        </div>

                        <!-- Variables detectadas -->
                        <div class="mb-4">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                                Variables dinámicas en esta versión
                            </span>
                            <div v-if="selectedVersion?.variables && selectedVersion.variables.length > 0" class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="v in selectedVersion.variables"
                                    :key="v"
                                    v-text="'{{' + v + '}}'"
                                    class="px-2 py-0.5 rounded text-[11px] font-mono bg-amber-50 text-amber-800 border border-amber-200"
                                ></span>
                            </div>
                            <span v-else class="text-xs text-slate-400 italic">No contiene variables dinámicas &#123;&#123;clave&#125;&#125;.</span>
                        </div>

                        <!-- Contenido de las instrucciones -->
                        <div>
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                                Prompt del Sistema (Instrucciones)
                            </span>
                            <pre class="w-full p-4 bg-slate-900 text-slate-100 rounded-xl font-mono text-xs whitespace-pre-wrap leading-relaxed overflow-x-auto border border-slate-800">{{ selectedVersion?.instructions }}</pre>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Historial de Versiones -->
                <div class="lg:col-span-1 space-y-4">
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4">
                            Historial de Versiones
                        </h2>

                        <div class="space-y-3 divide-y divide-slate-100">
                            <div
                                v-for="v in versions"
                                :key="v.id"
                                @click="selectedVersion = v"
                                class="pt-3 first:pt-0 cursor-pointer p-3 rounded-xl transition-all"
                                :class="[
                                    selectedVersion?.id === v.id
                                        ? 'bg-indigo-50/80 border border-indigo-200'
                                        : 'hover:bg-slate-50 border border-transparent'
                                ]"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-slate-900">v{{ v.version_number }}</span>
                                        <span
                                            v-if="v.is_current"
                                            class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-wider"
                                        >
                                            Vigente
                                        </span>
                                    </div>
                                    <span :class="['px-1.5 py-0.5 rounded text-[10px] border', getSourceBadge(v.source).class]">
                                        {{ getSourceBadge(v.source).label }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 line-clamp-1 mb-1 font-medium">
                                    {{ v.changelog || 'Sin cambios documentados' }}
                                </p>
                                <div class="flex items-center justify-between text-[10px] text-slate-400">
                                    <span>{{ v.creator_name }}</span>
                                    <span>{{ v.created_at }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Publicar Nueva Versión -->
            <DialogModal :show="showPublishModal" @close="showPublishModal = false" max-width="3xl">
                <template #title>
                    Publicar Nueva Versión del Skill
                </template>

                <template #content>
                    <div class="space-y-4">
                        <p class="text-xs text-slate-500">
                            Las modificaciones crean una nueva versión inmutable. Los SOPs que referencien este skill usarán la nueva versión inmediatamente tras publicarla.
                        </p>

                        <div>
                            <InputLabel for="pub_changelog" value="Notas de Cambio (Changelog)" class="text-xs font-semibold text-slate-700" />
                            <TextInput
                                id="pub_changelog"
                                v-model="publishForm.changelog"
                                type="text"
                                placeholder="Ej: Mejorado el tono de voz para mayor persuasión y estructura de bullet points..."
                                class="mt-1 w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            />
                            <InputError :message="publishForm.errors.changelog" class="mt-1 text-xs" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel for="pub_instructions" value="Instrucciones del Sistema" class="text-xs font-semibold text-slate-700" />
                                <span class="text-[11px] text-indigo-600 font-mono">Usa &#123;&#123;variable&#125;&#125;</span>
                            </div>
                            <textarea
                                id="pub_instructions"
                                v-model="publishForm.instructions"
                                rows="12"
                                class="mt-1 w-full font-mono text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            ></textarea>
                            <InputError :message="publishForm.errors.instructions" class="mt-1 text-xs" />
                        </div>
                    </div>
                </template>

                <template #footer>
                    <SecondaryButton @click="showPublishModal = false" class="!rounded-xl text-xs uppercase tracking-wider mr-2">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        @click="submitPublish"
                        :disabled="publishForm.processing"
                        class="!rounded-xl text-xs uppercase tracking-wider"
                    >
                        Publicar Versión
                    </PrimaryButton>
                </template>
            </DialogModal>

            <!-- Modal Importar Markdown con Diff (Vía B) -->
            <DialogModal :show="showImportModal" @close="cancelImport" max-width="3xl">
                <template #title>
                    <div class="flex items-center justify-between">
                        <span class="text-base font-bold text-slate-900">
                            {{ importStep === 'preview' ? 'Revisión de Cambios (Diff de Skill)' : 'Importar Markdown Externo (Vía B)' }}
                        </span>
                        <span v-if="importStep === 'preview'" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-purple-100 text-purple-800 border border-purple-200">
                            Paso 2 de 2: Confirmación
                        </span>
                        <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                            Paso 1 de 2: Ingesta
                        </span>
                    </div>
                </template>

                <template #content>
                    <!-- PASO 1: Ingesta de archivo o texto -->
                    <div v-if="importStep === 'input'" class="space-y-4">
                        <p class="text-xs text-slate-500">
                            Sube o pega el archivo Markdown trabajado en ChatGPT, Claude o Gemini. Se validará el encabezado front-matter y se mostrará un diff detallado antes de guardar cualquier cambio.
                        </p>

                        <!-- Error de validación -->
                        <div v-if="previewError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 flex items-start gap-2">
                            <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>{{ previewError }}</span>
                        </div>

                        <div>
                            <InputLabel value="Subir archivo .md (Máx. 200 KB)" class="text-xs font-semibold text-slate-700 mb-1" />
                            <input
                                type="file"
                                accept=".md,.txt"
                                @change="handleFileUpload"
                                class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                        </div>

                        <div>
                            <InputLabel for="imp_content" value="O Pega el Contenido Markdown con Front-Matter" class="text-xs font-semibold text-slate-700" />
                            <textarea
                                id="imp_content"
                                v-model="importContent"
                                rows="8"
                                placeholder="---&#10;tipo: skill&#10;slug: brief-de-marca&#10;version: 1&#10;---&#10;&#10;## Instrucciones del Sistema&#10;...&#10;&#10;## Prompt a Ejecutar&#10;..."
                                class="mt-1 w-full font-mono text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                        </div>
                    </div>

                    <!-- PASO 2: Vista previa con Diff -->
                    <div v-else-if="importStep === 'preview' && previewData" class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
                        <!-- Alertas de versión o SOPs -->
                        <div v-if="previewData.warning" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-start gap-2">
                            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <span>{{ previewData.warning }}</span>
                        </div>

                        <div v-if="Object.keys(previewData.removed_variables_in_sops || {}).length > 0" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-900 space-y-1">
                            <div class="font-bold flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Advertencia: Variables eliminadas están en uso por SOPs publicados</span>
                            </div>
                            <p class="text-[11px] text-rose-700">
                                Al eliminar estas variables, los siguientes SOPs no podrán inyectar datos a este skill:
                            </p>
                            <ul class="list-disc list-inside text-[11px] font-mono mt-1 space-y-0.5">
                                <li v-for="(sops, v) in previewData.removed_variables_in_sops" :key="v">
                                    <strong v-text="'{{' + v + '}}'"></strong> requerida en: {{ sops.join(', ') }}
                                </li>
                            </ul>
                        </div>

                        <!-- Resumen estadístico del Diff -->
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex flex-wrap items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-slate-700">Líneas:</span>
                                <span class="px-2 py-0.5 rounded font-mono font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    +{{ previewData.diff.total_added }} líneas
                                </span>
                                <span class="px-2 py-0.5 rounded font-mono font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                    -{{ previewData.diff.total_removed }} líneas
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-slate-500 font-medium">Versión Base:</span>
                                <span class="font-bold text-slate-800">v{{ previewData.base_version_number }}</span>
                                <span class="text-slate-400">→</span>
                                <span class="text-slate-500 font-medium">Nueva Versión:</span>
                                <span class="font-bold text-indigo-700">v{{ previewData.base_version_number + 1 }}</span>
                            </div>
                        </div>

                        <!-- Variables agregadas / eliminadas -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 block">
                                    Variables Nuevas (+{{ previewData.added_variables.length }})
                                </span>
                                <div v-if="previewData.added_variables.length > 0" class="flex flex-wrap gap-1">
                                    <span v-for="v in previewData.added_variables" :key="v" v-text="'{{' + v + '}}'" class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-emerald-50 text-emerald-800 border border-emerald-200"></span>
                                </div>
                                <span v-else class="text-[11px] text-slate-400 italic">Ninguna</span>
                            </div>

                            <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-rose-700 block">
                                    Variables Eliminadas (-{{ previewData.removed_variables.length }})
                                </span>
                                <div v-if="previewData.removed_variables.length > 0" class="flex flex-wrap gap-1">
                                    <span v-for="v in previewData.removed_variables" :key="v" v-text="'{{' + v + '}}'" class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-rose-50 text-rose-800 border border-rose-200"></span>
                                </div>
                                <span v-else class="text-[11px] text-slate-400 italic">Ninguna</span>
                            </div>
                        </div>

                        <!-- Diff de System Prompt (si existe) -->
                        <div v-if="previewData.diff.system_prompt.lines.length > 0" class="space-y-1">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-700 block">
                                Diff: Instrucciones del Sistema (System Prompt)
                            </span>
                            <div class="p-3 bg-slate-900 rounded-xl font-mono text-xs overflow-x-auto max-h-56 divide-y divide-slate-800">
                                <div
                                    v-for="(line, idx) in previewData.diff.system_prompt.lines"
                                    :key="idx"
                                    :class="[
                                        'px-2 py-0.5 flex items-start gap-2 whitespace-pre-wrap',
                                        line.type === 'added' ? 'bg-emerald-950/60 text-emerald-300 font-semibold' : (line.type === 'removed' ? 'bg-rose-950/60 text-rose-300 font-semibold line-through' : 'text-slate-400')
                                    ]"
                                >
                                    <span class="select-none w-4 shrink-0 opacity-60 text-center font-bold">
                                        {{ line.type === 'added' ? '+' : (line.type === 'removed' ? '-' : ' ') }}
                                    </span>
                                    <span v-text="line.text" class="flex-1"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Diff de User Instructions / Prompt -->
                        <div class="space-y-1">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-700 block">
                                Diff: Prompt a Ejecutar (Instrucciones de Usuario)
                            </span>
                            <div class="p-3 bg-slate-900 rounded-xl font-mono text-xs overflow-x-auto max-h-72 divide-y divide-slate-800">
                                <div
                                    v-for="(line, idx) in previewData.diff.user_instructions.lines"
                                    :key="idx"
                                    :class="[
                                        'px-2 py-0.5 flex items-start gap-2 whitespace-pre-wrap',
                                        line.type === 'added' ? 'bg-emerald-950/60 text-emerald-300 font-semibold' : (line.type === 'removed' ? 'bg-rose-950/60 text-rose-300 font-semibold line-through' : 'text-slate-400')
                                    ]"
                                >
                                    <span class="select-none w-4 shrink-0 opacity-60 text-center font-bold">
                                        {{ line.type === 'added' ? '+' : (line.type === 'removed' ? '-' : ' ') }}
                                    </span>
                                    <span v-text="line.text" class="flex-1"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Campo Obligatorio de Changelog -->
                        <div class="pt-2">
                            <InputLabel for="confirm_changelog" value="Notas del cambio / Changelog (Obligatorio) *" class="text-xs font-bold text-slate-800" />
                            <TextInput
                                id="confirm_changelog"
                                v-model="confirmChangelog"
                                type="text"
                                placeholder="Ej: Ajuste de tono persuasivo y adición de variable de oferta en Claude 3.5"
                                class="mt-1 w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            />
                            <p v-if="confirmError" class="text-xs text-rose-600 mt-1 font-semibold">{{ confirmError }}</p>
                            <p class="text-[11px] text-slate-400 mt-1">Este mensaje quedará registrado en el historial inmutable de versiones del skill.</p>
                        </div>
                    </div>
                </template>

                <template #footer>
                    <div class="flex items-center justify-between w-full">
                        <SecondaryButton @click="cancelImport" class="!rounded-xl text-xs uppercase tracking-wider">
                            Cancelar
                        </SecondaryButton>

                        <div class="flex items-center gap-2">
                            <button
                                v-if="importStep === 'preview'"
                                type="button"
                                @click="importStep = 'input'"
                                class="px-3 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors"
                            >
                                ← Cambiar Archivo
                            </button>

                            <PrimaryButton
                                v-if="importStep === 'input'"
                                @click="requestPreview"
                                :disabled="isPreviewLoading || (!importContent && !importFile)"
                                class="!rounded-xl text-xs uppercase tracking-wider"
                            >
                                <span v-if="isPreviewLoading">Analizando...</span>
                                <span v-else>Revisar Cambios (Ver Diff) →</span>
                            </PrimaryButton>

                            <PrimaryButton
                                v-else
                                @click="submitConfirm"
                                :disabled="isConfirming || !confirmChangelog || confirmChangelog.trim().length < 3"
                                class="!rounded-xl text-xs uppercase tracking-wider !bg-emerald-600 hover:!bg-emerald-700"
                            >
                                <span v-if="isConfirming">Guardando...</span>
                                <span v-else>✓ Confirmar e Importar</span>
                            </PrimaryButton>
                        </div>
                    </div>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>

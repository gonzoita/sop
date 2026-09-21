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

// Modal para importar Markdown
const showImportModal = ref(false);
const importForm = useForm({
    content: '',
    changelog: 'Reimportado desde Markdown externo',
});

const openImportModal = () => {
    importForm.reset();
    importForm.changelog = 'Reimportado desde Markdown externo';
    showImportModal.value = true;
};

const submitImport = () => {
    importForm.post(route('skills.import.markdown', props.skill.id), {
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
            selectedVersion.value = props.versions?.[0] || null;
        },
    });
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        importForm.content = e.target.result;
    };
    reader.readAsText(file);
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

            <!-- Modal Importar Markdown (Vía B) -->
            <DialogModal :show="showImportModal" @close="showImportModal = false" max-width="2xl">
                <template #title>
                    Importar Markdown Externo (Vía B)
                </template>

                <template #content>
                    <div class="space-y-4">
                        <p class="text-xs text-slate-500">
                            Sube o pega el contenido Markdown modificado en ChatGPT, Claude o Gemini. El sistema extraerá las instrucciones y creará una nueva versión con trazabilidad.
                        </p>

                        <div>
                            <InputLabel value="Subir archivo .md" class="text-xs font-semibold text-slate-700 mb-1" />
                            <input
                                type="file"
                                accept=".md,.txt"
                                @change="handleFileUpload"
                                class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                        </div>

                        <div>
                            <InputLabel for="imp_content" value="O Pega el Contenido Markdown" class="text-xs font-semibold text-slate-700" />
                            <textarea
                                id="imp_content"
                                v-model="importForm.content"
                                rows="8"
                                placeholder="---&#10;tipo: skill&#10;...&#10;---&#10;&#10;## Instrucciones&#10;..."
                                class="mt-1 w-full font-mono text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            ></textarea>
                            <InputError :message="importForm.errors.content" class="mt-1 text-xs" />
                        </div>

                        <div>
                            <InputLabel for="imp_changelog" value="Notas de la Importación" class="text-xs font-semibold text-slate-700" />
                            <TextInput
                                id="imp_changelog"
                                v-model="importForm.changelog"
                                type="text"
                                class="mt-1 w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError :message="importForm.errors.changelog" class="mt-1 text-xs" />
                        </div>
                    </div>
                </template>

                <template #footer>
                    <SecondaryButton @click="showImportModal = false" class="!rounded-xl text-xs uppercase tracking-wider mr-2">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        @click="submitImport"
                        :disabled="importForm.processing || !importForm.content"
                        class="!rounded-xl text-xs uppercase tracking-wider"
                    >
                        Importar y Crear Versión
                    </PrimaryButton>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>

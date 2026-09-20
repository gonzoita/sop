<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    run: {
        type: Object,
        required: true,
    },
    teamMembers: {
        type: Array,
        default: () => [],
    },
});

// Blocks from the frozen SOP version
const blocks = computed(() => props.run.sop_version?.blocks?.blocks || []);

// Map of block_id -> SopRunStep
const stepsMap = computed(() => {
    const map = {};
    (props.run.steps || []).forEach(s => {
        map[s.block_id] = s;
    });
    return map;
});

// Progress computation
const totalSteps = computed(() => (props.run.steps || []).length);
const completedSteps = computed(() => {
    return (props.run.steps || []).filter(s => ['completed', 'approved', 'skipped'].includes(s.status)).length;
});
const progressPercent = computed(() => {
    if (totalSteps.value === 0) return 100;
    return Math.round((completedSteps.value / totalSteps.value) * 100);
});

// Active inputs dictionary
const currentInputs = computed(() => props.run.inputs || {});

// Local reactive state for step form fields
const stepInputs = ref({});
const checklistState = ref({});
const aiTaskOutputs = ref({});
const approvalNotes = ref({});

// Initialize local models from existing step data
blocks.value.forEach(b => {
    const step = stepsMap.value[b.id];
    if (!step) return;

    if (b.type === 'input') {
        const key = b.props?.key;
        stepInputs.value[b.id] = currentInputs.value[key] || step.output?.value || '';
    } else if (b.type === 'checklist') {
        checklistState.value[b.id] = step.output?.checked_items || [];
    } else if (b.type === 'ai_task') {
        aiTaskOutputs.value[b.id] = step.output?.result || props.run.outputs?.[b.props?.output_key] || '';
    }
});

// Advance Step Call
const advanceStep = (step, payload) => {
    router.post(
        route('runs.steps.advance', [props.run.id, step.id]),
        payload,
        {
            preserveScroll: true,
        }
    );
};

// Input submission
const submitInput = (step, key) => {
    const val = stepInputs.value[step.block_id];
    advanceStep(step, { value: val });
};

// Checklist toggle
const toggleChecklistItem = (step, itemId) => {
    const current = [...(checklistState.value[step.block_id] || [])];
    const idx = current.indexOf(itemId);
    if (idx > -1) {
        current.splice(idx, 1);
    } else {
        current.push(itemId);
    }
    checklistState.value[step.block_id] = current;
    advanceStep(step, { checked_items: current });
};

// Decision branch click
const selectDecisionBranch = (step, branch) => {
    advanceStep(step, { selected_branch: branch.label, goto: branch.goto });
};

// AI Task manual submit
const submitAiTask = (step) => {
    const res = aiTaskOutputs.value[step.block_id];
    advanceStep(step, { output: res });
};

// Approval decision
const submitApproval = (step, decision) => {
    const notes = approvalNotes.value[step.block_id] || '';
    advanceStep(step, { decision, notes });
};

// Handoff confirm
const confirmHandoff = (step) => {
    advanceStep(step, { status: 'completed' });
};

// Step Assignment Modal
const selectedStepForAssignment = ref(null);
const assignmentForm = useForm({
    assigned_to: '',
    due_at: '',
    notes: '',
});

const openAssignmentModal = (step) => {
    selectedStepForAssignment.value = step;
    assignmentForm.assigned_to = step.assigned_to || '';
    assignmentForm.due_at = step.due_at ? step.due_at.substring(0, 10) : '';
    assignmentForm.notes = step.notes || '';
};

const submitAssignment = () => {
    if (!selectedStepForAssignment.value) return;

    assignmentForm.put(
        route('runs.steps.assignment', [props.run.id, selectedStepForAssignment.value.id]),
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedStepForAssignment.value = null;
            },
        }
    );
};

// Helper to resolve variables in strings
const resolveText = (text) => {
    if (!text) return '';
    return text.replace(/\{\{([a-zA-Z0-9_]+)\}\}/g, (match, key) => {
        if (currentInputs.value[key] !== undefined && currentInputs.value[key] !== null && currentInputs.value[key] !== '') {
            return String(currentInputs.value[key]);
        }
        return `{{${key}}}`;
    });
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'completed':
            return { label: 'Completado', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
        case 'approved':
            return { label: 'Aprobado', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
        case 'rejected':
            return { label: 'Rechazado', class: 'bg-rose-50 text-rose-700 border-rose-200' };
        case 'skipped':
            return { label: 'Omitido', class: 'bg-slate-100 text-slate-500 border-slate-200' };
        case 'awaiting_approval':
            return { label: 'Esperando Aprobación', class: 'bg-amber-50 text-amber-700 border-amber-200' };
        case 'in_progress':
            return { label: 'En Progreso', class: 'bg-indigo-50 text-indigo-700 border-indigo-200' };
        case 'pending':
        default:
            return { label: 'Pendiente', class: 'bg-slate-50 text-slate-600 border-slate-200' };
    }
};
</script>

<template>
    <AppLayout :title="`Ejecución: ${run.title}`">
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center space-x-2 text-xs text-slate-500">
                        <Link :href="route('runs.index')" class="hover:text-indigo-600 transition">Ejecuciones</Link>
                        <span>/</span>
                        <span class="font-medium text-slate-700">{{ run.sop?.title }}</span>
                        <span>/</span>
                        <span class="text-slate-400">v{{ run.sop_version?.version_number }}</span>
                    </div>

                    <h1 class="font-bold text-xl text-slate-900 leading-tight">
                        {{ run.title }}
                    </h1>
                </div>

                <div class="flex items-center space-x-3 shrink-0">
                    <span :class="['px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border', getStatusBadge(run.status).class]">
                        {{ getStatusBadge(run.status).label }}
                    </span>

                    <span v-if="run.client" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                        Cliente: {{ run.client.name }}
                    </span>
                </div>
            </div>

            <!-- Barra Global de Progreso -->
            <div class="mt-4 pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                <div class="flex items-center space-x-3 flex-1 max-w-md">
                    <span class="text-slate-500 font-medium">Progreso general:</span>
                    <div class="flex-1 bg-slate-100 h-2.5 rounded-full overflow-hidden">
                        <div
                            class="bg-indigo-600 h-full rounded-full transition-all duration-300"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                    <span class="font-bold text-slate-700">{{ progressPercent }}%</span>
                </div>

                <div class="text-slate-500 text-[11px]">
                    <span>{{ completedSteps }} de {{ totalSteps }} pasos accionables completados</span>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Columna Principal: Bloques y Pasos de Ejecución -->
            <div class="lg:col-span-3 space-y-5">
                <div
                    v-for="(block, index) in blocks"
                    :key="block.id"
                    :class="[
                        'transition rounded-2xl border p-5 shadow-2xs',
                        stepsMap[block.id]?.status === 'skipped'
                            ? 'bg-slate-50/70 border-slate-200 opacity-60'
                            : (stepsMap[block.id]?.status === 'completed' || stepsMap[block.id]?.status === 'approved'
                                ? 'bg-white border-emerald-200/80 shadow-xs'
                                : (block.type === 'heading' ? 'bg-indigo-50/40 border-indigo-100' : 'bg-white border-slate-200'))
                    ]"
                >
                    <!-- ===================== HEADING (Informativo) ===================== -->
                    <div v-if="block.type === 'heading'" class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">
                            §
                        </div>
                        <h2 class="text-base font-bold text-slate-900 leading-snug">
                            {{ block.props?.text || 'Sección' }}
                        </h2>
                    </div>

                    <!-- ===================== TEXT (Informativo con variables resueltas) ===================== -->
                    <div v-else-if="block.type === 'text'" class="space-y-2">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" /></svg>
                                <span>Instrucciones</span>
                            </span>
                        </div>
                        <div
                            class="text-xs text-slate-700 leading-relaxed prose prose-slate max-w-none"
                            v-html="resolveText(block.props?.html || '<em>Sin contenido</em>')"
                        />
                    </div>

                    <!-- ===================== MEDIA (Informativo) ===================== -->
                    <div v-else-if="block.type === 'media'" class="space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                <span>Material de Referencia ({{ block.props?.kind }})</span>
                            </span>
                        </div>

                        <div v-if="block.props?.kind === 'video'" class="aspect-video bg-slate-950 rounded-xl overflow-hidden shadow-inner flex items-center justify-center text-white">
                            <a :href="block.props?.url" target="_blank" class="flex items-center space-x-2 text-xs font-semibold text-indigo-300 hover:text-white underline">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                <span>Ver video de referencia: {{ block.props?.caption || block.props?.url }}</span>
                            </a>
                        </div>
                        <div v-else class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                            <a :href="block.props?.url" target="_blank" class="text-indigo-600 font-semibold underline">
                                {{ block.props?.caption || 'Abrir recurso externo' }}
                            </a>
                        </div>
                    </div>

                    <!-- ===================== PASOS ACCIONABLES ===================== -->
                    <div v-else class="space-y-4">
                        <!-- Cabecera del Paso -->
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div class="flex items-center space-x-2.5">
                                <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 font-bold text-xs flex items-center justify-center">
                                    {{ index + 1 }}
                                </span>
                                <div>
                                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                        {{ block.type.replace('_', ' ') }}
                                    </h3>
                                    <p v-if="stepsMap[block.id]?.assignee" class="text-[10px] text-slate-500">
                                        Asignado a: <strong class="text-slate-700">{{ stepsMap[block.id].assignee.name }}</strong>
                                        <span v-if="stepsMap[block.id].due_at">· Límite: {{ stepsMap[block.id].due_at.substring(0, 10) }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold border', getStatusBadge(stepsMap[block.id]?.status).class]">
                                    {{ getStatusBadge(stepsMap[block.id]?.status).label }}
                                </span>

                                <button
                                    type="button"
                                    @click="openAssignmentModal(stepsMap[block.id])"
                                    class="p-1 text-slate-400 hover:text-indigo-600 rounded-lg hover:bg-slate-100 transition cursor-pointer"
                                    title="Asignar responsable o fecha límite"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Si el paso fue omitido por una decisión -->
                        <div v-if="stepsMap[block.id]?.status === 'skipped'" class="p-3 bg-slate-100/70 rounded-xl text-xs text-slate-500 italic">
                            Este paso fue omitido debido a la bifurcación seleccionada en el bloque de decisión anterior.
                        </div>

                        <!-- INPUT BLOCK -->
                        <div v-else-if="block.type === 'input'" class="space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-0.5">
                                    {{ block.props?.label }}
                                    <span v-if="block.props?.required" class="text-rose-500">*</span>
                                </label>
                                <p v-if="block.props?.help" class="text-[11px] text-slate-400 mb-2">
                                    {{ block.props.help }}
                                </p>
                            </div>

                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                <div class="flex-1">
                                    <textarea
                                        v-if="block.props?.field === 'textarea'"
                                        v-model="stepInputs[block.id]"
                                        rows="2"
                                        :placeholder="block.props?.placeholder || 'Ingresa el texto...'"
                                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                                    />
                                    <select
                                        v-else-if="block.props?.field === 'select'"
                                        v-model="stepInputs[block.id]"
                                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                                    >
                                        <option value="" disabled>Selecciona una opción...</option>
                                        <option v-for="opt in block.props?.options || []" :key="opt" :value="opt">
                                            {{ opt }}
                                        </option>
                                    </select>
                                    <input
                                        v-else
                                        :type="block.props?.field === 'number' ? 'number' : (block.props?.field === 'date' ? 'date' : 'text')"
                                        v-model="stepInputs[block.id]"
                                        :placeholder="block.props?.placeholder || 'Ingresa el valor...'"
                                        class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                                    />
                                </div>

                                <button
                                    type="button"
                                    @click="submitInput(stepsMap[block.id], block.props?.key)"
                                    class="px-4 py-2 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition shrink-0 cursor-pointer"
                                >
                                    Guardar
                                </button>
                            </div>

                            <div v-if="currentInputs[block.props?.key]" class="text-[11px] text-emerald-700 flex items-center space-x-1.5 pt-1">
                                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span>Variable <code v-text="'{{' + block.props?.key + '}}'"></code> guardada en la ejecución.</span>
                            </div>
                        </div>

                        <!-- CHECKLIST BLOCK -->
                        <div v-else-if="block.type === 'checklist'" class="space-y-2">
                            <p class="text-xs font-semibold text-slate-700 mb-2">
                                Completa las verificaciones manuales para avanzar:
                            </p>

                            <div class="space-y-2">
                                <label
                                    v-for="item in block.props?.items || []"
                                    :key="item.id"
                                    class="flex items-start space-x-2.5 p-2 rounded-xl hover:bg-slate-50 transition cursor-pointer border border-transparent hover:border-slate-200"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="(checklistState[block.id] || []).includes(item.id)"
                                        @change="toggleChecklistItem(stepsMap[block.id], item.id)"
                                        class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 size-4"
                                    />
                                    <span :class="['text-xs', (checklistState[block.id] || []).includes(item.id) ? 'line-through text-slate-400' : 'text-slate-800']">
                                        {{ item.text }}
                                        <span v-if="item.required" class="text-rose-500 font-bold ml-1">*</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- DECISION BLOCK -->
                        <div v-else-if="block.type === 'decision'" class="space-y-3">
                            <h4 class="text-xs font-bold text-slate-900">
                                {{ block.props?.question || 'Pregunta de decisión' }}
                            </h4>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    v-for="b in block.props?.branches || []"
                                    :key="b.label"
                                    type="button"
                                    @click="selectDecisionBranch(stepsMap[block.id], b)"
                                    :class="[
                                        'px-4 py-2 rounded-xl text-xs font-bold border transition flex items-center space-x-2 cursor-pointer',
                                        stepsMap[block.id]?.output?.selected_branch === b.label
                                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                                    ]"
                                >
                                    <span>{{ b.label }}</span>
                                    <span class="text-[10px] opacity-75 font-mono">→ {{ b.goto }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- AI TASK BLOCK (Fase 2: Manual) -->
                        <div v-else-if="block.type === 'ai_task'" class="space-y-3">
                            <div class="p-3 bg-amber-50 border border-amber-200/80 rounded-xl flex items-start space-x-2.5">
                                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <div class="text-[11px] text-amber-800">
                                    <strong>Generación de IA (Skill: {{ block.props?.skill_slug }}):</strong>
                                    El motor automático de OpenRouter se integrará en la Fase 3. En esta fase puedes escribir o pegar el resultado manualmente para continuar con la corrida.
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Entregable / Salida de la tarea:
                                </label>
                                <textarea
                                    v-model="aiTaskOutputs[block.id]"
                                    rows="3"
                                    placeholder="Escribe el entregable o respuesta de esta tarea..."
                                    class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl font-mono"
                                />
                            </div>

                            <button
                                type="button"
                                @click="submitAiTask(stepsMap[block.id])"
                                class="px-4 py-2 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition cursor-pointer"
                            >
                                Guardar Entregable
                            </button>
                        </div>

                        <!-- APPROVAL BLOCK -->
                        <div v-else-if="block.type === 'approval'" class="space-y-3">
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
                                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                    Rol requerido: <strong class="text-slate-800">{{ block.props?.role || 'editor' }}</strong>
                                </span>
                                <p class="text-xs text-slate-700">
                                    {{ block.props?.instructions || 'Revisa el entregable y toma una decisión.' }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Observaciones / Comentarios:
                                </label>
                                <textarea
                                    v-model="approvalNotes[block.id]"
                                    rows="2"
                                    placeholder="Motivos de aprobación o correcciones necesarias..."
                                    class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                                />
                            </div>

                            <div class="flex items-center space-x-2">
                                <button
                                    type="button"
                                    @click="submitApproval(stepsMap[block.id], 'approved')"
                                    class="px-4 py-2 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-xs transition cursor-pointer"
                                >
                                    ✓ Aprobar Paso
                                </button>
                                <button
                                    type="button"
                                    @click="submitApproval(stepsMap[block.id], 'rejected')"
                                    class="px-4 py-2 text-xs font-semibold bg-rose-600 hover:bg-rose-700 text-white rounded-xl shadow-xs transition cursor-pointer"
                                >
                                    ✕ Rechazar
                                </button>
                            </div>
                        </div>

                        <!-- HANDOFF BLOCK -->
                        <div v-else-if="block.type === 'handoff'" class="space-y-3">
                            <div class="p-3 bg-indigo-50/50 border border-indigo-100 rounded-xl space-y-1">
                                <div class="text-[11px] font-semibold text-indigo-700 uppercase tracking-wider">
                                    Traspaso hacia: <strong>{{ block.props?.to === 'client' ? 'Cliente' : 'Equipo Interno' }}</strong>
                                </div>
                                <p class="text-xs text-slate-700">
                                    {{ resolveText(block.props?.message || 'Revisar y confirmar avance.') }}
                                </p>
                            </div>

                            <button
                                type="button"
                                @click="confirmHandoff(stepsMap[block.id])"
                                class="px-4 py-2 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition cursor-pointer"
                            >
                                Confirmar Traspaso
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Lateral: Panel de Variables y Datos -->
            <div class="space-y-5">
                <!-- Panel de Variables Capturadas -->
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs space-y-3 sticky top-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">
                            Variables Capturadas
                        </h3>
                        <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-mono">
                            {{ Object.keys(currentInputs).length }}
                        </span>
                    </div>

                    <div v-if="Object.keys(currentInputs).length === 0" class="text-xs text-slate-400 py-3 text-center">
                        Aún no se han completado campos de entrada en esta ejecución.
                    </div>

                    <div v-else class="space-y-2 max-h-[300px] overflow-y-auto">
                        <div
                            v-for="(val, key) in currentInputs"
                            :key="key"
                            class="p-2 bg-slate-50 rounded-xl border border-slate-100 text-xs space-y-0.5"
                        >
                            <span
                                class="font-mono text-[10px] text-indigo-600 font-semibold block"
                                v-text="'{{' + key + '}}'"
                            />
                            <span class="text-slate-800 font-medium block truncate">
                                {{ val }}
                            </span>
                        </div>
                    </div>

                    <!-- Metadatos de la corrida -->
                    <div class="pt-3 border-t border-slate-100 space-y-1.5 text-[11px] text-slate-500">
                        <div class="flex justify-between">
                            <span>Iniciado por:</span>
                            <strong class="text-slate-700">{{ run.starter?.name || 'Sistema' }}</strong>
                        </div>
                        <div class="flex justify-between">
                            <span>Fecha de inicio:</span>
                            <span class="text-slate-700">{{ run.started_at ? run.started_at.substring(0, 16).replace('T', ' ') : '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Versión congelada:</span>
                            <span class="font-mono text-slate-700">v{{ run.sop_version?.version_number }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Asignación de Paso -->
        <div v-if="selectedStepForAssignment" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 space-y-4 border border-slate-100">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">
                        Asignar Responsable y Plazo
                    </h3>
                    <button
                        type="button"
                        @click="selectedStepForAssignment = null"
                        class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submitAssignment" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Responsable del paso
                        </label>
                        <select
                            v-model="assignmentForm.assigned_to"
                            class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                        >
                            <option value="">Sin responsable específico</option>
                            <option v-for="m in teamMembers" :key="m.id" :value="m.id">
                                {{ m.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Fecha límite (due_at)
                        </label>
                        <input
                            type="date"
                            v-model="assignmentForm.due_at"
                            class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Instrucciones o notas adicionales
                        </label>
                        <textarea
                            v-model="assignmentForm.notes"
                            rows="2"
                            placeholder="Añade notas para el responsable..."
                            class="w-full text-xs text-slate-800 border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl"
                        />
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="selectedStepForAssignment = null"
                            class="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="assignmentForm.processing"
                            class="inline-flex items-center px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-sm cursor-pointer"
                        >
                            Guardar Asignación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

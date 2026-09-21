<script setup>
import { ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    skills: Array,
});

const showModal = ref(false);
const form = useForm({
    name: '',
    slug: '',
    description: '',
    instructions: '',
});

const openModal = () => {
    form.reset();
    showModal.value = true;
};

const submitCreate = () => {
    form.post(route('skills.store'), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};

const deleteSkill = (skill) => {
    if (confirm(`¿Estás seguro de eliminar el skill "${skill.name}"? Esta acción se registrará en el log de auditoría.`)) {
        router.delete(route('skills.destroy', skill.id));
    }
};
</script>

<template>
    <AppLayout title="Librería de Skills de IA">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-100 text-indigo-800 border border-indigo-200">
                            Activos de IA
                        </span>
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Librería de Skills</h1>
                    </div>
                    <p class="text-sm text-slate-500 mt-1">
                        Conjunto versionado de instrucciones de IA que producen entregables estructurados a partir de variables.
                    </p>
                </div>
                <button
                    @click="openModal"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nuevo Skill</span>
                </button>
            </div>

            <!-- List of Skills Grid -->
            <div v-if="skills && skills.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="skill in skills"
                    :key="skill.id"
                    class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between"
                >
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <h2 class="text-base font-bold text-slate-900 leading-snug">
                                <Link :href="route('skills.show', skill.id)" class="hover:text-indigo-600 transition-colors">
                                    {{ skill.name }}
                                </Link>
                            </h2>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                v{{ skill.current_version_number }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2 mb-3">
                            <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-purple-50 text-purple-700 border border-purple-100">
                                {{ skill.slug }}
                            </span>
                            <span class="text-xs text-slate-400">• {{ skill.versions_count }} {{ skill.versions_count === 1 ? 'versión' : 'versiones' }}</span>
                        </div>

                        <p class="text-xs text-slate-500 line-clamp-2 mb-4">
                            {{ skill.description || 'Sin descripción detallada.' }}
                        </p>

                        <!-- Variables detectadas -->
                        <div v-if="skill.current_variables && skill.current_variables.length > 0" class="mb-4">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                                Variables requeridas
                            </span>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="v in skill.current_variables"
                                    :key="v"
                                    v-text="'{{' + v + '}}'"
                                    class="px-2 py-0.5 rounded text-[10px] font-mono bg-amber-50 text-amber-800 border border-amber-200"
                                ></span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between mt-auto">
                        <span class="text-[11px] text-slate-400">
                            Por {{ skill.creator_name }}
                        </span>
                        <div class="flex items-center gap-2">
                            <button
                                @click="deleteSkill(skill)"
                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                title="Eliminar skill"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                            <Link
                                :href="route('skills.show', skill.id)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-700 text-xs font-semibold rounded-lg transition-colors"
                            >
                                <span>Ver Skill</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-16 bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 mx-auto flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-1">No hay skills en la librería</h3>
                <p class="text-xs text-slate-500 max-w-md mx-auto mb-6">
                    Los skills son los módulos reutilizables de instrucciones de IA (briefs, copys, estructuras de campaña, reportes) que tus SOPs pueden ejecutar automáticamente.
                </p>
                <button
                    @click="openModal"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Crear Primer Skill</span>
                </button>
            </div>

            <!-- Modal Nuevo Skill -->
            <DialogModal :show="showModal" @close="showModal = false">
                <template #title>
                    Nuevo Skill de IA
                </template>

                <template #content>
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="skill_name" value="Nombre del Skill" class="text-xs font-semibold text-slate-700" />
                            <TextInput
                                id="skill_name"
                                v-model="form.name"
                                type="text"
                                placeholder="Ej: Brief Estratégico de Marca"
                                class="mt-1 w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            />
                            <InputError :message="form.errors.name" class="mt-1 text-xs" />
                        </div>

                        <div>
                            <InputLabel for="skill_slug" value="Slug (Identificador único en SOPs)" class="text-xs font-semibold text-slate-700" />
                            <TextInput
                                id="skill_slug"
                                v-model="form.slug"
                                type="text"
                                placeholder="Ej: brief-de-marca (opcional, se autogenera)"
                                class="mt-1 w-full text-sm font-mono rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError :message="form.errors.slug" class="mt-1 text-xs" />
                        </div>

                        <div>
                            <InputLabel for="skill_desc" value="Descripción del Propósito" class="text-xs font-semibold text-slate-700" />
                            <textarea
                                id="skill_desc"
                                v-model="form.description"
                                rows="2"
                                placeholder="Breve explicación de qué produce este skill y para qué sirve..."
                                class="mt-1 w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            <InputError :message="form.errors.description" class="mt-1 text-xs" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel for="skill_instructions" value="Instrucciones del Sistema (System Prompt)" class="text-xs font-semibold text-slate-700" />
                                <span class="text-[11px] text-indigo-600 font-mono">Usa &#123;&#123;variable&#125;&#125; para campos</span>
                            </div>
                            <textarea
                                id="skill_instructions"
                                v-model="form.instructions"
                                rows="7"
                                placeholder="Eres un estratega de marca experto en agencias digitales. A partir de los siguientes datos:&#10;- Marca: {{nombre_marca}}&#10;- Objetivo: {{objetivo_campana}}&#10;&#10;Genera un brief completo con propuesta de valor, tono de voz y pilares..."
                                class="mt-1 w-full font-mono text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            ></textarea>
                            <InputError :message="form.errors.instructions" class="mt-1 text-xs" />
                        </div>
                    </div>
                </template>

                <template #footer>
                    <SecondaryButton @click="showModal = false" class="!rounded-xl text-xs uppercase tracking-wider mr-2">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        @click="submitCreate"
                        :disabled="form.processing"
                        class="!rounded-xl text-xs uppercase tracking-wider"
                    >
                        Crear Skill
                    </PrimaryButton>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>

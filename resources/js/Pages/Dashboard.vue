<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const currentTeam = computed(() => user.value.current_team || {});
const isAdmin = computed(() => {
    return user.value.roles?.includes('admin') || user.value.permissions?.includes('view-audit-logs');
});
</script>

<template>
    <AppLayout title="Panel Principal">
        <template #header>
            <div>
                <h1 class="font-bold text-xl text-gray-900 leading-tight">
                    Panel Principal
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Visión general operativa de <strong class="text-gray-700">{{ currentTeam.name || 'tu equipo' }}</strong>
                </p>
            </div>
        </template>

        <div class="space-y-6">

            <!-- Banner Informativo de Fase 0 Completada -->
            <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 rounded-2xl p-6 text-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="inline-flex items-center space-x-2 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                        <span>Fase 0: Fundaciones Completadas</span>
                    </div>
                    <h2 class="text-lg font-bold tracking-tight text-white">
                        Bienvenido a SOPForge, {{ user.name }}
                    </h2>
                    <p class="text-xs text-indigo-200/80 max-w-2xl">
                        El núcleo de multi-inquilino, roles de agencia, registro inmutable de auditoría y autenticación reforzada está activo y verificado en Hostinger.
                    </p>
                </div>

                <div class="flex items-center space-x-3 shrink-0">
                    <Link
                        v-if="isAdmin"
                        :href="route('admin.audit-logs.index')"
                        class="inline-flex items-center px-3.5 py-2 rounded-lg text-xs font-semibold bg-white text-indigo-900 hover:bg-indigo-50 shadow-sm transition"
                    >
                        <svg class="w-4 h-4 me-1.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        Ver Registro de Auditoría
                    </Link>
                    <Link
                        :href="route('profile.show')"
                        class="inline-flex items-center px-3.5 py-2 rounded-lg text-xs font-semibold bg-indigo-700/60 hover:bg-indigo-700 text-white border border-indigo-500/40 transition"
                    >
                        Seguridad 2FA
                    </Link>
                </div>
            </div>

            <!-- Grid de 3 Tarjetas Reservadas (SOPs, Ejecuciones, Consumo IA) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <!-- 1. SOPs Recientes (Espacio Reservado Fase 1) -->
                <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs flex flex-col justify-between hover:border-gray-300 transition">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                0 plantillas
                            </span>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900">SOPs Recientes</h3>
                        <p class="text-xs text-gray-500 mt-1">
                            Plantillas y procedimientos operativos estándar de la agencia.
                        </p>

                        <!-- Estado vacío reservado -->
                        <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-dashed border-slate-200 text-center">
                            <p class="text-xs text-gray-500">
                                Espacio reservado para el constructor de SOPs de la <strong class="text-gray-700">Fase 1</strong> con bloques arrastrables y variables.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between text-xs">
                        <span class="text-gray-400">Constructor visual</span>
                        <span class="font-medium text-blue-600">Próximamente Fase 1 &rarr;</span>
                    </div>
                </div>

                <!-- 2. Ejecuciones en Curso (Espacio Reservado Fase 2) -->
                <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs flex flex-col justify-between hover:border-gray-300 transition">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                                0 activas
                            </span>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900">Ejecuciones en Curso</h3>
                        <p class="text-xs text-gray-500 mt-1">
                            Corridas de procedimientos con datos concretos de clientes.
                        </p>

                        <!-- Estado vacío reservado -->
                        <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-dashed border-slate-200 text-center">
                            <p class="text-xs text-gray-500">
                                Espacio reservado para el motor de ejecución y aprobación de pasos de la <strong class="text-gray-700">Fase 2</strong>.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between text-xs">
                        <span class="text-gray-400">Motor de ejecución</span>
                        <span class="font-medium text-emerald-600">Próximamente Fase 2 &rarr;</span>
                    </div>
                </div>

                <!-- 3. Consumo de IA del Mes (Espacio Reservado Fase 3) -->
                <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs flex flex-col justify-between hover:border-gray-300 transition">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-violet-50 text-violet-700 border border-violet-100">
                                $0.00 USD
                            </span>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900">Consumo de IA (Mes Actual)</h3>
                        <p class="text-xs text-gray-500 mt-1">
                            Monitoreo de costos y tokens con la API de OpenRouter.
                        </p>

                        <!-- Métrica reservada -->
                        <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="flex justify-between text-xs mb-1.5">
                                <span class="text-gray-500 font-medium">Gasto / Límite de Equipo</span>
                                <span class="font-semibold text-gray-900">$0.00 / $50.00 USD</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                <div class="bg-violet-600 h-2 rounded-full" style="width: 0%"></div>
                            </div>
                            <div class="flex justify-between text-[11px] text-gray-400 mt-2 font-mono">
                                <span>0 llamadas</span>
                                <span>0 tokens</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between text-xs">
                        <span class="text-gray-400">OpenRouter + Colas</span>
                        <span class="font-medium text-violet-600">Próximamente Fase 3 &rarr;</span>
                    </div>
                </div>

            </div>

            <!-- Fila Inferior: Controles de Seguridad y Multi-inquilino -->
            <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-xs">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">
                    Estado de Seguridad y Arquitectura
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-start space-x-3">
                        <div class="p-1.5 bg-emerald-100 text-emerald-700 rounded-lg shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-800">Aislamiento de Inquilino</div>
                            <div class="text-gray-500 mt-0.5">Scoping automático con BelongsToTeam en cada consulta.</div>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-start space-x-3">
                        <div class="p-1.5 bg-purple-100 text-purple-700 rounded-lg shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-800">2FA Obligatorio</div>
                            <div class="text-gray-500 mt-0.5">Control de acceso estricto para cuentas con rol Admin.</div>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-start space-x-3">
                        <div class="p-1.5 bg-blue-100 text-blue-700 rounded-lg shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-800">Auditoría Inmutable</div>
                            <div class="text-gray-500 mt-0.5">spatie/laravel-activitylog registrando eventos clave.</div>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-start space-x-3">
                        <div class="p-1.5 bg-amber-100 text-amber-700 rounded-lg shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-800">Rate Limiting Activo</div>
                            <div class="text-gray-500 mt-0.5">Throttling en login y restablecimiento de contraseña.</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
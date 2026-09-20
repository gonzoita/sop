<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

defineProps({
    title: String,
});

const page = usePage();
const sidebarOpen = ref(false);

const user = computed(() => page.props.auth?.user || {});
const currentTeam = computed(() => user.value.current_team || {});
const allTeams = computed(() => user.value.all_teams || []);
const flash = computed(() => page.props.flash || {});

const isAdmin = computed(() => {
    return user.value.roles?.includes('admin') || user.value.permissions?.includes('view-audit-logs');
});

const userRoleBadge = computed(() => {
    if (user.value.roles?.includes('admin')) return { label: 'Admin', class: 'bg-purple-100 text-purple-800 border-purple-200' };
    if (user.value.roles?.includes('editor')) return { label: 'Editor', class: 'bg-blue-100 text-blue-800 border-blue-200' };
    if (user.value.roles?.includes('ejecutor')) return { label: 'Ejecutor', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    if (user.value.roles?.includes('cliente')) return { label: 'Cliente', class: 'bg-amber-100 text-amber-800 border-amber-200' };
    return { label: 'Usuario', class: 'bg-gray-100 text-gray-800 border-gray-200' };
});

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex">
        <Head :title="title" />

        <!-- Mobile Sidebar Backdrop -->
        <div
            v-if="sidebarOpen"
            @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-gray-900/60 backdrop-blur-sm lg:hidden transition-opacity"
        />

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-300 flex flex-col transition-transform duration-300 ease-in-out lg:static lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Logo & Brand -->
            <div class="h-16 flex items-center justify-between px-6 bg-slate-950/40 border-b border-slate-800">
                <Link :href="route('dashboard')" class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white shadow-md shadow-indigo-500/20">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <span class="font-bold text-lg text-white tracking-tight">SOP<span class="text-indigo-400">Forge</span></span>
                        <span class="block text-[10px] text-slate-400 -mt-1 uppercase tracking-wider font-semibold">Agencia Digital</span>
                    </div>
                </Link>
                <button @click="sidebarOpen = false" class="lg:hidden text-slate-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2">
                    Operaciones
                </div>

                <Link
                    :href="route('dashboard')"
                    :class="[
                        'flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition',
                        route().current('dashboard')
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                    ]"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                    <span>Panel Principal</span>
                </Link>

                <Link
                    :href="route('sops.index')"
                    :class="[
                        'flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition',
                        route().current('sops.*')
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                    ]"
                >
                    <div class="flex items-center space-x-3">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <span>SOPs (Plantillas)</span>
                    </div>
                </Link>

                <Link
                    :href="route('runs.index')"
                    :class="[
                        'flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition',
                        route().current('runs.*')
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                    ]"
                >
                    <div class="flex items-center space-x-3">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Ejecuciones</span>
                    </div>
                </Link>

                <Link
                    :href="route('clients.index')"
                    :class="[
                        'flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition',
                        route().current('clients.*')
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                    ]"
                >
                    <div class="flex items-center space-x-3">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <span>Clientes</span>
                    </div>
                </Link>

                <div class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium text-slate-400 opacity-70 cursor-not-allowed">
                    <div class="flex items-center space-x-3">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        <span>Skills de IA</span>
                    </div>
                    <span class="text-[10px] bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded font-mono">Fase 3</span>
                </div>

                <div class="pt-6">
                    <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2">
                        Administración
                    </div>

                    <Link
                        v-if="isAdmin"
                        :href="route('admin.automation-triggers.index')"
                        :class="[
                            'flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition',
                            route().current('admin.automation-triggers.*')
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        <span>Automatizaciones</span>
                    </Link>

                    <Link
                        v-if="isAdmin"
                        :href="route('admin.audit-logs.index')"
                        :class="[
                            'flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition',
                            route().current('admin.audit-logs.*')
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        <span>Registro de Auditoría</span>
                    </Link>

                    <Link
                        v-if="currentTeam.id"
                        :href="route('teams.show', currentTeam.id)"
                        :class="[
                            'flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition',
                            route().current('teams.show')
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <span>Configuración de Equipo</span>
                    </Link>
                </div>
            </nav>

            <!-- Bottom System Status -->
            <div class="p-4 bg-slate-950/30 border-t border-slate-800/80">
                <div class="flex items-center space-x-2 text-xs text-slate-400">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
                    <span>Multi-tenant Activo</span>
                </div>
                <div class="text-[10px] text-slate-400 mt-1 font-mono">
                    Hostinger • PHP 8.4 • MariaDB
                </div>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <div class="flex-1 flex flex-col min-w-0">

            <!-- Header -->
            <header class="h-16 bg-white border-b border-gray-200/80 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8 shadow-xs">
                <!-- Left: Mobile toggle + Page Title -->
                <div class="flex items-center space-x-4">
                    <button
                        @click="sidebarOpen = true"
                        class="lg:hidden text-gray-500 hover:text-gray-700 focus:outline-none"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>

                    <div v-if="$slots.header">
                        <slot name="header" />
                    </div>
                </div>

                <!-- Right: Team Switcher & User Menu -->
                <div class="flex items-center space-x-3 sm:space-x-4">

                    <!-- Team Switcher -->
                    <div class="relative">
                        <Dropdown align="right" width="60">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-xs text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition"
                                >
                                    <svg class="w-4 h-4 text-indigo-500 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    <span class="max-w-[120px] sm:max-w-[180px] truncate">{{ currentTeam.name || 'Sin Equipo' }}</span>
                                    <svg class="ms-2 -me-0.5 size-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="w-64">
                                    <div class="block px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider bg-gray-50">
                                        Equipo Activo
                                    </div>

                                    <DropdownLink v-if="currentTeam.id" :href="route('teams.show', currentTeam.id)">
                                        Configuración de Equipo
                                    </DropdownLink>

                                    <DropdownLink v-if="$page.props.jetstream?.canCreateTeams" :href="route('teams.create')">
                                        Crear Nuevo Equipo
                                    </DropdownLink>

                                    <!-- Switch Teams -->
                                    <template v-if="allTeams.length > 1">
                                        <div class="border-t border-gray-100" />
                                        <div class="block px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider bg-gray-50">
                                            Cambiar de Equipo
                                        </div>

                                        <template v-for="team in allTeams" :key="team.id">
                                            <form @submit.prevent="switchToTeam(team)">
                                                <DropdownLink as="button" class="w-full text-left">
                                                    <div class="flex items-center justify-between">
                                                        <span :class="{'font-semibold text-indigo-600': team.id === currentTeam.id}">
                                                            {{ team.name }}
                                                        </span>
                                                        <svg v-if="team.id === currentTeam.id" class="size-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </div>
                                                </DropdownLink>
                                            </form>
                                        </template>
                                    </template>
                                </div>
                            </template>
                        </Dropdown>
                    </div>

                    <!-- User Menu -->
                    <div class="relative">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button class="flex items-center space-x-2 text-sm focus:outline-none">
                                    <div class="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center font-medium text-xs shadow-xs">
                                        {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                                    </div>
                                    <div class="hidden md:block text-left">
                                        <div class="text-xs font-semibold text-gray-700 leading-tight">{{ user.name }}</div>
                                        <div class="text-[10px] text-gray-400 flex items-center space-x-1">
                                            <span :class="['px-1.5 py-0.2 rounded-full text-[9px] font-semibold border', userRoleBadge.class]">
                                                {{ userRoleBadge.label }}
                                            </span>
                                        </div>
                                    </div>
                                    <svg class="size-3.5 text-gray-400 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="block px-4 py-2 text-xs text-gray-400 bg-gray-50 border-b border-gray-100">
                                    Conectado como <strong class="text-gray-700 font-semibold">{{ user.email }}</strong>
                                </div>

                                <DropdownLink :href="route('profile.show')">
                                    Mi Perfil y Seguridad (2FA)
                                </DropdownLink>

                                <div class="border-t border-gray-100" />

                                <form @submit.prevent="logout">
                                    <DropdownLink as="button" class="text-red-600 hover:text-red-700 hover:bg-red-50">
                                        Cerrar Sesión
                                    </DropdownLink>
                                </form>
                            </template>
                        </Dropdown>
                    </div>

                </div>
            </header>

            <!-- Flash Alert Messages -->
            <div v-if="flash.warning" class="bg-amber-50 border-b border-amber-200 px-4 py-3 text-sm text-amber-800 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <span>{{ flash.warning }}</span>
                </div>
            </div>

            <div v-if="flash.error" class="bg-red-50 border-b border-red-200 px-4 py-3 text-sm text-red-800 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ flash.error }}</span>
                </div>
            </div>

            <div v-if="flash.success" class="bg-emerald-50 border-b border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ flash.success }}</span>
                </div>
            </div>

            <!-- Page Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>

        </div>
    </div>
</template>
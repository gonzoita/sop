<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

defineProps({
    title: String,
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const flash = computed(() => page.props.flash || {});

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col font-sans text-slate-800">
        <Head :title="title" />

        <!-- Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <!-- Brand / Portal identity -->
                <div class="flex items-center gap-6">
                    <Link :href="route('portal.runs.index')" class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-amber-600 to-indigo-600 flex items-center justify-center text-white font-bold text-base shadow-sm">
                            S
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-slate-900 tracking-tight leading-none text-base">SOPForge</span>
                            <span class="text-[11px] font-semibold text-amber-600 uppercase tracking-wider">Portal de Cliente</span>
                        </div>
                    </Link>

                    <!-- Navigation Tabs -->
                    <nav class="hidden sm:flex items-center space-x-1">
                        <Link
                            :href="route('portal.runs.index')"
                            :class="[
                                'px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors',
                                route().current('portal.runs.*')
                                    ? 'bg-amber-100/80 text-amber-900 font-bold'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                            ]"
                        >
                            Procedimientos
                        </Link>
                        <Link
                            :href="route('portal.documents.index')"
                            :class="[
                                'px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors',
                                route().current('portal.documents.*')
                                    ? 'bg-amber-100/80 text-amber-900 font-bold'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                            ]"
                        >
                            Mis Documentos
                        </Link>
                    </nav>
                </div>

                <!-- User Navigation & Logout -->
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-amber-100 border border-amber-300 flex items-center justify-center text-amber-800 font-bold text-xs uppercase">
                            {{ user.name ? user.name.substring(0, 2) : 'CL' }}
                        </div>
                        <div class="hidden sm:flex flex-col text-left">
                            <span class="text-xs font-semibold text-slate-800 leading-tight">{{ user.name }}</span>
                            <span class="text-[10px] text-slate-500 leading-none">{{ user.email }}</span>
                        </div>
                    </div>

                    <div class="h-5 w-px bg-slate-200"></div>

                    <button
                        @click="logout"
                        type="button"
                        class="text-xs font-medium text-slate-600 hover:text-red-600 transition-colors flex items-center gap-1.5 px-2 py-1 rounded hover:bg-red-50"
                        title="Cerrar sesión"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="hidden sm:inline">Salir</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Mobile sub-navigation bar -->
        <div class="sm:hidden bg-white border-b border-slate-200 px-4 py-2 flex items-center space-x-2">
            <Link
                :href="route('portal.runs.index')"
                :class="[
                    'px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors',
                    route().current('portal.runs.*')
                        ? 'bg-amber-100/80 text-amber-900 font-bold'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                ]"
            >
                Procedimientos
            </Link>
            <Link
                :href="route('portal.documents.index')"
                :class="[
                    'px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors',
                    route().current('portal.documents.*')
                        ? 'bg-amber-100/80 text-amber-900 font-bold'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                ]"
            >
                Mis Documentos
            </Link>
        </div>

        <!-- Flash Banner -->
        <div v-if="flash.banner" class="bg-indigo-600 text-white text-sm py-2.5 px-4 text-center font-medium shadow-sm flex items-center justify-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span>{{ flash.banner }}</span>
        </div>

        <!-- Main Content -->
        <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400">
            &copy; {{ new Date().getFullYear() }} SOPForge — Acceso seguro al portal de cliente
        </footer>
    </div>
</template>

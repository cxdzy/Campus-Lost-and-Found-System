<script setup>
import ToastStack from '@/Components/Admin/ToastStack.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    errors: Object,
    auth: Object,
});

const page = usePage();

const navItems = [
    { label: 'Inventory',     key: 'inventory',     route: 'admin.dashboard' },
    { label: 'Match Alerts',  key: 'match-alerts',  route: 'admin.match-alerts' },
    { label: 'API Logs',      key: 'api-logs',      route: 'admin.api-logs' },
    { label: 'Reports',       key: 'reports',       route: 'admin.reports' },
];

const isActive = (item) => {
    const name = page.component;
    if (item.key === 'match-alerts') return name === 'Admin/MatchAlerts';
    if (item.key === 'reports')      return name === 'Admin/Reports';
    if (item.key === 'api-logs')     return name === 'Admin/ApiLogs';
    if (item.key === 'inventory')    return name === 'Admin/AdminDashboard';
    return false;
};

const userName = computed(() => page.props.auth?.user?.name ?? 'Staff');
</script>

<template>
    <div class="flex h-screen w-full font-inter bg-slate-50 overflow-hidden">
        <aside class="w-72 bg-slate-900 text-slate-300 flex-col hidden lg:flex shadow-2xl">
            <div class="h-20 flex items-center px-8 border-b border-slate-800 bg-slate-950">
                <div class="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center mr-3 shadow-lg shadow-indigo-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <span class="text-xl font-bold text-white tracking-tight">Security Portal</span>
            </div>

            <nav class="flex-1 p-6 space-y-1">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4 px-2">Main Menu</div>
                <template v-for="item in navItems" :key="item.key">
                    <!-- All nav items → dedicated Inertia pages -->
                    <Link :href="route(item.route)"
                          :class="['w-full flex items-center px-4 py-3 rounded-xl text-sm font-medium transition-all',
                              isActive(item) ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800 hover:text-white']">
                        {{ item.label }}
                    </Link>
                </template>
            </nav>

            <div class="p-6 bg-slate-950/50 border-t border-slate-800">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-slate-700 border-2 border-indigo-500/50 flex items-center justify-center text-white font-bold">
                        {{ userName.charAt(0).toUpperCase() }}
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-bold text-white">{{ userName }}</p>
                        <p class="text-[10px] text-slate-500 uppercase font-semibold">Security Portal</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="flex-1 flex flex-col h-screen overflow-hidden">
            <div class="flex-1 overflow-auto pb-16 lg:pb-0">
                <slot />
            </div>
        </main>

        <ToastStack />

        <!-- Mobile bottom navigation (hidden on lg+ where sidebar is visible) -->
        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-slate-900 border-t border-slate-700 shadow-lg">
            <div class="grid grid-cols-4 h-16">
                <Link :href="route('admin.dashboard')"
                      :class="['flex flex-col items-center justify-center gap-1 text-xs font-medium transition-colors',
                          isActive({ key: 'inventory' }) ? 'text-indigo-400' : 'text-slate-400']">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    <span>Inventory</span>
                </Link>
                <Link :href="route('admin.match-alerts')"
                      :class="['flex flex-col items-center justify-center gap-1 text-xs font-medium transition-colors',
                          isActive({ key: 'match-alerts' }) ? 'text-indigo-400' : 'text-slate-400']">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span>Alerts</span>
                </Link>
                <Link :href="route('admin.api-logs')"
                      :class="['flex flex-col items-center justify-center gap-1 text-xs font-medium transition-colors',
                          isActive({ key: 'api-logs' }) ? 'text-indigo-400' : 'text-slate-400']">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>API Logs</span>
                </Link>
                <Link :href="route('admin.reports')"
                      :class="['flex flex-col items-center justify-center gap-1 text-xs font-medium transition-colors',
                          isActive({ key: 'reports' }) ? 'text-indigo-400' : 'text-slate-400']">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span>Reports</span>
                </Link>
            </div>
        </nav>
    </div>
</template>

<style scoped>
</style>

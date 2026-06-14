<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    logs: Object,
});

const formatTime = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('en-MY', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
};

const statusClass = (code) => {
    if (!code) return 'bg-slate-100 text-slate-600';
    if (code >= 200 && code < 300) return 'bg-emerald-100 text-emerald-700';
    if (code >= 400 && code < 500) return 'bg-amber-100 text-amber-700';
    return 'bg-rose-100 text-rose-700';
};

const serviceBadgeClass = (service) => {
    if (!service) return 'bg-slate-100 text-slate-600';
    const s = service.toLowerCase();
    if (s.includes('vision') || s.includes('opencv')) return 'bg-violet-100 text-violet-700';
    if (s.includes('telegram')) return 'bg-sky-100 text-sky-700';
    return 'bg-slate-100 text-slate-600';
};
</script>

<template>
    <AdminLayout>
        <Head title="API Logs" />

        <div class="p-8 space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">API Logs</h1>
                <p class="mt-1 text-sm text-slate-500">
                    External service transactions — Vision API and Telegram calls.
                </p>
            </div>

            <!-- Summary chips -->
            <div class="flex flex-wrap gap-3">
                <div class="rounded-xl bg-white border border-slate-200 px-5 py-3 shadow-sm">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total</p>
                    <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ logs.total }}</p>
                </div>
                <div class="rounded-xl bg-white border border-slate-200 px-5 py-3 shadow-sm">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">This page</p>
                    <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ logs.data.length }}</p>
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">#</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Item</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Logged At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="logs.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-slate-400 text-sm">
                                    No API log entries yet.
                                </td>
                            </tr>
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs text-slate-400">{{ log.id }}</td>
                                <td class="px-6 py-4">
                                    <span :class="['inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold', serviceBadgeClass(log.service)]">
                                        {{ log.service ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="['inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold tabular-nums', statusClass(log.http_status_code)]">
                                        {{ log.http_status_code ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-700 max-w-xs truncate">
                                    <span v-if="log.item_title" class="text-xs">{{ log.item_title }}</span>
                                    <span v-else class="text-slate-400 text-xs">—</span>
                                </td>
                                <td class="px-6 py-4 text-slate-500 text-xs tabular-nums whitespace-nowrap">
                                    {{ formatTime(log.logged_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="flex items-center justify-between px-6 py-4 border-t border-slate-100">
                    <p class="text-sm text-slate-500">
                        Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }}
                    </p>
                    <div class="flex gap-2">
                        <Link v-if="logs.prev_page_url"
                              :href="logs.prev_page_url"
                              class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">
                            Previous
                        </Link>
                        <Link v-if="logs.next_page_url"
                              :href="logs.next_page_url"
                              class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

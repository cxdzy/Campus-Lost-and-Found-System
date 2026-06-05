<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    alerts: { type: Array, default: () => [] },
});

defineOptions({ layout: AdminLayout });

const alerts = ref(props.alerts);
const verifying = ref({});
const toastMessage = ref('');
const toastType = ref('success');

const showToast = (message, type = 'success') => {
    toastMessage.value = message;
    toastType.value = type;
    setTimeout(() => { toastMessage.value = ''; }, 4000);
};

const verify = async (alert) => {
    verifying.value[alert.id] = true;
    try {
        await window.axios.post(`/admin/match-alerts/${alert.id}/verify`);
        const idx = alerts.value.findIndex(a => a.id === alert.id);
        if (idx !== -1) alerts.value[idx].has_pending_claim = true;
        showToast('OTP sent to student via Telegram.', 'success');
    } catch (err) {
        const msg = err?.response?.data?.message ?? 'Failed to send OTP. Please try again.';
        showToast(msg, 'error');
    } finally {
        verifying.value[alert.id] = false;
    }
};

const scoreColor = (score) => {
    if (score >= 90) return 'bg-emerald-500';
    if (score >= 75) return 'bg-indigo-500';
    return 'bg-amber-500';
};

const formatDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('en-MY', { dateStyle: 'medium', timeStyle: 'short' });
};
</script>

<template>
    <Head title="Match Alerts" />

    <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-8 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Match Alerts</h1>
            <p class="text-xs text-slate-500 font-medium">
                AI-generated matches above the confidence threshold
            </p>
        </div>
        <span class="px-3 py-1 bg-indigo-100 text-indigo-700 text-xs font-bold rounded-full">
            {{ alerts.length }} alert{{ alerts.length !== 1 ? 's' : '' }}
        </span>
    </header>

    <div class="flex-1 overflow-auto p-8 bg-slate-50">

        <!-- Toast -->
        <transition name="fade">
            <div v-if="toastMessage"
                 :class="['fixed top-6 right-6 z-50 px-5 py-3 rounded-xl text-sm font-semibold shadow-xl border',
                     toastType === 'success'
                         ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                         : 'bg-red-50 text-red-800 border-red-200']">
                {{ toastMessage }}
            </div>
        </transition>

        <!-- Empty state -->
        <div v-if="alerts.length === 0"
             class="flex flex-col items-center justify-center py-24 text-center">
            <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-700">No match alerts yet</h3>
            <p class="text-sm text-slate-400 mt-1">Alerts appear here once the AI engine finds a high-confidence match.</p>
        </div>

        <!-- Alert cards -->
        <div class="space-y-5">
            <div v-for="alert in alerts" :key="alert.id"
                 class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

                <!-- Score banner -->
                <div class="flex items-center justify-between px-6 py-3 border-b border-slate-100 bg-slate-50/60">
                    <div class="flex items-center gap-3">
                        <span :class="['text-white text-xs font-black px-3 py-1 rounded-full uppercase tracking-wider', scoreColor(alert.match_score)]">
                            {{ alert.match_score }}% match
                        </span>
                        <span class="text-[11px] text-slate-400 font-medium">Alert #{{ alert.id }} · {{ formatDate(alert.created_at) }}</span>
                    </div>
                    <span v-if="alert.has_pending_claim"
                          class="text-[10px] font-bold text-amber-700 bg-amber-100 px-2.5 py-1 rounded-full uppercase tracking-wide">
                        OTP Active
                    </span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-[1fr_48px_1fr_200px] gap-0 items-stretch">

                    <!-- Lost side -->
                    <div class="p-6">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Student Report (Lost)</p>
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                            <h4 class="font-bold text-slate-800 text-base leading-snug">{{ alert.lost.title }}</h4>
                            <p class="text-xs text-slateigo-500 mt-1">
                                <span class="font-semibold text-slate-600">{{ alert.lost.category }}</span>
                                <span v-if="alert.lost.location" class="text-slate-400"> · {{ alert.lost.location }}</span>
                            </p>
                            <div class="mt-3 pt-3 border-t border-slate-200 space-y-0.5">
                                <p class="text-[11px] text-slate-500">
                                    Student: <span class="font-semibold text-slate-700">{{ alert.lost.student_name ?? '—' }}</span>
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    Matric: <span class="font-mono font-semibold text-slate-700">{{ alert.lost.matric ?? '—' }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Arrow divider -->
                    <div class="hidden lg:flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </div>

                    <!-- Found side -->
                    <div class="p-6 border-t lg:border-t-0 lg:border-l border-slate-100">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">System Found Record</p>
                        <div class="p-4 bg-indigo-50 rounded-xl border border-indigo-100 flex gap-4">
                            <div class="w-16 h-16 rounded-lg overflow-hidden bg-slate-200 flex-shrink-0 border border-indigo-200">
                                <img v-if="alert.found.image_url"
                                     :src="alert.found.image_url"
                                     :alt="alert.found.title"
                                     class="w-full h-full object-cover"
                                     @error="$event.target.style.display='none'">
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-indigo-900 text-base leading-snug truncate">{{ alert.found.title }}</h4>
                                <p class="text-xs text-indigo-600 mt-1">
                                    <span class="font-semibold">{{ alert.found.category }}</span>
                                    <span v-if="alert.found.location" class="text-indigo-400"> · {{ alert.found.location }}</span>
                                </p>
                                <div v-if="alert.found.tags.length" class="flex flex-wrap gap-1 mt-2">
                                    <span v-for="tag in alert.found.tags.slice(0, 5)" :key="tag"
                                          class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] font-semibold rounded-full">
                                        {{ tag }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action column -->
                    <div class="flex items-center justify-center p-6 border-t lg:border-t-0 lg:border-l border-slate-100 bg-slate-50/40">
                        <button
                            @click="verify(alert)"
                            :disabled="verifying[alert.id]"
                            class="w-full bg-indigo-600 text-white py-3 px-4 rounded-xl text-sm font-bold hover:bg-indigo-700 active:scale-95 transition-all shadow-lg shadow-indigo-600/20 disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                            <svg v-if="verifying[alert.id]"
                                 class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor"
                                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span>{{ verifying[alert.id] ? 'Sending…' : 'Verify & Notify' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style scoped>
    .fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
    .fade-enter-from, .fade-leave-to { opacity: 0; }
    </style>
</template>

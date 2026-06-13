<template>
    <div>
        <Head title="Admin Dashboard" />
        <!-- Page content is rendered inside AdminLayout via layout slot -->
          <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-8 z-10 shadow-sm">
              <div>
                  <h1 class="text-2xl font-bold text-slate-800 tracking-tight capitalize">{{ activeTab.replace('-', ' ') }}</h1>
                  <p class="text-xs text-slate-500 font-medium">System Role: <span class="text-indigo-600">Authenticated Administrator</span></p>
              </div>
              <div class="flex items-center space-x-4">
                  <div class="relative group">
                      <input type="text" placeholder="Global search..." class="pl-10 pr-4 py-2.5 bg-slate-100 border-transparent rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 w-80 transition-all border border-slate-200">
                      <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 group-focus-within:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                  </div>
              </div>
          </header>

          <div class="flex-1 overflow-auto p-8 bg-slate-50 no-scrollbar">
              
              <div v-if="activeTab === 'inventory'" class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
                  <table class="min-w-full divide-y divide-slate-200">
                      <thead class="bg-slate-50">
                          <tr>
                              <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Entry ID</th>
                              <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Visual Evidence</th>
                              <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Item Description</th>
                              <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Proximity / Location</th>
                              <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</th>
                              <th class="px-6 py-4 text-right"></th>
                          </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                          <tr v-for="item in inventory" :key="item.id" class="hover:bg-slate-50/80 transition-all group">
                              <td class="px-6 py-4 whitespace-nowrap text-xs font-mono font-bold text-slate-500">#{{ item.id }}</td>
                              <td class="px-6 py-4 whitespace-nowrap">
                                  <div class="w-16 h-12 bg-slate-200 rounded-lg overflow-hidden border border-slate-300 shadow-inner">
                                      <img :src="item.image" @error="handleImageError" class="w-full h-full object-cover group-hover:scale-110 transition-all">
                                  </div>
                              </td>
                              <td class="px-6 py-4 whitespace-nowrap">
                                  <div class="text-sm font-bold text-slate-800">{{ item.title }}</div>
                                  <div class="text-[11px] text-indigo-500 font-semibold">{{ item.category }}</div>
                              </td>
                              <td class="px-6 py-4 whitespace-nowrap">
                                  <div class="text-sm font-medium text-slate-600">{{ item.location }}</div>
                                  <div class="text-[10px] text-slate-400 font-mono">{{ item.coords }}</div>
                              </td>
                              <td class="px-6 py-4 whitespace-nowrap">
                                  <span :class="['px-2.5 py-1 text-[10px] font-bold rounded-lg uppercase tracking-wider', 
                                      item.status === 'Pending' ? 'bg-amber-100 text-amber-700' : 
                                      item.status === 'Matched' ? 'bg-indigo-100 text-indigo-700' : 
                                      'bg-emerald-100 text-emerald-700']">
                                      {{ item.status }}
                                  </span>
                              </td>
                              <td class="px-6 py-4 whitespace-nowrap text-right">
                                  <div class="flex items-center justify-end gap-2">
                                      <button @click="selectItem(item)" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-xs font-bold hover:bg-slate-900 hover:text-white transition-all">Audit Details</button>
                                      <button @click="confirmDelete(item)" class="bg-white border border-red-300 text-red-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-red-600 hover:text-white transition-all">Delete</button>
                                  </div>
                              </td>
                          </tr>
                      </tbody>
                  </table>
              </div>

              <div v-if="activeTab === 'match-alerts'" class="space-y-6">
                  <div v-for="match in matchAlerts" :key="match.id" class="bg-white p-6 rounded-2xl shadow-md border border-slate-200 flex items-center gap-8 relative overflow-hidden">
                      <div class="absolute top-0 right-0 px-4 py-1 bg-indigo-600 text-white text-[10px] font-bold uppercase tracking-widest rounded-bl-xl">
                          {{ match.score }}% Match Score
                      </div>
                      <div class="flex-1">
                          <label class="text-[10px] font-bold text-slate-400 uppercase mb-2 block">Student Report (Lost)</label>
                          <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                              <h4 class="font-bold text-slate-800">{{ match.lostItem.title }}</h4>
                              <p class="text-xs text-slate-500">Reported by: {{ match.lostItem.user }}</p>
                          </div>
                      </div>
                      <div class="flex flex-col items-center justify-center">
                          <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                      </div>
                      <div class="flex-1">
                          <label class="text-[10px] font-bold text-slate-400 uppercase mb-2 block">System Found Record</label>
                          <div class="p-4 bg-indigo-50 rounded-xl border border-indigo-100 flex items-center gap-3">
                              <img :src="match.foundItem.image" class="w-12 h-12 rounded-lg object-cover shadow-sm">
                              <div>
                                  <h4 class="font-bold text-indigo-900">{{ match.foundItem.title }}</h4>
                                  <p class="text-xs text-indigo-600">Proximity: {{ match.distance }}m</p>
                              </div>
                          </div>
                      </div>
                      <div class="w-48">
                          <button class="w-full bg-indigo-600 text-white py-3 rounded-xl text-xs font-bold hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-600/20">Verify & Notify</button>
                      </div>
                  </div>
              </div>

              <div v-if="activeTab === 'api-logs'" class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
                  <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                      <h3 class="text-sm font-bold text-slate-700">Real-time External Webhook Transactions</h3>
                      <span class="flex items-center text-[10px] font-bold text-emerald-500 uppercase tracking-widest">
                          <span class="w-2 h-2 bg-emerald-500 rounded-full mr-2 animate-pulse"></span> System Live
                      </span>
                  </div>
                  <table class="min-w-full divide-y divide-slate-100">
                      <thead class="bg-slate-50">
                          <tr>
                              <th class="px-6 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Service</th>
                              <th class="px-6 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Endpoint</th>
                              <th class="px-6 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</th>
                              <th class="px-6 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Response Time</th>
                              <th class="px-6 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Timestamp</th>
                          </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-50">
                          <tr v-for="log in apiLogs" :key="log.id" class="hover:bg-slate-50/50 transition-colors">
                              <td class="px-6 py-4 whitespace-nowrap">
                                  <span :class="['px-2 py-0.5 rounded text-[10px] font-black uppercase', 
                                      log.service === 'Vision' ? 'bg-purple-100 text-purple-700' : 
                                      log.service === 'Maps' ? 'bg-blue-100 text-blue-700' : 'bg-sky-100 text-sky-700']">
                                      {{ log.service }}
                                  </span>
                              </td>
                              <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-slate-600">{{ log.endpoint }}</td>
                              <td class="px-6 py-4 whitespace-nowrap">
                                  <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg">200 OK</span>
                              </td>
                              <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500 font-medium">{{ log.time }}ms</td>
                              <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400">{{ log.timestamp }}</td>
                          </tr>
                      </tbody>
                  </table>
              </div>
                    </div>
      <transition name="fade">
          <div v-if="selectedItem" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 transition-all" @click="closeItem"></div>
      </transition>

      <!-- Delete confirmation modal -->
      <transition name="fade">
          <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center px-4">
              <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="cancelDelete"></div>
              <div class="relative z-10 w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
                  <div class="p-6 border-b border-slate-100 bg-red-50 flex items-center gap-3">
                      <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                          <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path></svg>
                      </div>
                      <div>
                          <h3 class="text-base font-black text-slate-900">Confirm Deletion</h3>
                          <p class="text-xs text-slate-500 mt-0.5">This action is permanent and cannot be undone.</p>
                      </div>
                  </div>
                  <div class="p-6">
                      <p class="text-sm text-slate-700">Are you sure you want to delete item <span class="font-black text-slate-900">#{{ deleteTarget.id }}</span>? This will permanently remove the found item, all AI tags, match alerts, claim records, and associated logs.</p>
                  </div>
                  <div class="px-6 pb-6 flex justify-end gap-3">
                      <button @click="cancelDelete" :disabled="deleteLoading" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-bold hover:bg-slate-50 transition-colors disabled:opacity-50">Cancel</button>
                      <button @click="executeDelete" :disabled="deleteLoading" class="px-5 py-2.5 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700 transition-colors shadow-sm disabled:opacity-50 flex items-center gap-2">
                          <svg v-if="deleteLoading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                          <span>{{ deleteLoading ? 'Deleting...' : 'Delete Permanently' }}</span>
                      </button>
                  </div>
              </div>
          </div>
      </transition>

      <transition name="slide">
          <div v-if="selectedItem" class="fixed inset-y-0 right-0 max-w-xl w-full bg-white shadow-2xl z-50 overflow-y-auto flex flex-col border-l border-slate-200">
              <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 sticky top-0 z-10">
                  <div>
                      <h2 class="text-xl font-black text-slate-900 italic">AUDIT LOG: {{ selectedItem.id }}</h2>
                      <p class="text-[10px] font-bold text-slate-400 tracking-widest uppercase">Database Reference Record</p>
                  </div>
                  <button @click="closeItem" class="text-slate-400 hover:text-slate-900 bg-white p-2 rounded-full border shadow-sm transition-all">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                  </button>
              </div>
              
              <div class="p-8 space-y-8">
                  <div class="space-y-3">
                      <label class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">Raw Image Asset (VPS Hosted)</label>
                      <div class="w-full h-72 bg-slate-100 rounded-3xl overflow-hidden shadow-2xl border-4 border-white ring-1 ring-slate-200">
                          <img :src="selectedItem.image" @error="handleImageError" class="w-full h-full object-cover">
                      </div>
                  </div>

                  <div class="grid grid-cols-2 gap-4">
                      <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100">
                          <label class="text-[9px] font-bold text-indigo-400 uppercase mb-2 block">Vision API Confidence</label>
                          <div class="text-2xl font-black text-indigo-700">{{ selectedItem.confidence }}%</div>
                          <div class="w-full bg-indigo-200 h-1.5 rounded-full mt-2 overflow-hidden">
                              <div class="bg-indigo-600 h-full" :style="{ width: selectedItem.confidence + '%' }"></div>
                          </div>
                      </div>
                      <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                          <label class="text-[9px] font-bold text-slate-400 uppercase mb-2 block">System Log Status</label>
                          <div class="text-sm font-bold text-slate-700">HTTP 200 OK</div>
                          <div class="text-[10px] text-slate-400 mt-1">API Response: 4.2ms</div>
                      </div>
                  </div>

                  <div class="space-y-4">
                      <h3 class="text-sm font-bold text-slate-800 flex items-center">
                          <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                          OpenCV Metadata Extraction
                      </h3>
                      <div class="flex flex-wrap gap-2">
                          <span v-for="tag in selectedItem.tags" :key="tag" class="px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-lg shadow-sm">
                              {{ tag }}
                          </span>
                      </div>
                  </div>

                  <div class="space-y-4">
                      <h3 class="text-sm font-bold text-slate-800 flex items-center">
                          <svg class="w-4 h-4 mr-2 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                          Spatial Mapping Data (OpenStreetMap)
                      </h3>
                      <div v-if="selectedItemLatLng" class="rounded-2xl overflow-hidden border border-slate-200 shadow-sm" style="height: 200px;">
                          <LMap :zoom="16" :center="selectedItemLatLng" :options="{ zoomControl: true, dragging: false, scrollWheelZoom: false }" style="height: 200px; width: 100%;">
                              <LTileLayer
                                  url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                  attribution="&copy; <a href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors"
                              />
                              <LMarker :lat-lng="selectedItemLatLng" />
                          </LMap>
                      </div>
                      <div v-else class="w-full h-40 bg-slate-100 rounded-2xl border-2 border-slate-200 border-dashed flex flex-col items-center justify-center">
                          <div class="text-xs font-mono text-slate-500 mb-2">{{ selectedItem.coords || 'No coordinates recorded' }}</div>
                          <div class="text-[10px] font-bold text-slate-400 bg-slate-200 px-3 py-1 rounded-full">Location unavailable</div>
                      </div>
                  </div>

                  <div class="pt-2">
                      <button @click="triggerOTP" :disabled="otpLoading" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-xl shadow-slate-900/20 disabled:opacity-50">
                          <span v-if="!otpLoading">Initiate OTP Secure Handover</span>
                          <span v-else>Triggering Telegram API...</span>
                      </button>
                  </div>
              </div>
          </div>
    </transition>
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { LMap, LMarker, LTileLayer } from '@vue-leaflet/vue-leaflet';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const activeTab = ref('inventory');
const selectedItem = ref(null);
const otpLoading = ref(false);

const asset = (p) => new URL(p, import.meta.url).href;
const fallbackImage =
    'data:image/svg+xml;charset=UTF-8,' +
    encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600"><rect width="800" height="600" fill="#e2e8f0"/><rect x="120" y="110" width="560" height="360" rx="28" fill="#cbd5e1"/><circle cx="320" cy="250" r="44" fill="#94a3b8"/><path d="M160 430l120-120 90 90 70-70 160 160H160z" fill="#94a3b8"/><text x="400" y="520" text-anchor="middle" font-family="Arial, sans-serif" font-size="34" fill="#64748b">Image unavailable</text></svg>`
    );

const inventory = ref([]);

const normalizeImagePath = (path) => {
    if (!path) return fallbackImage;
    if (typeof path !== 'string') return fallbackImage;
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    if (path.startsWith('/')) return window.location.origin + path;
    return window.location.origin + `/storage/${path}`;
};

const handleImageError = (event) => {
    event.target.src = fallbackImage;
};

const mapReportToInventory = (item) => ({
    id:           item.id,
    title:        item.title_description ?? 'Untitled',
    category:     typeof item.category === 'string' ? item.category : (item.category?.category_name ?? 'Uncategorized'),
    location:     item.location_name ?? 'Unknown location',
    coords:       (item.latitude && item.longitude) ? `${item.latitude}, ${item.longitude}` : '',
    status:       item.status ?? 'Pending',
    image:        normalizeImagePath(item.image_url ?? ''),
    matchAlertId: item.match_alert_id ?? null,
    matchScore:   item.match_score ?? null,
    confidence: item.confidence ?? 0,
    tags:       Array.isArray(item.tags) ? item.tags : [],
});

const matchAlerts = ref([
    { 
        id: 1, 
        score: 96, 
        distance: 12,
        lostItem: { title: 'Black Leather Wallet', user: 'Mohamad Ali (2024123...)' },
        foundItem: { title: 'Black Leather Wallet', image: asset('./assets/blackwallet.jpg') }
    },
    { 
        id: 2, 
        score: 89, 
        distance: 45,
        lostItem: { title: 'Blue Water Bottle', user: 'Nurul Huda (2024556...)' },
        foundItem: { title: 'Blue HydroFlask', image: asset('./assets/bluehydroflask.jpg') }
    }
]);

const apiLogs = ref([
    { id: 1, service: 'Vision', endpoint: 'POST /v1/images:annotate', time: 1420, timestamp: '2026-05-10 21:05:12' },
    { id: 2, service: 'Maps', endpoint: 'GET /maps/api/geocode/json', time: 240, timestamp: '2026-05-10 21:05:14' },
    { id: 3, service: 'Bot', endpoint: 'POST /bot_TOKEN/sendMessage', time: 110, timestamp: '2026-05-10 21:06:01' },
    { id: 4, service: 'Vision', endpoint: 'POST /v1/images:annotate', time: 1560, timestamp: '2026-05-10 21:08:44' },
    { id: 5, service: 'Maps', endpoint: 'GET /maps/api/geocode/json', time: 310, timestamp: '2026-05-10 21:08:45' }
]);

const selectItem = (item) => selectedItem.value = item;
const closeItem  = () => selectedItem.value = null;

const deleteTarget  = ref(null);
const deleteLoading = ref(false);

const confirmDelete = (item) => { deleteTarget.value = item; };
const cancelDelete  = () => { deleteTarget.value = null; };

const executeDelete = async () => {
    if (!deleteTarget.value) return;
    deleteLoading.value = true;
    try {
        await window.axios.delete(`/admin/items/${deleteTarget.value.id}`);
        inventory.value = inventory.value.filter((i) => i.id !== deleteTarget.value.id);
        if (selectedItem.value?.id === deleteTarget.value.id) closeItem();
        pushToast('success', 'Item deleted', `Item #${deleteTarget.value.id} has been permanently removed.`);
    } catch (err) {
        const msg = err?.response?.data?.error ?? 'Could not delete the item. Please try again.';
        pushToast('error', 'Delete failed', msg);
    } finally {
        deleteLoading.value = false;
        deleteTarget.value = null;
    }
};

// Parse "lat, lng" string from inventory coords into a Leaflet LatLng array
const selectedItemLatLng = computed(() => {
    const coords = selectedItem.value?.coords;
    if (!coords) return null;
    const parts = String(coords).split(',').map(Number);
    if (parts.length < 2 || parts.some(Number.isNaN)) return null;
    if (parts[0] === 0 && parts[1] === 0) return null;
    return parts.slice(0, 2);
});

const pushToast = (type, title, message = '') => {
    window.dispatchEvent(new CustomEvent('admin-toast', { detail: { type, title, message } }));
};

const onAdminTab = (e) => {
    if (e?.detail) activeTab.value = e.detail;
};

onMounted(() => {
    window.addEventListener('admin-tab', onAdminTab);

    // Populate synchronously from Inertia props — no Axios call needed
    if (props.items.length > 0) {
        inventory.value = props.items.map(mapReportToInventory);
    }
});

onUnmounted(() => {
    window.removeEventListener('admin-tab', onAdminTab);
});

const triggerOTP = async () => {
    if (!selectedItem.value?.matchAlertId) {
        pushToast('error', 'No match alert', 'This item has no verified AI match yet. OTP handover is only available for matched items.');
        return;
    }
    otpLoading.value = true;
    try {
        await window.axios.post(`/admin/match-alerts/${selectedItem.value.matchAlertId}/verify`);
        pushToast('success', 'OTP sent', 'A 6-digit claim code has been sent to the student via Telegram.');
    } catch (err) {
        const msg = err?.response?.data?.message ?? 'Could not send OTP. Please try again.';
        pushToast('error', 'OTP failed', msg);
    } finally {
        otpLoading.value = false;
    }
};
</script>

<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
export default { layout: AdminLayout }
</script>

<style scoped>
.font-inter { font-family: 'Inter', sans-serif; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-enter-active, .slide-leave-active { transition: transform 0.3s ease, opacity 0.3s ease; }
.slide-enter-from, .slide-leave-to { transform: translateX(30px); opacity: 0; }
.no-scrollbar::-webkit-scrollbar { display: none; }
</style>
<template>
    <div class="flex h-screen w-full font-inter bg-slate-50 overflow-hidden">
      <aside class="w-72 bg-slate-900 text-slate-300 flex flex-col hidden lg:flex shadow-2xl">
          <div class="h-20 flex items-center px-8 border-b border-slate-800 bg-slate-950">
              <div class="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center mr-3 shadow-lg shadow-indigo-500/20">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              </div>
              <span class="text-xl font-bold text-white tracking-tight">Security Portal</span>
          </div>
              <nav class="flex-1 p-6 space-y-2">
              <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4 px-2">Main Menu</div>
              <button v-for="tab in ['inventory', 'match-alerts', 'api-logs', 'settings']" :key="tab" @click="selectTab(tab)"
                  class="w-full flex items-center px-4 py-3 rounded-xl text-sm font-medium transition-all group hover:bg-slate-800 hover:text-white">
                  <span class="capitalize">{{ tab.replace('-', ' ') }}</span>
              </button>
          </nav>
          <div class="p-6 bg-slate-950/50 border-t border-slate-800">
              <div class="flex items-center">
                  <div class="w-10 h-10 rounded-full bg-slate-700 border-2 border-indigo-500/50 flex items-center justify-center text-white font-bold">HQ</div>
                  <div class="ml-3">
                      <p class="text-sm font-bold text-white">Haziq Naqib</p>
                      <p class="text-[10px] text-slate-500 uppercase font-semibold">Senior Security Admin</p>
                  </div>
              </div>
          </div>
      </aside>

            <main class="flex-1 flex flex-col h-screen overflow-hidden">
                <div class="flex-1 overflow-auto">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mt-4 bg-white rounded-t-2xl shadow-sm">
                        <slot />
                    </div>
                </div>
            </main>
        <ToastStack />
    </div>
</template>

<script setup>
import ToastStack from '@/Components/Admin/ToastStack.vue'

defineProps({
    errors: Object,
    auth: Object,
})

const selectTab = (tab) => {
    try {
        window.dispatchEvent(new CustomEvent('admin-tab', { detail: tab }));
    } catch (e) {
        // ignore in non-browser environments
    }
}
</script>

<style scoped>
</style>

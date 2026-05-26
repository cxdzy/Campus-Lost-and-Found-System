<template>
  <div class="fixed top-4 right-4 z-[60] space-y-3 w-full max-w-sm pointer-events-none">
    <transition-group name="toast" tag="div" class="space-y-3">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="[
          'pointer-events-auto rounded-2xl border px-4 py-3 shadow-xl backdrop-blur-sm',
          toast.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : '',
          toast.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : '',
          toast.type === 'info' ? 'border-sky-200 bg-sky-50 text-sky-900' : '',
          toast.type === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-900' : '',
        ]"
      >
        <div class="flex items-start gap-3">
          <div class="mt-0.5 h-2.5 w-2.5 rounded-full" :class="indicatorClass(toast.type)"></div>
          <div class="flex-1">
            <p class="text-sm font-semibold leading-5">{{ toast.title }}</p>
            <p v-if="toast.message" class="mt-1 text-xs opacity-80">{{ toast.message }}</p>
          </div>
          <button type="button" class="text-xs font-bold opacity-60 hover:opacity-100" @click="removeToast(toast.id)">x</button>
        </div>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const toasts = ref([])
let nextId = 1
let timeoutIds = new Map()

const indicatorClass = (type) => {
  if (type === 'success') return 'bg-emerald-500'
  if (type === 'error') return 'bg-rose-500'
  if (type === 'warning') return 'bg-amber-500'
  return 'bg-sky-500'
}

const removeToast = (id) => {
  toasts.value = toasts.value.filter((toast) => toast.id !== id)
  const timeoutId = timeoutIds.get(id)
  if (timeoutId) {
    clearTimeout(timeoutId)
    timeoutIds.delete(id)
  }
}

const pushToast = (payload = {}) => {
  const toast = {
    id: nextId++,
    type: payload.type ?? 'info',
    title: payload.title ?? 'Notification',
    message: payload.message ?? '',
  }

  toasts.value.unshift(toast)

  const timeoutId = window.setTimeout(() => removeToast(toast.id), payload.timeout ?? 3500)
  timeoutIds.set(toast.id, timeoutId)
}

const handleToastEvent = (event) => pushToast(event?.detail ?? {})

onMounted(() => {
  window.addEventListener('admin-toast', handleToastEvent)
})

onBeforeUnmount(() => {
  window.removeEventListener('admin-toast', handleToastEvent)
  timeoutIds.forEach((timeoutId) => clearTimeout(timeoutId))
  timeoutIds.clear()
})
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>

<template>
  <Head title="Admin Portal - Security Access" />

  <div class="flex min-h-screen w-full bg-slate-900 text-slate-300">
    
    <div class="hidden lg:flex w-1/2 bg-slate-950 relative overflow-hidden flex-col justify-between p-12 border-r border-slate-800">
      <div class="absolute inset-0 z-0">
        <img src="https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=1470&auto=format&fit=crop" alt="Server Data Center" class="w-full h-full object-cover opacity-20 grayscale">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/80 to-transparent"></div>
      </div>

      <div class="relative z-10 flex items-center">
        <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center mr-3 shadow-lg shadow-indigo-600/30">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </div>
        <span class="text-2xl font-black text-white tracking-tight">System Admin</span>
      </div>

      <div class="relative z-10 mb-12">
        <h1 class="text-4xl lg:text-5xl font-extrabold text-white leading-tight mb-6">
          This is the place to manage <br>
          <span class="text-indigo-500">what they are searching for.</span>
        </h1>
        <p class="text-lg text-slate-400 max-w-md">
          Secure access gateway for Campus Security and System Administrators. Monitor API webhooks, verify OTPs, and manage the AI matching engine.
        </p>
      </div>
      
      <div class="absolute inset-0 z-0 opacity-5 pointer-events-none" style="background-image: linear-gradient(#ffffff 1px, transparent 1px), linear-gradient(90deg, #ffffff 1px, transparent 1px); background-size: 40px 40px;"></div>
    </div>

    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 sm:p-12 relative">
      <div class="w-full max-w-md">
        
        <div class="mb-10 text-center lg:text-left">
          <h2 class="text-3xl font-black text-white mb-2">Security Portal</h2>
          <p class="text-slate-400 font-medium">Restricted Access. Staff credentials required.</p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-6 bg-slate-800/50 p-8 rounded-2xl border border-slate-700/50 shadow-2xl backdrop-blur-sm">
          
          <div>
            <label class="block text-sm font-bold text-slate-300 mb-2">Staff / Admin ID</label>
            <div class="relative">
              <input v-model="form.staff_id" type="text" placeholder="e.g., SEC-9021" required class="w-full border border-slate-600 rounded-xl px-4 py-3.5 pl-11 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-900/50 text-white placeholder-slate-500 transition-all">
              <svg class="w-5 h-5 text-slate-500 absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
              </svg>
            </div>
            <div v-if="errors.staff_id" class="text-xs text-red-400 mt-1 font-medium">{{ errors.staff_id }}</div>
          </div>

          <div>
            <label class="block text-sm font-bold text-slate-300 mb-2">System Password</label>
            <div class="relative">
              <input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="••••••••" required class="w-full border border-slate-600 rounded-xl px-4 py-3.5 pl-11 pr-12 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-900/50 text-white placeholder-slate-500 transition-all">
              <svg class="w-5 h-5 text-slate-500 absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
              </svg>
              
              <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-3.5 text-slate-500 hover:text-indigo-400 focus:outline-none">
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.543 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
              </button>
            </div>
            <div v-if="errors.password" class="text-xs text-red-400 mt-1 font-medium">{{ errors.password }}</div>
          </div>

          <div class="pt-2">
            <button type="submit" :disabled="form.processing" class="w-full py-4 rounded-xl font-black uppercase tracking-widest text-white shadow-xl bg-indigo-600 hover:bg-indigo-500 shadow-indigo-600/20 transition-all disabled:opacity-70 flex justify-center items-center">
              <span v-if="!form.processing">Authorize Access</span>
              <svg v-else class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </button>
          </div>
        </form>

        <p class="text-center text-xs font-medium text-slate-500 mt-8">
          Unauthorized access to this portal is strictly prohibited. <br>
          For IT Support, contact <a href="#" class="text-indigo-400 hover:underline">sysadmin@campus.edu</a>.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

// Props handling backend input failure reporting bounds cleanly
defineProps({
    errors: Object
});

const showPassword = ref(false);

const form = useForm({
    staff_id: '',
    password: '',
});

const handleLogin = () => {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>
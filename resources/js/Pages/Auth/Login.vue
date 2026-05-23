<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    matric_number: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login" />

    <div
        class="min-h-screen w-full bg-white"
        style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;"
    >
        <div class="flex min-h-screen w-full bg-white">
    
    <!-- LEFT PANEL: Visuals & Branding (Hidden on mobile) -->
    <div class="hidden lg:flex w-1/2 bg-indigo-900 relative overflow-hidden flex-col justify-between p-12">
        <!-- Background Image with Overlay -->
        <div class="absolute inset-0 z-0">
            <!-- Placeholder image: Campus / Library -->
            <img src="https://images.unsplash.com/photo-1541339907198-e08756dedf3f?q=80&w=1470&auto=format&fit=crop" alt="Campus Library" class="w-full h-full object-cover opacity-30 mix-blend-overlay">
            <div class="absolute inset-0 bg-gradient-to-t from-indigo-950 via-indigo-900/80 to-indigo-900/40"></div>
        </div>

        <!-- Top Logo -->
        <div class="relative z-10 flex items-center">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center mr-3 shadow-lg">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <span class="text-2xl font-black text-white tracking-tight">Campus L&F</span>
        </div>

        <!-- Center / Bottom Content -->
        <div class="relative z-10 mb-12">
            <!-- The Requested Quote -->
            <h1 class="text-4xl lg:text-5xl font-extrabold text-white leading-tight mb-6">
                This is the place to find <br>
                <span class="text-indigo-400">what you are searching for.</span>
            </h1>
            <p class="text-lg text-indigo-200 max-w-md">
                Our AI-Powered Campus Lost & Found System connects lost items with their rightful owners using advanced Vision AI and geographic proximity matching.
            </p>
            
            <!-- Decorative Elements -->
            <div class="flex items-center space-x-4 mt-10">
                <div class="flex -space-x-3">
                    <img class="w-10 h-10 rounded-full border-2 border-indigo-900 object-cover" src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop" alt="User">
                    <img class="w-10 h-10 rounded-full border-2 border-indigo-900 object-cover" src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=100&auto=format&fit=crop" alt="User">
                    <img class="w-10 h-10 rounded-full border-2 border-indigo-900 object-cover" src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop" alt="User">
                </div>
                <p class="text-sm font-medium text-indigo-200">Join thousands of students securing their belongings.</p>
            </div>
        </div>
        
        <!-- Background Grid Pattern -->
        <div class="absolute inset-0 z-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(#ffffff 2px, transparent 2px); background-size: 30px 30px;"></div>
    </div>

    <!-- RIGHT PANEL: Auth Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 sm:p-12 relative overflow-y-auto">
        
        <!-- Mobile Logo -->
        <div class="absolute top-8 left-8 flex items-center lg:hidden">
            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center mr-2">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <span class="text-xl font-bold text-slate-900">Campus L&F</span>
        </div>

        <div class="w-full max-w-md my-auto">
            
            <div class="mb-8 text-center lg:text-left mt-12 lg:mt-0">
                <h2 class="text-3xl font-black text-slate-900 mb-2">Student Portal</h2>
                <p class="text-slate-500 font-medium">Access your reports or create a new account.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1.5">Matric Number</label>
                        <div class="relative">
                            <input
                                v-model="form.matric_number"
                                type="text"
                                placeholder="e.g., 2024123456"
                                required
                                class="w-full border border-slate-300 rounded-xl px-4 py-3.5 pl-11 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all"
                                autocomplete="username"
                            />
                            <svg class="w-5 h-5 text-slate-400 absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <p v-if="form.errors.matric_number" class="mt-2 text-xs font-semibold text-red-600">
                            {{ form.errors.matric_number }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1.5">Password</label>
                        <div class="relative">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="••••••••"
                                required
                                class="w-full border border-slate-300 rounded-xl px-4 py-3.5 pl-11 pr-12 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all"
                                autocomplete="current-password"
                            />
                            <svg class="w-5 h-5 text-slate-400 absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>

                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-4 top-3.5 text-slate-400 hover:text-indigo-600 focus:outline-none"
                                aria-label="Toggle password visibility"
                            >
                                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.543 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-2 text-xs font-semibold text-red-600">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between mt-4">
                        <label class="flex items-center">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600"
                            />
                            <span class="ml-2 text-sm font-medium text-slate-600">Remember me</span>
                        </label>
                        <Link
                            :href="route('password.request')"
                            class="text-sm font-bold text-indigo-600 hover:text-indigo-800 transition-colors"
                        >
                            Forgot password?
                        </Link>
                    </div>
                </div>

                <div class="pt-6">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-4 rounded-xl font-black uppercase tracking-widest text-white shadow-xl bg-indigo-600 hover:bg-indigo-700 shadow-indigo-600/30 transition-all disabled:opacity-70 flex justify-center items-center"
                    >
                        <span v-if="!form.processing">Sign In</span>
                        <svg v-else class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </form>

            <p class="mt-8 text-center text-sm text-slate-600">
                New here?
                <Link :href="route('register')" class="font-bold text-indigo-600 hover:text-indigo-800">
                    Create an account
                </Link>
            </p>
        </div>
    </div>
        </div>
    </div>
</template>
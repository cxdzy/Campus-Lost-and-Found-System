<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    matric_number: '',
    telegram_chat_id: '',
    terms: false,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <div
        class="min-h-screen w-full bg-white"
        style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;"
    >
        <div class="flex min-h-screen w-full bg-white">
            <div class="hidden lg:flex w-1/2 bg-indigo-900 relative overflow-hidden flex-col justify-between p-12">
                <div class="absolute inset-0 z-0">
                    <img
                        src="https://images.unsplash.com/photo-1541339907198-e08756dedf3f?q=80&w=1470&auto=format&fit=crop"
                        alt="Campus Library"
                        class="w-full h-full object-cover opacity-30 mix-blend-overlay"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-indigo-950 via-indigo-900/80 to-indigo-900/40"></div>
                </div>

                <div class="relative z-10 flex items-center">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center mr-3 shadow-lg">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <span class="text-2xl font-black text-white tracking-tight">Campus L&amp;F</span>
                </div>

                <div class="relative z-10 mb-12">
                    <h1 class="text-4xl lg:text-5xl font-extrabold text-white leading-tight mb-6">
                        This is the place to find <br />
                        <span class="text-indigo-400">what you are searching for.</span>
                    </h1>
                    <p class="text-lg text-indigo-200 max-w-md">
                        Our AI-Powered Campus Lost &amp; Found System connects lost items with their rightful owners using advanced Vision AI and geographic proximity matching.
                    </p>

                    <div class="flex items-center space-x-4 mt-10">
                        <div class="flex -space-x-3">
                            <img class="w-10 h-10 rounded-full border-2 border-indigo-900 object-cover" src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop" alt="User" />
                            <img class="w-10 h-10 rounded-full border-2 border-indigo-900 object-cover" src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=100&auto=format&fit=crop" alt="User" />
                            <img class="w-10 h-10 rounded-full border-2 border-indigo-900 object-cover" src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop" alt="User" />
                        </div>
                        <p class="text-sm font-medium text-indigo-200">Join thousands of students securing their belongings.</p>
                    </div>
                </div>

                <div class="absolute inset-0 z-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(#ffffff 2px, transparent 2px); background-size: 30px 30px;"></div>
            </div>

            <div class="w-full lg:w-1/2 flex items-center justify-center p-8 sm:p-12 relative overflow-y-auto">
                <div class="absolute top-8 left-8 flex items-center lg:hidden">
                    <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center mr-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <span class="text-xl font-bold text-slate-900">Campus L&amp;F</span>
                </div>

                <div class="w-full max-w-md my-auto">
                    <div class="mb-8 text-center lg:text-left mt-12 lg:mt-0">
                        <h2 class="text-3xl font-black text-slate-900 mb-2">Create Account</h2>
                        <p class="text-slate-500 font-medium">Start reporting and tracking your lost items.</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">Full Name</label>
                            <div class="relative">
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Mohamad Ali"
                                    required
                                    class="w-full border border-slate-300 rounded-xl px-4 py-3 pl-11 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all"
                                    autocomplete="name"
                                />
                                <svg class="w-5 h-5 text-slate-400 absolute left-4 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <p v-if="form.errors.name" class="mt-2 text-xs font-semibold text-red-600">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1.5">Matric Number</label>
                                <input
                                    v-model="form.matric_number"
                                    type="text"
                                    placeholder="e.g., 2024123456"
                                    required
                                    class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all"
                                    autocomplete="username"
                                />
                                <p v-if="form.errors.matric_number" class="mt-2 text-xs font-semibold text-red-600">
                                    {{ form.errors.matric_number }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1.5">Telegram Handle</label>
                                <input
                                    v-model="form.telegram_chat_id"
                                    type="text"
                                    placeholder="@username"
                                    class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all"
                                    autocomplete="off"
                                />
                                <p v-if="form.errors.telegram_chat_id" class="mt-2 text-xs font-semibold text-red-600">
                                    {{ form.errors.telegram_chat_id }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">Password</label>
                            <input
                                v-model="form.password"
                                type="password"
                                placeholder="Create a secure password"
                                required
                                class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all"
                                autocomplete="new-password"
                            />
                            <p v-if="form.errors.password" class="mt-2 text-xs font-semibold text-red-600">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1.5">Confirm Password</label>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                placeholder="Repeat your password"
                                required
                                class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all"
                                autocomplete="new-password"
                            />
                            <p v-if="form.errors.password_confirmation" class="mt-2 text-xs font-semibold text-red-600">
                                {{ form.errors.password_confirmation }}
                            </p>
                        </div>

                        <div class="flex items-start mt-4">
                            <input
                                v-model="form.terms"
                                type="checkbox"
                                class="mt-1 w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600"
                            />
                            <span class="ml-2 text-xs font-medium text-slate-600">
                                I agree to the University
                                <a href="#" class="text-indigo-600 font-bold hover:underline">Terms of Service</a>
                                and privacy guidelines.
                            </span>
                        </div>
                        <p v-if="form.errors.terms" class="text-xs font-semibold text-red-600">
                            {{ form.errors.terms }}
                        </p>

                        <div class="pt-4">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full py-4 rounded-xl font-black uppercase tracking-widest text-white shadow-xl bg-indigo-600 hover:bg-indigo-700 shadow-indigo-600/30 transition-all disabled:opacity-70 flex justify-center items-center"
                            >
                                <span v-if="!form.processing">Create Account</span>
                                <svg v-else class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </button>
                        </div>
                    </form>

                    <p class="mt-8 text-center text-sm text-slate-600">
                        Already have an account?
                        <Link :href="route('login')" class="font-bold text-indigo-600 hover:text-indigo-800">
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

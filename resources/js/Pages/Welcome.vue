<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin:     { type: Boolean, default: false },
    totalFound:   { type: Number, default: 0 },
    totalMatched: { type: Number, default: 0 },
    totalClaimed: { type: Number, default: 0 },
});

const steps = [
    {
        number: '01',
        title:  'Report via Telegram Bot',
        desc:   'Finders snap a photo and send it through our Telegram bot. Category selection and GPS location are captured automatically.',
        icon:   'telegram',
    },
    {
        number: '02',
        title:  'AI Vision Analysis',
        desc:   'Google Cloud Vision API extracts visual tags from the photo. The matching engine scores every open Lost report by tag overlap and GPS proximity.',
        icon:   'ai',
    },
    {
        number: '03',
        title:  'Instant Match Alert',
        desc:   'When confidence exceeds 80%, the system fires a Telegram notification to the item owner with a secure OTP to claim at the security desk.',
        icon:   'alert',
    },
];

const features = [
    {
        title: 'AI-Powered Matching',
        desc:  'Google Cloud Vision tags found items automatically. Our scoring engine compares visual tags and GPS proximity to surface the best matches.',
        icon:  'brain',
        color: 'bg-violet-100 text-violet-600',
    },
    {
        title: 'GPS Location Tracking',
        desc:  'Every found item carries GPS coordinates from the Telegram bot. Proximity is factored into match confidence using the Haversine formula.',
        icon:  'map',
        color: 'bg-blue-100 text-blue-600',
    },
    {
        title: 'OTP Secure Handover',
        desc:  'A time-limited one-time code is sent to the owner via Telegram. Security staff verify the code at the desk before releasing any item.',
        icon:  'lock',
        color: 'bg-emerald-100 text-emerald-600',
    },
    {
        title: 'Telegram Notifications',
        desc:  'The entire flow — submission, match alerts, and OTP delivery — runs through Telegram, so students never need to install a separate app.',
        icon:  'bell',
        color: 'bg-sky-100 text-sky-600',
    },
];
</script>

<template>
    <Head title="Campus Lost & Found — UiTM" />

    <div class="min-h-screen bg-white font-sans text-slate-800 antialiased">

        <!-- ── Navbar ───────────────────────────────────────────── -->
        <header class="sticky top-0 z-50 border-b border-slate-100 bg-white/90 backdrop-blur-sm">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-3">
                    <!-- Logo mark -->
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 shadow-sm">
                        <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                    </div>
                    <span class="text-lg font-bold tracking-tight text-slate-900">Campus Lost &amp; Found</span>
                </div>

                <nav class="flex items-center gap-3">
                    <span class="hidden text-sm text-slate-500 sm:block">UiTM Campus</span>
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                    >
                        Student Login
                    </Link>
                    <Link
                        :href="route('admin.login')"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                    >
                        Admin
                    </Link>
                </nav>
            </div>
        </header>

        <!-- ── Hero ────────────────────────────────────────────── -->
        <section class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-900 px-6 py-24 text-white sm:py-32">
            <!-- Subtle grid overlay -->
            <div class="pointer-events-none absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg%20width%3D%2260%22%20height%3D%2260%22%20viewBox%3D%220%200%2060%2060%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cg%20fill%3D%22none%22%20fill-rule%3D%22evenodd%22%3E%3Cg%20fill%3D%22%23ffffff%22%20fill-opacity%3D%220.03%22%3E%3Cpath%20d%3D%22M36%2034v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6%2034v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6%204V0H4v4H0v2h4v4h2V6h4V4H6z%22%2F%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E')] opacity-40"></div>

            <div class="relative mx-auto max-w-4xl text-center">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm font-medium text-blue-200 backdrop-blur-sm">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Powered by AI Vision + GPS Matching
                </div>

                <h1 class="text-4xl font-extrabold leading-tight tracking-tight sm:text-6xl">
                    Never Lose Your <br class="hidden sm:block" />
                    <span class="bg-gradient-to-r from-blue-300 to-indigo-300 bg-clip-text text-transparent">Belongings</span> Again
                </h1>

                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">
                    Campus Lost &amp; Found uses AI vision analysis and GPS matching to automatically connect
                    found items with their owners — all through Telegram, no app download needed.
                </p>

                <div class="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="w-full rounded-xl bg-blue-500 px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-blue-900/40 transition hover:bg-blue-400 sm:w-auto"
                    >
                        Report a Lost Item →
                    </Link>
                    <a
                        href="#how-it-works"
                        class="w-full rounded-xl border border-white/20 bg-white/10 px-8 py-3.5 text-base font-semibold text-white backdrop-blur-sm transition hover:bg-white/20 sm:w-auto"
                    >
                        See How It Works
                    </a>
                </div>
            </div>
        </section>

        <!-- ── Stats ────────────────────────────────────────────── -->
        <section class="border-b border-slate-100 bg-slate-50 px-6 py-12">
            <div class="mx-auto max-w-4xl">
                <div class="grid grid-cols-1 gap-8 text-center sm:grid-cols-3">
                    <div>
                        <p class="text-4xl font-extrabold text-blue-600">{{ totalFound.toLocaleString() }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">Items Found &amp; Logged</p>
                    </div>
                    <div>
                        <p class="text-4xl font-extrabold text-indigo-600">{{ totalMatched.toLocaleString() }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">AI Match Alerts Fired</p>
                    </div>
                    <div>
                        <p class="text-4xl font-extrabold text-emerald-600">{{ totalClaimed.toLocaleString() }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">Items Successfully Claimed</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── How It Works ─────────────────────────────────────── -->
        <section id="how-it-works" class="px-6 py-20 sm:py-28">
            <div class="mx-auto max-w-5xl">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-blue-600">The Process</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">How It Works</h2>
                    <p class="mx-auto mt-4 max-w-xl text-base text-slate-500">
                        From Telegram submission to OTP handover — everything is automated.
                    </p>
                </div>

                <div class="mt-16 grid gap-8 sm:grid-cols-3">
                    <div
                        v-for="step in steps"
                        :key="step.number"
                        class="relative rounded-2xl border border-slate-100 bg-white p-8 shadow-sm"
                    >
                        <!-- Step number -->
                        <span class="text-5xl font-black text-slate-100 select-none">{{ step.number }}</span>

                        <!-- Icon -->
                        <div class="mt-3 mb-4">
                            <!-- Telegram icon -->
                            <template v-if="step.icon === 'telegram'">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100">
                                    <svg class="h-6 w-6 text-blue-600" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                    </svg>
                                </div>
                            </template>
                            <!-- AI icon -->
                            <template v-if="step.icon === 'ai'">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-100">
                                    <svg class="h-6 w-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15M14.25 3.104c.251.023.501.05.75.082M19.8 15l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 1-6.23-.607L4.2 15m15.6 0l1.232 4.853A1.5 1.5 0 0 1 19.575 21H4.425a1.5 1.5 0 0 1-1.257-2.147L4.2 15"/>
                                    </svg>
                                </div>
                            </template>
                            <!-- Alert icon -->
                            <template v-if="step.icon === 'alert'">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100">
                                    <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                                    </svg>
                                </div>
                            </template>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900">{{ step.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ step.desc }}</p>

                        <!-- Connector arrow (hidden on last) -->
                        <div
                            v-if="step.number !== '03'"
                            class="absolute -right-4 top-1/2 hidden -translate-y-1/2 text-slate-300 sm:block"
                        >
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Features ─────────────────────────────────────────── -->
        <section class="bg-slate-50 px-6 py-20 sm:py-28">
            <div class="mx-auto max-w-5xl">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-blue-600">Built for UiTM</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Key Features</h2>
                    <p class="mx-auto mt-4 max-w-xl text-base text-slate-500">
                        A complete pipeline from item discovery to secure owner handover.
                    </p>
                </div>

                <div class="mt-12 grid gap-6 sm:grid-cols-2">
                    <div
                        v-for="f in features"
                        :key="f.title"
                        class="flex gap-5 rounded-2xl border border-slate-100 bg-white p-7 shadow-sm transition hover:shadow-md"
                    >
                        <!-- Icon -->
                        <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-xl', f.color]">
                            <!-- brain -->
                            <template v-if="f.icon === 'brain'">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15M14.25 3.104c.251.023.501.05.75.082M19.8 15l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 1-6.23-.607L4.2 15m15.6 0l1.232 4.853A1.5 1.5 0 0 1 19.575 21H4.425a1.5 1.5 0 0 1-1.257-2.147L4.2 15"/>
                                </svg>
                            </template>
                            <!-- map -->
                            <template v-if="f.icon === 'map'">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0z"/>
                                </svg>
                            </template>
                            <!-- lock -->
                            <template v-if="f.icon === 'lock'">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25z"/>
                                </svg>
                            </template>
                            <!-- bell -->
                            <template v-if="f.icon === 'bell'">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                                </svg>
                            </template>
                        </div>

                        <div>
                            <h3 class="font-bold text-slate-900">{{ f.title }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ f.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CTA ──────────────────────────────────────────────── -->
        <section class="bg-gradient-to-br from-blue-600 to-indigo-700 px-6 py-20 text-white">
            <div class="mx-auto max-w-3xl text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Lost something on campus?</h2>
                <p class="mx-auto mt-4 max-w-xl text-blue-100">
                    Log in with your matric number to report a lost item and receive an instant Telegram notification when it's found.
                </p>
                <Link
                    v-if="canLogin"
                    :href="route('login')"
                    class="mt-8 inline-block rounded-xl bg-white px-8 py-3.5 text-base font-semibold text-blue-700 shadow-lg transition hover:bg-blue-50"
                >
                    Get Started — Student Login
                </Link>
            </div>
        </section>

        <!-- ── Footer ───────────────────────────────────────────── -->
        <footer class="border-t border-slate-100 bg-white px-6 py-8 text-center text-sm text-slate-400">
            <p>Campus Lost &amp; Found · UiTM · ITT626 Back-End Technology Project</p>
        </footer>

    </div>
</template>

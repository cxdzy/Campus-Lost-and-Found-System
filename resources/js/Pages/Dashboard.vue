<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage();

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    myReports: {
        type: Array,
        default: () => [],
    },
});

const activeTab = ref('gallery');
const activeCategory = ref('All');
const searchQuery = ref('');
const isSubmitting = ref(false);
const showSuccess = ref(false);
const isSavingSettings = ref(false);
const settingsSaved = ref(false);
const isLoadingGallery = ref(false);
const galleryError = ref('');
const reportError = ref('');
const categoryError = ref('');

// Reactive container tracking the local desktop upload preview URL state
const imagePreviewUrl = ref(null);

const userProfile = ref({
    name: page.props.auth?.user?.name ?? 'Student',
    matric: page.props.auth?.user?.matric_number ?? '2024123456',
    telegram: page.props.auth?.user?.telegram_chat_id ?? 'username',
    notifications: true,
});

const categoryOptions = ref([]);
const categories = computed(() => ['All', ...categoryOptions.value.map((category) => category.category_name)]);

const reportForm = ref({
    type: 'Lost',
    title: '',
    categoryId: null,
    features: '',
    locationName: '',
    coords: '',
    imageFile: null, // UPGRADED: Holds the raw File binary binary signature bundle
});

const isFoundReport = computed(() => reportForm.value.type === 'Found');

const galleryItems = ref([]);

const filteredItems = computed(() => {
    return galleryItems.value.filter((item) => {
        const matchesCategory = activeCategory.value === 'All' || item.category === activeCategory.value;
        const matchesSearch = (item.title ?? '').toLowerCase().includes(searchQuery.value.trim().toLowerCase());
        return matchesCategory && matchesSearch;
    });
});

const normalizeImagePath = (path) => {
    if (!path) {
        return '/images/placeholder.jpg';
    }
    // If already an absolute URL, return as-is
    if (path.startsWith('http')) {
        return path;
    }

    // If path already starts with a slash (e.g. /storage/...), build an absolute URL
    if (path.startsWith('/')) {
        return window.location.origin + path;
    }

    // Otherwise assume a storage relative path and prefix with /storage/
    return window.location.origin + `/storage/${path}`;
};

const formatTimeAgo = (value) => {
    if (!value) {
        return 'just now';
    }

    const time = new Date(value).getTime();
    if (Number.isNaN(time)) {
        return 'recently';
    }

    const seconds = Math.max(1, Math.floor((Date.now() - time) / 1000));
    const intervals = [
        { label: 'year', seconds: 31536000 },
        { label: 'month', seconds: 2592000 },
        { label: 'day', seconds: 86400 },
        { label: 'hour', seconds: 3600 },
        { label: 'minute', seconds: 60 },
    ];

    for (const interval of intervals) {
        const count = Math.floor(seconds / interval.seconds);
        if (count >= 1) {
            return `${count} ${interval.label}${count > 1 ? 's' : ''} ago`;
        }
    }

    return 'just now';
};

const mapItemToCard = (item) => {
    const rawCategory = item.category;
    const categoryName = typeof rawCategory === 'string' ? rawCategory : (rawCategory?.category_name ?? 'Uncategorized');

    return {
        id: item.id,
        title: item.title_description,
        category: categoryName,
        location: item.location_name ?? 'Unknown location',
        timeAgo: formatTimeAgo(item.created_at),
        // Prefer explicit image_url returned by backend; fallback to image_path
        image: normalizeImagePath(item.image_url ?? item.image_path),
    };
};

const mapReportToCard = (item) => ({
    id: item.id,
    title: item.title_description,
    category: typeof item.category === 'string' ? item.category : (item.category?.category_name ?? 'Uncategorized'),
    location: item.location_name ?? 'Unknown location',
    status: item.status ?? 'Pending',
    timeAgo: formatTimeAgo(item.created_at),
    image: normalizeImagePath(item.image_url ?? item.image_path),
});

const reportItems = computed(() => props.myReports.map(mapReportToCard));

const fetchCategories = async () => {
    categoryError.value = '';
    try {
        const response = await window.axios.get('/dashboard/data/categories');
        categoryOptions.value = response.data?.data ?? [];
        if (!reportForm.value.categoryId && categoryOptions.value.length > 0) {
            reportForm.value.categoryId = categoryOptions.value[0].id;
        }
    } catch (error) {
        console.error('Failed to load categories', error);
        categoryError.value = 'Unable to load categories. Please refresh.';
    }
};

const fetchGalleryItems = async () => {
    isLoadingGallery.value = true;
    galleryError.value = '';
    try {
        const response = await window.axios.get('/dashboard/data/items', {
            params: {
                type: 'Found',
            },
        });
        const items = response.data?.data ?? [];
        galleryItems.value = items.map(mapItemToCard);
    } catch (error) {
        console.error('Failed to load items', error);
        galleryError.value = 'Failed to load found items. Please refresh.';
    } finally {
        isLoadingGallery.value = false;
    }
};

// NEW: Captures browser input changes, updates file objects, and hooks preview stream paths
const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    // Validate if uploaded format is an acceptable mime-type pattern
    if (!file.type.startsWith('image/')) {
        reportError.value = 'Unsupported media format. Please supply a valid image asset (PNG, JPG).';
        return;
    }

    reportForm.value.imageFile = file;
    imagePreviewUrl.value = URL.createObjectURL(file);
};

// NEW: Drops active reference allocations from browser storage memory to prevent memory leaks
const clearSelectedImage = () => {
    reportForm.value.imageFile = null;
    if (imagePreviewUrl.value) {
        URL.revokeObjectURL(imagePreviewUrl.value);
        imagePreviewUrl.value = null;
    }
};

const submitReport = async () => {
    reportError.value = '';

    if (!reportForm.value.title.trim()) {
        reportError.value = 'Please enter an item description before submitting.';
        return;
    }

    if (isFoundReport.value && !reportForm.value.imageFile) {
        reportError.value = 'A photo is required when reporting a found item.';
        return;
    }

    if (!reportForm.value.coords) {
        reportError.value = 'Please set a map location before submitting.';
        return;
    }

    if (!reportForm.value.categoryId) {
        reportError.value = 'Please select a category before submitting.';
        return;
    }

    const coords = reportForm.value.coords.split(',').map((part) => Number(part.trim()));
    if (coords.length < 2 || coords.some((value) => Number.isNaN(value))) {
        reportError.value = 'Invalid coordinates. Please set the map location again.';
        return;
    }

    const [latitude, longitude] = coords;
    const userId = page.props.auth?.user?.id;
    if (!userId) {
        reportError.value = 'Unable to identify your user profile. Please re-login.';
        return;
    }

    isSubmitting.value = true;

    // UPGRADED: Instantiating standard Multi-part FormData object tracking structure to pass file data stream bytes safely
    const dataPayload = new FormData();
    dataPayload.append('user_id', userId);
    dataPayload.append('category_id', reportForm.value.categoryId);
    dataPayload.append('type', reportForm.value.type);
    dataPayload.append('title_description', reportForm.value.title);
    dataPayload.append('latitude', latitude);
    dataPayload.append('longitude', longitude);
    dataPayload.append('location_name', reportForm.value.locationName || 'Unknown location');
    dataPayload.append('status', 'Pending');
    
    if (reportForm.value.features) {
        dataPayload.append('features', reportForm.value.features);
    }

    // Attach raw file object bytes if user maps placeholder, else leave backend parameters empty (nullable support tracking)
    if (reportForm.value.imageFile) {
        dataPayload.append('image_file', reportForm.value.imageFile);
    }

    try {
        await window.axios.post('/dashboard/data/items', dataPayload, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        reportForm.value.type = 'Lost';
        reportForm.value.title = '';
        reportForm.value.features = '';
        reportForm.value.locationName = '';
        reportForm.value.coords = '';
        clearSelectedImage();
        // Refresh gallery so newly uploaded found items appear immediately
        try {
            await fetchGalleryItems();
        } catch (e) {
            // ignore fetch errors here; the gallery will attempt to load normally later
        }

        activeTab.value = 'profile';
        showSuccess.value = true;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => {
            showSuccess.value = false;
        }, 5000);
    } catch (error) {
        console.error('Failed to submit report', error);
        reportError.value = 'Unable to submit the report right now. Please try again.';
    } finally {
        isSubmitting.value = false;
    }
};

const saveSettings = () => {
    isSavingSettings.value = true;
    setTimeout(() => {
        isSavingSettings.value = false;
        settingsSaved.value = true;
        setTimeout(() => {
            settingsSaved.value = false;
        }, 3000);
    }, 1000);
};

const viewItem = (item) => {
    console.log(`Opening detail view for: ${item.title}`);
};

const simulateMapPin = () => {
    reportForm.value.coords = '3.0697, 101.5037';
};

onMounted(() => {
    if (props.items.length) {
        galleryItems.value = props.items.map(mapItemToCard);
    }
    fetchCategories();
    fetchGalleryItems();
});
</script>

<template>
    <Head title="Campus Lost & Found" />

    <div class="text-gray-900 min-h-screen flex flex-col dashboard-body">
        <div class="flex flex-col min-h-screen w-full">
            <header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 flex items-center bg-indigo-600 text-white p-2 rounded-lg">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <span class="ml-3 text-xl font-bold text-gray-900 hidden sm:block">Campus Lost &amp; Found</span>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:space-x-8">
                            <button @click="activeTab = 'gallery'" :class="['inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium h-full transition-colors', activeTab === 'gallery' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700']">
                                Found Gallery
                            </button>
                            <button @click="activeTab = 'report'" :class="['inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium h-full transition-colors', activeTab === 'report' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700']">
                                Report Lost Item
                            </button>
                            <button @click="activeTab = 'profile'" :class="['inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium h-full transition-colors relative', activeTab === 'profile' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700']">
                                My Reports
                                <span class="ml-2 bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-xs font-bold">1 Alert</span>
                            </button>
                        </div>

                        <div class="flex items-center space-x-4">
                            <button @click="activeTab = 'settings'" :class="['p-2 rounded-full transition-colors', activeTab === 'settings' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100']">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </button>
                            <div class="flex items-center space-x-3 border-l border-gray-200 pl-4">
                                <div class="hidden md:block text-right">
                                    <div class="text-sm font-bold text-gray-900">{{ userProfile.name }}</div>
                                    <div class="text-xs text-gray-500">Student Portal</div>
                                </div>
                                <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold border border-indigo-200 shadow-sm">
                                    {{ userProfile.name.charAt(0) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <transition name="fade" mode="out-in">
                    <div v-if="activeTab === 'gallery'" class="space-y-6">
                        <div class="bg-indigo-700 rounded-3xl shadow-lg overflow-hidden relative">
                            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 20px 20px;"></div>
                            <div class="relative px-6 py-12 sm:px-12 sm:py-16 flex flex-col items-center text-center">
                                <h1 class="text-3xl font-extrabold text-white sm:text-4xl">Did you lose something?</h1>
                                <p class="mt-3 max-w-2xl text-xl text-indigo-200 sm:mt-4">Search through items recently found around campus. Our AI tags everything automatically.</p>

                                <div class="mt-8 w-full max-w-xl relative">
                                    <input v-model="searchQuery" type="text" placeholder="Search found items (e.g., Wallet, Keys, Blue)..." class="w-full bg-white rounded-xl py-4 pl-12 pr-4 shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-gray-900">
                                    <svg class="w-6 h-6 text-gray-400 absolute left-4 top-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 py-4">
                            <span class="text-sm font-medium text-gray-500 mr-2">Filter by Category:</span>
                            <button v-for="cat in categories" :key="cat" :class="['px-4 py-2 rounded-full text-sm font-medium transition-colors border', activeCategory === cat ? 'bg-gray-900 text-white border-gray-900 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50']" @click="activeCategory = cat">
                                {{ cat }}
                            </button>
                            <div class="flex-grow"></div>
                            <button class="flex items-center text-sm font-medium text-indigo-600 bg-indigo-50 px-4 py-2 rounded-lg hover:bg-indigo-100 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                                View on Map
                            </button>
                        </div>

                        <div v-if="isLoadingGallery" class="text-center text-sm text-gray-500">
                            Loading found items...
                        </div>
                        <div v-else-if="galleryError" class="text-center text-sm text-red-500">
                            {{ galleryError }}
                        </div>
                        <div v-else-if="filteredItems.length === 0" class="text-center text-sm text-gray-500">
                            No items match your filters yet.
                        </div>
                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div v-for="item in filteredItems" :key="item.id" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 cursor-pointer group" @click="viewItem(item)">
                                <div class="h-48 bg-gray-100 relative overflow-hidden">
                                    <img :src="item.image" :alt="item.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" onerror="this.src='https://via.placeholder.com/400x300?text=Missing+Image';">
                                    <div class="absolute top-3 right-3 bg-white bg-opacity-90 backdrop-blur-sm px-2 py-1 rounded text-[10px] font-bold text-gray-700 uppercase tracking-wide shadow-sm">
                                        {{ item.category }}
                                    </div>
                                </div>
                                <div class="p-5">
                                    <h3 class="text-lg font-bold text-gray-900 truncate">{{ item.title }}</h3>
                                    <p class="text-sm text-gray-500 mt-2 flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 flex-shrink-0 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        {{ item.location }}
                                    </p>
                                    <p class="text-xs text-gray-400 mt-2 flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Found {{ item.timeAgo }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'report'" class="max-w-5xl mx-auto space-y-8">
                        <div class="text-center">
                            <h2 class="text-3xl font-extrabold text-gray-900">Report a Lost Item</h2>
                            <p class="mt-2 text-lg text-gray-500">Provide details so our AI matching engine can actively search for it.</p>
                        </div>

                        <form @submit.prevent="submitReport" class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
                                <div class="p-8 md:p-10 space-y-6 bg-white">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                                        <select v-model="reportForm.type" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                            <option value="Lost">Lost</option>
                                            <option value="Found">Found</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Title / Description</label>
                                        <input v-model="reportForm.title" type="text" placeholder="e.g., Black Honda Car Keys with Red Tag" required class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                        <select v-model="reportForm.categoryId" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                            <option v-if="categoryOptions.length === 0" disabled value="">Loading categories...</option>
                                            <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                                                {{ category.category_name }}
                                            </option>
                                        </select>
                                        <p v-if="categoryError" class="mt-2 text-sm text-red-500">
                                            {{ categoryError }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Distinctive Features (Optional)</label>
                                        <textarea v-model="reportForm.features" rows="3" placeholder="Any specific scratches, stickers, or marks that make it unique?" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Reference Image <span v-if="isFoundReport" class="text-red-500">(Required for Found)</span>
                                        </label>
                                        <p class="text-xs text-gray-400 mb-2">Upload a stock or similar item image to help direct AI descriptor matching loops.</p>
                                        
                                        <div class="flex items-center space-x-4">
                                            <label class="cursor-pointer bg-white border border-gray-300 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-50 transition-colors shadow-sm inline-flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                                Select Reference Photo
                                                <input type="file" accept="image/*" class="hidden" @change="handleImageUpload">
                                            </label>
                                            
                                            <button v-if="reportForm.imageFile" type="button" @click="clearSelectedImage" class="text-xs text-red-500 font-bold hover:underline">
                                                Remove File
                                            </button>
                                        </div>

                                        <div v-if="imagePreviewUrl" class="mt-4 relative w-32 h-24 bg-gray-50 rounded-xl overflow-hidden border border-gray-200 shadow-inner">
                                            <img :src="imagePreviewUrl" class="w-full h-full object-cover" />
                                        </div>
                                    </div>

                                    <div class="bg-[#eef2ff] border border-indigo-100 rounded-xl p-5 flex items-start space-x-4">
                                        <div class="bg-indigo-100 p-3 rounded-lg flex-shrink-0">
                                            <svg class="w-6 h-6 text-indigo-600" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.892-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-gray-900">Get Notified via Telegram Bot</h4>
                                            <p class="text-sm text-gray-600 mt-1 mb-3">Our AI Matching Engine will instantly alert your linked Telegram account if we find a match.</p>
                                            <label class="inline-flex items-center cursor-pointer">
                                                <input type="checkbox" v-model="userProfile.notifications" class="sr-only peer">
                                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                                <span class="ms-3 text-sm font-medium text-gray-900">Enable Webhooks</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 border-l border-gray-100 p-8 md:p-10 flex flex-col">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Known Location</label>
                                    <p class="text-xs text-gray-500 mb-4">Click on the map to drop a pin. This helps our algorithm calculate proximity to found items.</p>

                                    <div @click="simulateMapPin" class="flex-1 w-full bg-indigo-50 rounded-xl border-2 border-dashed border-indigo-200 flex flex-col items-center justify-center relative cursor-pointer hover:bg-indigo-100 transition-colors min-h-[300px] overflow-hidden">
                                        <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(#4f46e5 1px, transparent 1px); background-size: 20px 20px;"></div>

                                        <div class="z-10 flex flex-col items-center">
                                            <svg class="w-12 h-12 text-red-500 mb-2 drop-shadow-md" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path></svg>
                                            <span class="text-sm font-bold text-indigo-900 bg-white px-4 py-2 rounded-lg shadow-sm border border-indigo-100">
                                                {{ reportForm.coords ? `Coordinates Set: ${reportForm.coords}` : 'Click to place Google Maps Pin' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-6">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Location Name</label>
                                        <input v-model="reportForm.locationName" type="text" placeholder="e.g., Library Level 3" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow">
                                    </div>

                                    <p class="mt-4 text-sm text-red-500">
                                        {{ reportError }}
                                    </p>

                                    <div class="mt-8">
                                        <button type="submit" :disabled="isSubmitting || (isFoundReport && !reportForm.imageFile)" class="w-full bg-gray-900 text-white font-bold rounded-xl py-4 flex justify-center items-center hover:bg-gray-800 transition-colors shadow-lg shadow-gray-900/20 text-lg disabled:opacity-50">
                                            <span v-if="!isSubmitting">Start AI Search Process</span>
                                            <svg v-else class="animate-spin h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div v-else-if="activeTab === 'profile'" class="max-w-4xl mx-auto space-y-6">
                        <div class="flex justify-between items-end border-b border-gray-200 pb-4 mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">My Reports &amp; Alerts</h2>
                                <p class="text-gray-500 mt-1">Track the status of items you've lost.</p>
                            </div>
                        </div>

                        <transition name="fade">
                            <div v-if="showSuccess" class="bg-green-50 border-l-4 border-green-500 rounded-r-lg p-4 flex items-center shadow-sm">
                                <div class="bg-green-100 p-2 rounded-full mr-3">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-green-800">Search Initiated Successfully</h4>
                                    <p class="text-sm text-green-600">Our matching calculation is now monitoring all incoming items. We will notify you via Telegram.</p>
                                </div>
                            </div>
                        </transition>

                        <div class="space-y-6">
                            <div v-if="reportItems.length === 0" class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm text-center">
                                <div class="mx-auto w-14 h-14 rounded-full bg-indigo-50 flex items-center justify-center mb-4">
                                    <svg class="w-7 h-7 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900">You have not reported any lost items yet</h3>
                                <p class="mt-2 text-sm text-gray-500">Once you submit a lost report, it will appear here with its current status and alerts.</p>
                            </div>

                            <div v-for="item in reportItems" :key="item.id" :class="['rounded-2xl p-6 shadow-md relative overflow-hidden flex flex-col sm:flex-row gap-6 border', item.status === 'Matched' ? 'bg-indigo-50 border-indigo-200' : 'bg-white border-gray-200']">
                                <div v-if="item.status === 'Matched'" class="absolute top-0 right-0 w-32 h-32 bg-indigo-100 rounded-bl-full -z-10"></div>

                                <div class="w-full sm:w-48 h-32 bg-white rounded-xl border border-gray-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                    <img :src="item.image" :alt="item.title" class="w-full h-full object-cover" onerror="this.src='https://via.placeholder.com/200x150?text=Report';">
                                </div>

                                <div class="flex-1 flex flex-col justify-between">
                                    <div>
                                        <div class="flex justify-between items-start gap-3">
                                            <span :class="['text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide flex items-center mb-2 inline-flex', item.status === 'Matched' ? 'bg-indigo-600' : item.status === 'Claimed' ? 'bg-emerald-600' : 'bg-blue-600']">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                {{ item.status }}
                                            </span>
                                            <span class="text-xs text-gray-400 font-medium">Reported {{ item.timeAgo }}</span>
                                        </div>
                                        <h3 :class="['text-xl font-bold', item.status === 'Matched' ? 'text-indigo-900' : 'text-gray-900']">{{ item.title }}</h3>
                                        <p :class="['text-sm mt-1', item.status === 'Matched' ? 'text-indigo-700' : 'text-gray-600']">{{ item.category }} · {{ item.location }}</p>
                                    </div>

                                    <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <div class="text-sm font-medium text-gray-700 flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                            {{ item.location }}
                                        </div>
                                        <button class="bg-white border border-indigo-200 text-indigo-700 font-bold py-2 px-6 rounded-lg hover:bg-indigo-100 transition-colors shadow-sm whitespace-nowrap">
                                            View Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'settings'" class="max-w-3xl mx-auto space-y-6">
                        <div class="flex justify-between items-end border-b border-gray-200 pb-4 mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">Account Settings</h2>
                                <p class="text-gray-500 mt-1">Manage your profile and notification preferences.</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-8">
                                <div class="flex flex-col sm:flex-row gap-8 items-start">
                                    <div class="flex flex-col items-center">
                                        <div class="w-32 h-32 rounded-full bg-indigo-100 border-4 border-white shadow-lg flex items-center justify-center overflow-hidden mb-4">
                                            <span class="text-4xl font-bold text-indigo-700">{{ userProfile.name.charAt(0) }}</span>
                                        </div>
                                        <button class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg text-sm font-bold hover:bg-indigo-100 transition-colors border border-indigo-100">
                                            Update Photo
                                        </button>
                                    </div>

                                    <form @submit.prevent="saveSettings" class="flex-1 space-y-6 w-full">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-sm font-bold text-gray-700 mb-2">Full Name</label>
                                                <input type="text" v-model="userProfile.name" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-bold text-gray-700 mb-2 flex justify-between">
                                                    Matric Number <span class="text-xs text-gray-400 font-normal flex items-center"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg> Locked</span>
                                                </label>
                                                <input type="text" v-model="userProfile.matric" class="w-full bg-gray-100 text-gray-500 border border-gray-200 rounded-xl px-4 py-3 text-sm cursor-not-allowed" readonly>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-2">Telegram Handle</label>
                                            <div class="relative">
                                                <span class="absolute left-4 top-3 text-gray-400 font-bold">@</span>
                                                <input type="text" v-model="userProfile.telegram" class="w-full border border-gray-300 rounded-xl px-4 py-3 pl-9 text-sm text-indigo-700 font-bold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                                            </div>
                                        </div>

                                        <div class="pt-4 border-t border-gray-100">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-base font-bold text-gray-900">Telegram Match Alerts</p>
                                                    <p class="text-sm text-gray-500 mt-1">Receive automated notifications when our AI finds a high-confidence match for your items.</p>
                                                </div>
                                                <label class="relative inline-flex items-center cursor-pointer ml-4">
                                                    <input type="checkbox" v-model="userProfile.notifications" class="sr-only peer">
                                                    <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600"></div>
                                                </label>
                                            </div>
                                        </div>

                                        <transition name="fade">
                                            <div v-if="settingsSaved" class="bg-green-50 border border-green-200 text-green-800 text-sm font-bold p-4 rounded-xl flex items-center">
                                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                Account preferences updated successfully.
                                            </div>
                                        </transition>

                                        <div class="pt-6 flex flex-col sm:flex-row sm:items-center gap-4">
                                            <button type="submit" :disabled="isSavingSettings" class="flex-1 bg-indigo-600 text-white font-bold rounded-xl py-3.5 flex justify-center items-center hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/20 disabled:opacity-70">
                                                <span v-if="!isSavingSettings">Save Changes</span>
                                                <svg v-else class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/xl" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            </button>

                                            <Link :href="route('logout')" method="post" as="button" class="px-6 py-3.5 text-red-600 font-bold text-sm bg-white border border-red-200 hover:bg-red-50 rounded-xl transition-colors text-center">
                                                Sign Out
                                            </Link>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </main>
        </div>
    </div>
</template>
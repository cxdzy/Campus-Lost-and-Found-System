<template>
  <div class="p-6 space-y-6">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Reports</h1>
        <p class="mt-1 text-sm text-slate-500">Create or update lost and found records.</p>
      </div>
      <button
        type="button"
        class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/15 hover:bg-slate-800"
        @click="openCreate"
      >
        Add Report
      </button>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <label class="space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">User</span>
          <select v-model="form.user_id" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="" disabled>Select user</option>
            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.matric_number }})</option>
          </select>
          <p v-if="errors.user_id" class="text-xs text-rose-600">{{ errors.user_id }}</p>
        </label>

        <label class="space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Category</span>
          <select v-model="form.category_id" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="" disabled>Select category</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.category_name }}</option>
          </select>
          <p v-if="errors.category_id" class="text-xs text-rose-600">{{ errors.category_id }}</p>
        </label>

        <label class="space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Type</span>
          <select v-model="form.type" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="Lost">Lost</option>
            <option value="Found">Found</option>
          </select>
          <p v-if="errors.type" class="text-xs text-rose-600">{{ errors.type }}</p>
        </label>

        <label class="space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Status</span>
          <select v-model="form.status" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
          </select>
          <p v-if="errors.status" class="text-xs text-rose-600">{{ errors.status }}</p>
        </label>

        <label class="space-y-2 md:col-span-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Title</span>
          <input v-model.trim="form.title_description" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Item description">
          <p v-if="errors.title_description" class="text-xs text-rose-600">{{ errors.title_description }}</p>
        </label>

        <label class="space-y-2 md:col-span-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Location Name</span>
          <input v-model.trim="form.location_name" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Library, Cafeteria, etc.">
          <p v-if="errors.location_name" class="text-xs text-rose-600">{{ errors.location_name }}</p>
        </label>

        <label class="space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Latitude</span>
          <input v-model.trim="form.latitude" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="3.0712">
          <p v-if="errors.latitude" class="text-xs text-rose-600">{{ errors.latitude }}</p>
        </label>

        <label class="space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Longitude</span>
          <input v-model.trim="form.longitude" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="101.4984">
          <p v-if="errors.longitude" class="text-xs text-rose-600">{{ errors.longitude }}</p>
        </label>

        <label class="space-y-2 md:col-span-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Reference Image</span>
          <input type="file" accept="image/*" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" @change="handleImageChange">
          <p class="text-xs text-slate-400" v-if="form.type === 'Found'">Required when creating a Found report.</p>
          <p v-if="errors.image_file || errors.image_path" class="text-xs text-rose-600">{{ errors.image_file || errors.image_path }}</p>
        </label>
      </div>

      <div class="mt-6 flex flex-wrap gap-3">
        <button type="button" class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60" :disabled="saving" @click="submitForm">
          {{ saving ? 'Saving...' : (editingReportId ? 'Update Report' : 'Create Report') }}
        </button>
        <button v-if="editingReportId" type="button" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="resetForm">
          Cancel Edit
        </button>
      </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Current Reports</h2>
      </div>

      <div v-if="loading" class="px-6 py-8 text-sm text-slate-500">Loading reports...</div>
      <div v-else-if="reports.length === 0" class="px-6 py-8 text-sm text-slate-500">No reports found.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Title</th>
              <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Type</th>
              <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Status</th>
              <th class="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="report in reports" :key="report.id">
              <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ report.title_description }}</td>
              <td class="px-6 py-4 text-sm text-slate-500">{{ report.type }}</td>
              <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ report.status }}</td>
              <td class="px-6 py-4 text-right space-x-3">
                <button type="button" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" @click="openEdit(report)">Edit</button>
                <button type="button" class="text-sm font-semibold text-rose-600 hover:text-rose-500" @click="requestDelete(report)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-slate-900">Delete report?</h3>
        <p class="mt-2 text-sm text-slate-500">This will permanently remove {{ pendingDelete?.title_description }}.</p>
        <div class="mt-6 flex justify-end gap-3">
          <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="closeDeleteModal">Cancel</button>
          <button type="button" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500" :disabled="deleting" @click="confirmDelete">
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const reports = ref([])
const users = ref([])
const categories = ref([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const editingReportId = ref(null)
const showDeleteModal = ref(false)
const pendingDelete = ref(null)
const imageFile = ref(null)
const imagePreview = ref('')
const errors = reactive({})

const statusOptions = ['Pending', 'Matched', 'Claimed', 'Returned']

const emptyForm = () => ({
  user_id: '',
  category_id: '',
  type: 'Found',
  title_description: '',
  latitude: '',
  longitude: '',
  location_name: '',
  status: 'Pending',
  image_path: '',
})

const form = reactive(emptyForm())

const clearErrors = () => {
  Object.keys(errors).forEach((key) => delete errors[key])
}

const pushToast = (type, title, message = '') => {
  window.dispatchEvent(new CustomEvent('admin-toast', { detail: { type, title, message } }))
}

const normalizeError = (error) => {
  const responseErrors = error?.response?.data?.errors
  clearErrors()

  if (responseErrors) {
    Object.entries(responseErrors).forEach(([key, value]) => {
      errors[key] = Array.isArray(value) ? value[0] : String(value)
    })
  }
}

const fetchReports = async () => {
  loading.value = true
  try {
    const response = await window.axios.get('/admin/api/reports')
    reports.value = response.data?.data ?? []
  } catch (error) {
    pushToast('error', 'Unable to load reports', 'Refresh the page and try again.')
  } finally {
    loading.value = false
  }
}

const fetchReferenceData = async () => {
  const [usersResponse, categoriesResponse] = await Promise.all([
    window.axios.get('/admin/api/users'),
    window.axios.get('/dashboard/data/categories'),
  ])

  users.value = usersResponse.data?.data ?? []
  categories.value = categoriesResponse.data?.data ?? []

  if (!form.user_id && users.value.length > 0) {
    form.user_id = users.value[0].id
  }

  if (!form.category_id && categories.value.length > 0) {
    form.category_id = categories.value[0].id
  }
}

const resetImage = () => {
  imageFile.value = null
  imagePreview.value = ''
}

const handleImageChange = (event) => {
  const file = event.target.files?.[0]
  if (!file) return

  imageFile.value = file
  imagePreview.value = URL.createObjectURL(file)
}

const resetForm = () => {
  editingReportId.value = null
  Object.assign(form, emptyForm())
  resetImage()
  clearErrors()
}

const openCreate = () => {
  resetForm()
  pushToast('info', 'Create mode', 'Fill the form below to add a report.')
}

const openEdit = (report) => {
  editingReportId.value = report.id
  form.user_id = report.user_id ?? ''
  form.category_id = report.category_id ?? ''
  form.type = report.type ?? 'Found'
  form.title_description = report.title_description ?? ''
  form.latitude = report.latitude ?? ''
  form.longitude = report.longitude ?? ''
  form.location_name = report.location_name ?? ''
  form.status = report.status ?? 'Pending'
  form.image_path = report.image_path ?? ''
  imagePreview.value = report.image_url ?? (report.image_path ? `/storage/${report.image_path}` : '')
  imageFile.value = null
  clearErrors()
  pushToast('info', 'Edit mode', `Editing report #${report.id}.`)
}

const buildPayload = () => {
  const payload = new FormData()
  payload.append('user_id', String(form.user_id))
  payload.append('category_id', String(form.category_id))
  payload.append('type', form.type)
  payload.append('title_description', form.title_description)
  payload.append('latitude', String(form.latitude))
  payload.append('longitude', String(form.longitude))
  payload.append('location_name', form.location_name)
  payload.append('status', form.status)

  if (form.image_path) payload.append('image_path', form.image_path)
  if (imageFile.value) payload.append('image_file', imageFile.value)

  return payload
}

const submitForm = async () => {
  clearErrors()

  const missingRequired = !form.user_id || !form.category_id || !form.type || !form.title_description || !form.latitude || !form.longitude || !form.location_name || !form.status
  const imageRequired = form.type === 'Found' && !imageFile.value && !form.image_path

  if (missingRequired || imageRequired) {
    pushToast('warning', 'Missing fields', imageRequired ? 'Found reports require an image.' : 'Please complete all required fields.')
    return
  }

  saving.value = true
  try {
    const payload = buildPayload()

    if (editingReportId.value) {
      payload.append('_method', 'PATCH')
      await window.axios.post(`/admin/api/reports/${editingReportId.value}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      pushToast('success', 'Report updated', `Report #${editingReportId.value} has been updated.`)
    } else {
      await window.axios.post('/admin/api/reports', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      pushToast('success', 'Report created', `${form.title_description} has been created successfully.`)
    }

    await fetchReports()
    resetForm()
  } catch (error) {
    normalizeError(error)
    pushToast('error', 'Save failed', 'Please fix the highlighted fields and try again.')
  } finally {
    saving.value = false
  }
}

const requestDelete = (report) => {
  pendingDelete.value = report
  showDeleteModal.value = true
}

const closeDeleteModal = () => {
  showDeleteModal.value = false
  pendingDelete.value = null
}

const confirmDelete = async () => {
  if (!pendingDelete.value) return

  deleting.value = true
  try {
    await window.axios.delete(`/admin/api/reports/${pendingDelete.value.id}`)
    pushToast('success', 'Report deleted', `Report #${pendingDelete.value.id} was removed.`)
    await fetchReports()
    closeDeleteModal()
  } catch (error) {
    pushToast('error', 'Delete failed', 'The report could not be removed.')
  } finally {
    deleting.value = false
  }
}

const imagePreviewDisplay = computed(() => imagePreview.value || '')

onMounted(async () => {
  await Promise.all([fetchReferenceData(), fetchReports()])
})
</script>

<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
export default { layout: AdminLayout }
</script>

<style scoped></style>

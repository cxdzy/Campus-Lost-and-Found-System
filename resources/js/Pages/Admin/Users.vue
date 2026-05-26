<template>
  <div class="p-6 space-y-6">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Users</h1>
        <p class="mt-1 text-sm text-slate-500">Create, edit, and remove staff or student accounts.</p>
      </div>
      <button
        type="button"
        class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/15 hover:bg-slate-800"
        @click="openCreate"
      >
        Add User
      </button>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
        <label class="space-y-2 lg:col-span-1">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Name</span>
          <input v-model.trim="form.name" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Full name">
          <p v-if="errors.name" class="text-xs text-rose-600">{{ errors.name }}</p>
        </label>

        <label class="space-y-2 lg:col-span-1">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Matric Number</span>
          <input v-model.trim="form.matric_number" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="A1234567">
          <p v-if="errors.matric_number" class="text-xs text-rose-600">{{ errors.matric_number }}</p>
        </label>

        <label class="space-y-2 lg:col-span-1">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Role</span>
          <select v-model="form.role" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white">
            <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
          </select>
          <p v-if="errors.role" class="text-xs text-rose-600">{{ errors.role }}</p>
        </label>

        <label class="space-y-2 lg:col-span-1">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Telegram Chat ID</span>
          <input v-model.trim="form.telegram_chat_id" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional">
          <p v-if="errors.telegram_chat_id" class="text-xs text-rose-600">{{ errors.telegram_chat_id }}</p>
        </label>

        <label class="space-y-2 lg:col-span-1">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Password</span>
          <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" :placeholder="editingUserId ? 'Leave blank to keep current password' : 'Set password'">
          <p v-if="errors.password" class="text-xs text-rose-600">{{ errors.password }}</p>
        </label>
      </div>

      <div class="mt-6 flex flex-wrap gap-3">
        <button type="button" class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60" :disabled="saving" @click="submitForm">
          {{ saving ? 'Saving...' : (editingUserId ? 'Update User' : 'Create User') }}
        </button>
        <button v-if="editingUserId" type="button" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="resetForm">
          Cancel Edit
        </button>
      </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Current Users</h2>
      </div>

      <div v-if="loading" class="px-6 py-8 text-sm text-slate-500">Loading users...</div>
      <div v-else-if="users.length === 0" class="px-6 py-8 text-sm text-slate-500">No users found.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Name</th>
              <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Matric</th>
              <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Role</th>
              <th class="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="user in users" :key="user.id">
              <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ user.name }}</td>
              <td class="px-6 py-4 text-sm text-slate-500">{{ user.matric_number }}</td>
              <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ user.role }}</td>
              <td class="px-6 py-4 text-right space-x-3">
                <button type="button" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" @click="openEdit(user)">Edit</button>
                <button type="button" class="text-sm font-semibold text-rose-600 hover:text-rose-500" @click="requestDelete(user)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-slate-900">Delete user?</h3>
        <p class="mt-2 text-sm text-slate-500">This will permanently remove {{ pendingDelete?.name }}.</p>
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
import { onMounted, reactive, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const roleOptions = ['Admin', 'Security']
const users = ref([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const editingUserId = ref(null)
const showPassword = ref(false)
const showDeleteModal = ref(false)
const pendingDelete = ref(null)
const errors = reactive({})

const emptyForm = () => ({
  name: '',
  matric_number: '',
  telegram_chat_id: '',
  role: 'User',
  password: '',
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

const fetchUsers = async () => {
  loading.value = true
  try {
    const response = await window.axios.get('/admin/api/users')
    users.value = response.data?.data ?? []
  } catch (error) {
    pushToast('error', 'Unable to load users', 'Refresh the page and try again.')
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  editingUserId.value = null
  Object.assign(form, emptyForm())
  showPassword.value = false
  clearErrors()
}

const openCreate = () => {
  resetForm()
  pushToast('info', 'Create mode', 'Fill the form below to add a new user.')
}

const openEdit = (user) => {
  editingUserId.value = user.id
  form.name = user.name ?? ''
  form.matric_number = user.matric_number ?? ''
  form.telegram_chat_id = user.telegram_chat_id ?? ''
  form.role = user.role ?? 'User'
  form.password = ''
  clearErrors()
  pushToast('info', 'Edit mode', `Editing ${user.name}.`)
}

const submitForm = async () => {
  clearErrors()

  if (!form.name || !form.matric_number || !form.role || (!editingUserId.value && !form.password)) {
    pushToast('warning', 'Missing fields', 'Name, matric number, role, and password are required for a new user.')
    return
  }

  saving.value = true
  try {
    if (editingUserId.value) {
      await window.axios.patch(`/admin/api/users/${editingUserId.value}`, {
        ...form,
        password: form.password || undefined,
      })
      pushToast('success', 'User updated', `${form.name} has been updated successfully.`)
    } else {
      await window.axios.post('/admin/api/users', form)
      pushToast('success', 'User created', `${form.name} has been created successfully.`)
    }

    await fetchUsers()
    resetForm()
  } catch (error) {
    normalizeError(error)
    pushToast('error', 'Save failed', 'Please fix the highlighted fields and try again.')
  } finally {
    saving.value = false
  }
}

const requestDelete = (user) => {
  pendingDelete.value = user
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
    await window.axios.delete(`/admin/api/users/${pendingDelete.value.id}`)
    pushToast('success', 'User deleted', `${pendingDelete.value.name} was removed.`)
    await fetchUsers()
    closeDeleteModal()
  } catch (error) {
    pushToast('error', 'Delete failed', 'The user could not be removed.')
  } finally {
    deleting.value = false
  }
}

onMounted(fetchUsers)
</script>

<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
export default { layout: AdminLayout }
</script>

<style scoped></style>

<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Library</h1>

        <!-- View mode toggle -->
        <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-1">
          <button
            v-for="mode in ['list', 'grid', 'icons']"
            :key="mode"
            @click="viewMode = mode"
            class="px-3 py-1.5 text-xs font-medium rounded-md capitalize transition"
            :class="viewMode === mode ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
          >
            {{ mode }}
          </button>
        </div>
      </div>

      <!-- Bulk upload -->
      <div
        class="border-2 border-dashed rounded-xl p-6 text-center mb-6 transition"
        :class="dragging ? 'border-blue-400 bg-blue-50' : 'border-slate-200 hover:border-blue-300'"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
      >
        <p class="text-sm text-slate-400 mb-2">
          Drop .epub files here or <span class="text-blue-600 font-medium cursor-pointer" @click="fileInput.click()">browse</span>
        </p>
        <p class="text-xs text-slate-300">Multiple files accepted — each uploads and parses independently.</p>
        <input ref="fileInput" type="file" accept=".epub" multiple class="hidden" @change="onFileChange" />
      </div>

      <!-- In-flight uploads -->
      <div v-if="uploading.length" class="mb-6 space-y-2">
        <div v-for="u in uploading" :key="u.tempId" class="flex items-center justify-between bg-white border border-slate-200 rounded-lg px-4 py-2 text-sm">
          <span class="truncate">{{ u.filename }}</span>
          <span class="text-xs" :class="u.status === 'failed' ? 'text-red-500' : 'text-slate-400'">
            {{ u.status === 'failed' ? (u.status_reason ?? 'Failed') : u.status }}
          </span>
        </div>
      </div>

      <div v-if="books.data.length === 0 && uploading.length === 0"
        class="bg-white border border-slate-200 rounded-xl px-6 py-12 text-center text-sm text-slate-400">
        No books yet.
      </div>

      <template v-else>
        <!-- List mode -->
        <div v-if="viewMode === 'list'" class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Cover</th>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Title</th>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Author</th>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Uploaded</th>
                  <th class="px-5 py-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="book in books.data" :key="book.id" class="hover:bg-slate-50">
                  <td class="px-5 py-3">
                    <img v-if="book.cover_url" :src="book.cover_url" class="w-8 h-11 object-cover rounded" />
                    <div v-else class="w-8 h-11 bg-slate-100 rounded"></div>
                  </td>
                  <td class="px-5 py-3 font-medium text-slate-800 max-w-xs line-clamp-2" :title="book.title">{{ book.title }}</td>
                  <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ book.author ?? '—' }}</td>
                  <td class="px-5 py-3">
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium whitespace-nowrap" :class="statusBadge(book.status)">
                      {{ book.status }}
                    </span>
                    <span v-if="book.status === 'failed' && book.status_reason" class="block text-xs text-red-400 mt-1">
                      {{ book.status_reason }}
                    </span>
                  </td>
                  <td class="px-5 py-3 text-slate-400 text-xs whitespace-nowrap">{{ book.created_at }}</td>
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-3 whitespace-nowrap">
                      <button @click="openEdit(book)" class="text-xs text-blue-500 hover:text-blue-700">Edit</button>
                      <button v-if="book.status === 'failed'" @click="retry(book)" class="text-xs text-amber-500 hover:text-amber-700">Retry</button>
                      <button @click="destroy(book)" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Grid mode -->
        <div v-if="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
          <div v-for="book in books.data" :key="book.id" class="bg-white border border-slate-200 rounded-xl p-3">
            <img v-if="book.cover_url" :src="book.cover_url" class="aspect-[2/3] object-cover w-full rounded-lg" />
            <div v-else class="aspect-[2/3] w-full bg-slate-100 rounded-lg"></div>
            <p class="font-medium text-slate-800 text-sm mt-2 line-clamp-2" :title="book.title">{{ book.title }}</p>
            <p class="text-slate-500 text-xs">{{ book.author ?? '—' }}</p>
            <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium mt-1" :class="statusBadge(book.status)">
              {{ book.status }}
            </span>
            <span v-if="book.status === 'failed' && book.status_reason" class="block text-xs text-red-400 mt-1">
              {{ book.status_reason }}
            </span>
            <div class="flex items-center gap-3 mt-2">
              <button @click="openEdit(book)" class="text-xs text-blue-500 hover:text-blue-700">Edit</button>
              <button v-if="book.status === 'failed'" @click="retry(book)" class="text-xs text-amber-500 hover:text-amber-700">Retry</button>
              <button @click="destroy(book)" class="text-xs text-red-500 hover:text-red-700">Delete</button>
            </div>
          </div>
        </div>

        <!-- Icons mode -->
        <div v-if="viewMode === 'icons'" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3">
          <div v-for="book in books.data" :key="book.id" :title="book.title" class="relative group">
            <img v-if="book.cover_url" :src="book.cover_url" class="aspect-[2/3] object-cover w-full rounded-lg" />
            <div v-else class="aspect-[2/3] w-full bg-slate-100 rounded-lg"></div>

            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 rounded-lg transition flex items-center justify-center gap-2 hidden group-hover:flex">
              <button @click="openEdit(book)" class="bg-white/90 rounded-full px-2 py-1 text-xs text-blue-600 hover:text-blue-800 shadow-sm">Edit</button>
              <button v-if="book.status === 'failed'" @click="retry(book)" class="bg-white/90 rounded-full px-2 py-1 text-xs text-amber-600 hover:text-amber-800 shadow-sm">Retry</button>
              <button @click="destroy(book)" class="bg-white/90 rounded-full px-2 py-1 text-xs text-red-600 hover:text-red-800 shadow-sm">Delete</button>
            </div>

            <p class="line-clamp-2 text-xs text-center mt-1 text-slate-600">{{ book.title }}</p>
          </div>
        </div>
      </template>

      <!-- Pagination -->
      <div v-if="books.last_page > 1" class="flex gap-1.5 mt-4 justify-center flex-wrap">
        <Link v-for="link in books.links" :key="link.label"
          :href="link.url ?? '#'"
          class="px-3 py-1.5 text-xs rounded-lg border transition"
          :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 text-slate-500 hover:border-blue-300'"
          v-html="link.label" />
      </div>
    </div>

    <!-- Edit modal -->
    <Teleport to="body">
      <div v-if="editing" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-96 shadow-xl">
          <h2 class="font-semibold mb-4 text-slate-800">Edit Book</h2>
          <label class="block text-xs text-slate-500 mb-1">Title</label>
          <input v-model="editForm.title" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none focus:ring-2 focus:ring-blue-500" />
          <label class="block text-xs text-slate-500 mb-1">Author</label>
          <input v-model="editForm.author_name" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none focus:ring-2 focus:ring-blue-500" />
          <label class="block text-xs text-slate-500 mb-1">Description</label>
          <textarea v-model="editForm.description" rows="4" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-4 outline-none focus:ring-2 focus:ring-blue-500"></textarea>
          <div class="flex gap-2 justify-end">
            <button @click="editing = null" class="px-4 py-2 text-sm text-slate-500">Cancel</button>
            <button @click="submitEdit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref, onBeforeUnmount, reactive } from 'vue'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ books: { type: Object, required: true } })

const viewMode = ref('list')
const fileInput = ref(null)
const dragging = ref(false)
const uploading = ref([])
const editing = ref(null)
const editForm = reactive({ title: '', author_name: '', description: '' })
const pollTimers = {}

const statusBadge = (status) => ({
  ready: 'bg-green-50 text-green-700',
  pending: 'bg-slate-100 text-slate-600',
  processing: 'bg-blue-50 text-blue-700',
  failed: 'bg-red-50 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600')

// Matches the server's 'max:102400' (KB) rule. A file over this line never reaches
// Laravel at all — PHP's own post_max_size cuts the request off first and returns an
// HTTP 200 with a raw PHP warning as the body, which looks like a silently "succeeded"
// upload to axios. Rejecting oversized files before they're sent avoids that entirely.
const MAX_UPLOAD_BYTES = 100 * 1024 * 1024

const uploadFile = async (file) => {
  const tempId = crypto.randomUUID()
  uploading.value.push({ tempId, filename: file.name, status: 'pending' })

  if (file.size > MAX_UPLOAD_BYTES) {
    const entry = uploading.value.find((u) => u.tempId === tempId)
    entry.status = 'failed'
    entry.status_reason = 'File exceeds the 100MB upload limit.'
    return
  }

  const fd = new FormData()
  fd.append('file', file)

  try {
    const { data } = await axios.post('/admin/library/books', fd)
    const entry = uploading.value.find((u) => u.tempId === tempId)
    entry.id = data.id
    entry.status = data.status
    if (data.status === 'pending' || data.status === 'processing') {
      startPolling(tempId, data.id)
    } else {
      finishUpload(tempId)
    }
  } catch (err) {
    const entry = uploading.value.find((u) => u.tempId === tempId)
    entry.status = 'failed'
    entry.status_reason = err.response?.data?.message ?? 'Upload failed.'
  }
}

const startPolling = (tempId, bookId) => {
  pollTimers[tempId] = setInterval(async () => {
    const { data } = await axios.get(`/admin/library/books/${bookId}/status`)
    const entry = uploading.value.find((u) => u.tempId === tempId)
    if (!entry) return
    entry.status = data.status
    entry.status_reason = data.status_reason
    if (data.status === 'ready' || data.status === 'failed') {
      clearInterval(pollTimers[tempId])
      delete pollTimers[tempId]
      if (data.status === 'ready') finishUpload(tempId)
    }
  }, 2000)
}

const finishUpload = (tempId) => {
  uploading.value = uploading.value.filter((u) => u.tempId !== tempId)
  router.reload({ only: ['books'] })
}

const onFileChange = (e) => {
  Array.from(e.target.files).forEach(uploadFile)
  e.target.value = ''
}

const onDrop = (e) => {
  dragging.value = false
  Array.from(e.dataTransfer.files).forEach(uploadFile)
}

const openEdit = (book) => {
  editing.value = book
  editForm.title = book.title
  editForm.author_name = book.author ?? ''
  editForm.description = book.description ?? ''
}

const submitEdit = async () => {
  const payload = { title: editForm.title, description: editForm.description }
  // Omit author_name entirely when blank so the backend doesn't create/attach
  // a blank-named Author (see BookController::update -- `sometimes` treats an
  // empty string as "present", not "absent").
  if (editForm.author_name.trim() !== '') {
    payload.author_name = editForm.author_name
  }
  await axios.patch(`/admin/library/books/${editing.value.id}`, payload)
  editing.value = null
  router.reload({ only: ['books'] })
}

const retry = async (book) => {
  await axios.post(`/admin/library/books/${book.id}/retry`)
  router.reload({ only: ['books'] })
}

const destroy = async (book) => {
  if (!confirm(`Delete "${book.title}"? This cannot be undone.`)) return
  await axios.delete(`/admin/library/books/${book.id}`)
  router.reload({ only: ['books'] })
}

onBeforeUnmount(() => {
  Object.values(pollTimers).forEach(clearInterval)
})
</script>

<template>
  <AdminLayout>
    <div class="max-w-3xl mx-auto">
      <div class="flex items-center gap-3 mb-6">
        <Link href="/admin/library" class="text-slate-400 hover:text-slate-600 text-sm">← Library</Link>
        <h1 class="text-2xl font-bold text-slate-800">Upload Books</h1>
      </div>

      <!-- Bulk upload -->
      <div
        class="border-2 border-dashed rounded-xl p-8 text-center mb-6 transition"
        :class="dragging ? 'border-blue-400 bg-blue-50' : 'border-slate-200 hover:border-blue-300'"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
      >
        <p class="text-sm text-slate-400 mb-2">
          Drop .epub files here or <span class="text-blue-600 font-medium cursor-pointer hover:text-blue-800 transition-colors" @click="fileInput.click()">browse</span>
        </p>
        <p class="text-xs text-slate-300">Multiple files accepted — each uploads and parses independently.</p>
        <input ref="fileInput" type="file" accept=".epub" multiple class="hidden" @change="onFileChange" />
      </div>

      <!-- In-flight uploads -->
      <div v-if="uploading.length">
        <div class="flex items-center justify-between mb-2">
          <p class="text-xs text-slate-400">
            Uploading up to {{ MAX_CONCURRENT_UPLOADS }} at a time — {{ uploading.length }} in the list.
          </p>
          <button v-if="uploading.some((u) => u.status === 'failed')" @click="clearAllFailed"
            class="text-xs text-slate-500 hover:text-slate-700 underline">
            Clear all failed
          </button>
        </div>
        <!-- Bounded height so a big batch (dozens of files) scrolls in place instead
             of growing the page indefinitely. -->
        <div class="max-h-[32rem] overflow-y-auto space-y-2 pr-1">
          <div v-for="u in uploading" :key="u.tempId" class="flex items-center justify-between bg-white border border-slate-200 rounded-lg px-4 py-2 text-sm gap-3">
            <span class="truncate flex-1">{{ u.filename }}</span>
            <span class="text-xs whitespace-nowrap" :class="u.status === 'failed' ? 'text-red-500' : 'text-slate-400'">
              {{ u.status === 'failed' ? (u.status_reason ?? 'Failed') : u.status }}
            </span>
            <div v-if="u.status === 'failed'" class="flex gap-2 flex-shrink-0">
              <button @click="retryUpload(u.tempId)" class="text-xs text-blue-600 hover:bg-blue-50 px-2 py-1 rounded-md transition-colors">Retry</button>
              <button @click="clearUpload(u.tempId)" class="text-xs text-slate-400 hover:bg-slate-100 px-2 py-1 rounded-md transition-colors">Clear</button>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-sm text-slate-400 text-center py-8">
        Nothing uploading right now.
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const fileInput = ref(null)
const dragging = ref(false)
const uploading = ref([])
const pollTimers = {}

// Matches the server's 'max:102400' (KB) rule. A file over this line never reaches
// Laravel at all — PHP's own post_max_size cuts the request off first and returns an
// HTTP 200 with a raw PHP warning as the body, which looks like a silently "succeeded"
// upload to axios. Rejecting oversized files before they're sent avoids that entirely.
const MAX_UPLOAD_BYTES = 100 * 1024 * 1024

// Dropping a big batch (e.g. a whole light-novel series) used to fire every upload
// at once — many simultaneous 100MB-class requests can exhaust PHP-FPM's worker pool
// or the server's own POST-size ceiling, which is exactly how a batch of legitimate
// files ends up failing together with "The POST data is too large." Capping how many
// run at once keeps each individual upload within what the server can actually handle.
const MAX_CONCURRENT_UPLOADS = 3
const uploadQueue = []
let activeUploads = 0

const enqueueFiles = (files) => {
  files.forEach((file) => {
    const tempId = crypto.randomUUID()
    // Keep the File object on the entry (not just its name) so a failed upload can
    // be retried later without asking the user to re-select the file.
    uploading.value.push({ tempId, filename: file.name, file, status: 'queued', status_reason: null })
    uploadQueue.push(tempId)
  })
  processQueue()
}

const processQueue = () => {
  while (activeUploads < MAX_CONCURRENT_UPLOADS && uploadQueue.length) {
    const tempId = uploadQueue.shift()
    const entry = uploading.value.find((u) => u.tempId === tempId)
    if (!entry) continue

    activeUploads++
    uploadFile(entry).finally(() => {
      activeUploads--
      processQueue()
    })
  }
}

const uploadFile = async (entry) => {
  entry.status = 'pending'
  entry.status_reason = null

  if (entry.file.size > MAX_UPLOAD_BYTES) {
    entry.status = 'failed'
    entry.status_reason = 'File exceeds the 100MB upload limit.'
    return
  }

  const fd = new FormData()
  fd.append('file', entry.file)

  try {
    const { data } = await axios.post('/admin/library/books', fd)
    entry.id = data.id
    entry.status = data.status
    if (data.status === 'pending' || data.status === 'processing') {
      startPolling(entry.tempId, data.id)
    } else {
      finishUpload(entry.tempId)
    }
  } catch (err) {
    entry.status = 'failed'
    entry.status_reason = err.response?.data?.message ?? 'Upload failed.'
  }
}

const retryUpload = (tempId) => {
  const entry = uploading.value.find((u) => u.tempId === tempId)
  if (!entry || entry.status !== 'failed') return
  entry.status = 'queued'
  entry.status_reason = null
  uploadQueue.push(tempId)
  processQueue()
}

const clearUpload = (tempId) => {
  uploading.value = uploading.value.filter((u) => u.tempId !== tempId)
}

const clearAllFailed = () => {
  uploading.value = uploading.value.filter((u) => u.status !== 'failed')
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
  const entry = uploading.value.find((u) => u.tempId === tempId)
  if (entry) toast.success(`"${entry.filename}" is ready.`)
  uploading.value = uploading.value.filter((u) => u.tempId !== tempId)
}

const onFileChange = (e) => {
  enqueueFiles(Array.from(e.target.files))
  e.target.value = ''
}

const onDrop = (e) => {
  dragging.value = false
  enqueueFiles(Array.from(e.dataTransfer.files))
}

onBeforeUnmount(() => {
  Object.values(pollTimers).forEach(clearInterval)
})
</script>

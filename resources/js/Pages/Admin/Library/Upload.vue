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

      <!-- In-flight uploads / history -->
      <div v-if="uploading.length">
        <div class="flex items-center justify-between mb-2">
          <p class="text-xs text-slate-400">
            Uploading up to {{ MAX_CONCURRENT_UPLOADS }} at a time — {{ uploading.length }} in the list.
          </p>
          <button v-if="uploading.some((u) => u.phase === 'failed')" @click="clearAllFailed"
            class="text-xs text-slate-500 hover:text-slate-700 underline">
            Clear all failed
          </button>
        </div>

        <!-- Overall batch progress -->
        <div class="mb-3">
          <div class="flex items-center justify-between mb-1">
            <p class="text-xs text-slate-500">
              {{ doneCount }} of {{ totalCount }} processed
              <span v-if="failedCount > 0" class="text-red-500"> · {{ failedCount }} failed</span>
            </p>
            <p class="text-xs text-slate-400">{{ percent }}%</p>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
            <div class="bg-emerald-500 h-full transition-all" :style="{ width: percent + '%' }"></div>
          </div>
        </div>

        <!-- Bounded height so a big batch (dozens of files) scrolls in place instead
             of growing the page indefinitely. -->
        <div class="max-h-[32rem] overflow-y-auto space-y-2 pr-1">
          <div v-for="u in uploading" :key="u.tempId" class="flex items-center justify-between bg-white border border-slate-200 rounded-lg px-4 py-2 text-sm gap-3">
            <span class="truncate flex-1">{{ u.filename }}</span>

            <span v-if="u.phase === 'queued'" class="text-xs whitespace-nowrap text-slate-400">
              Waiting to upload…
            </span>

            <div v-else-if="u.phase === 'uploading'" class="flex items-center gap-2 flex-shrink-0 w-40">
              <div class="flex-1 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                <div class="bg-blue-500 h-full transition-all" :style="{ width: u.uploadProgress + '%' }"></div>
              </div>
              <span class="text-xs whitespace-nowrap text-slate-400">Uploading… {{ u.uploadProgress }}%</span>
            </div>

            <span v-else-if="u.phase === 'processing'" class="flex items-center gap-2 text-xs whitespace-nowrap text-slate-400">
              <span class="inline-block w-3 h-3 rounded-full border-2 border-slate-300 border-t-blue-500 animate-spin"></span>
              {{ u.status === 'processing' ? 'Processing…' : 'Queued for processing…' }}
            </span>

            <div v-else-if="u.phase === 'done'" class="flex items-center gap-2 flex-shrink-0">
              <span class="text-xs whitespace-nowrap text-emerald-600">Ready</span>
              <a v-if="u.slug" :href="`/library/books/${u.slug}`" target="_blank" rel="noopener"
                class="text-xs text-emerald-600 hover:bg-emerald-50 hover:text-emerald-800 px-2 py-1 rounded-md transition-colors">Read</a>
            </div>

            <span v-else-if="u.phase === 'failed'" class="text-xs whitespace-nowrap text-red-500">
              {{ u.status_reason ?? 'Failed' }}
            </span>

            <div v-if="u.phase === 'failed'" class="flex gap-2 flex-shrink-0">
              <button v-if="u.id" @click="retryProcessing(u.tempId)" class="text-xs text-blue-600 hover:bg-blue-50 px-2 py-1 rounded-md transition-colors">Retry</button>
              <button v-else-if="u.file" @click="retryUpload(u.tempId)" class="text-xs text-blue-600 hover:bg-blue-50 px-2 py-1 rounded-md transition-colors">Retry</button>
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
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  recentUploads: { type: Array, default: () => [] },
})

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

const totalCount = computed(() => uploading.value.length)
const doneCount = computed(() => uploading.value.filter((u) => u.phase === 'done').length)
const failedCount = computed(() => uploading.value.filter((u) => u.phase === 'failed').length)
const percent = computed(() => (totalCount.value ? Math.round((doneCount.value / totalCount.value) * 100) : 0))

const mapHistoryPhase = (status) => {
  if (status === 'ready') return 'done'
  if (status === 'failed') return 'failed'
  return 'processing'
}

onMounted(() => {
  props.recentUploads.forEach((book) => {
    const tempId = `book-${book.id}`
    const phase = mapHistoryPhase(book.status)
    uploading.value.push({
      tempId,
      filename: book.title,
      file: null,
      id: book.id,
      slug: book.slug,
      status: book.status,
      status_reason: book.status_reason,
      phase,
      uploadProgress: book.status === 'ready' ? 100 : 0,
      createdAt: book.created_at,
      fromHistory: true,
    })
    if (phase === 'processing') {
      startPolling(tempId, book.id)
    }
  })
})

const enqueueFiles = (files) => {
  files.forEach((file) => {
    const tempId = crypto.randomUUID()
    // Keep the File object on the entry (not just its name) so a failed upload can
    // be retried later without asking the user to re-select the file.
    uploading.value.push({
      tempId,
      filename: file.name,
      file,
      phase: 'queued',
      status: 'queued',
      status_reason: null,
      uploadProgress: 0,
    })
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
  entry.status_reason = null

  if (entry.file.size > MAX_UPLOAD_BYTES) {
    entry.phase = 'failed'
    entry.status = 'failed'
    entry.status_reason = 'File exceeds the 100MB upload limit.'
    return
  }

  entry.phase = 'uploading'
  entry.uploadProgress = 0

  const fd = new FormData()
  fd.append('file', entry.file)

  try {
    const { data } = await axios.post('/admin/library/books', fd, {
      onUploadProgress: (evt) => {
        entry.uploadProgress = Math.round((evt.loaded / evt.total) * 100)
      },
    })
    entry.id = data.id
    entry.slug = data.slug
    entry.status = data.status
    entry.phase = 'processing'
    if (data.status === 'pending' || data.status === 'processing') {
      startPolling(entry.tempId, data.id)
    } else {
      finishUpload(entry.tempId)
    }
  } catch (err) {
    entry.phase = 'failed'
    entry.status = 'failed'
    entry.status_reason = err.response?.data?.message ?? 'Upload failed.'
  }
}

const retryUpload = (tempId) => {
  const entry = uploading.value.find((u) => u.tempId === tempId)
  if (!entry || entry.phase !== 'failed') return
  entry.phase = 'queued'
  entry.status = 'queued'
  entry.status_reason = null
  entry.uploadProgress = 0
  uploadQueue.push(tempId)
  processQueue()
}

// Used for failed entries that already have a server-side Book row (history rows,
// or a fresh upload that made it past store() before parsing failed). No File object
// is needed — the source .epub is still on disk, so this just re-dispatches parsing.
const retryProcessing = async (tempId) => {
  const entry = uploading.value.find((u) => u.tempId === tempId)
  if (!entry || entry.phase !== 'failed' || !entry.id) return
  try {
    const { data } = await axios.post(`/admin/library/books/${entry.id}/retry`)
    entry.status = data.status
    entry.status_reason = data.status_reason ?? null
    entry.phase = data.status === 'failed' ? 'failed' : 'processing'
    if (entry.phase === 'processing') startPolling(tempId, entry.id)
  } catch (err) {
    entry.status_reason = err.response?.data?.message ?? 'Retry failed.'
  }
}

const clearUpload = (tempId) => {
  uploading.value = uploading.value.filter((u) => u.tempId !== tempId)
}

const clearAllFailed = () => {
  uploading.value = uploading.value.filter((u) => u.phase !== 'failed')
}

const startPolling = (tempId, bookId) => {
  pollTimers[tempId] = setInterval(async () => {
    const { data } = await axios.get(`/admin/library/books/${bookId}/status`)
    const entry = uploading.value.find((u) => u.tempId === tempId)
    if (!entry) return
    entry.status = data.status
    entry.status_reason = data.status_reason
    entry.slug = data.slug ?? entry.slug
    entry.phase = data.status === 'failed' ? 'failed' : 'processing'
    if (data.status === 'ready' || data.status === 'failed') {
      clearInterval(pollTimers[tempId])
      delete pollTimers[tempId]
      if (data.status === 'ready') finishUpload(tempId)
    }
  }, 2000)
}

const finishUpload = (tempId) => {
  const entry = uploading.value.find((u) => u.tempId === tempId)
  if (!entry) return
  entry.phase = 'done'
  entry.uploadProgress = 100
  if (!entry.fromHistory) toast.success(`"${entry.filename}" is ready.`)
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

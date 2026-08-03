<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto">
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-slate-800">Library</h1>

        <div class="flex items-center gap-3">
          <Link href="/admin/library/upload"
            class="text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg transition-colors">
            Upload
          </Link>

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
      </div>

      <!-- Search / filter -->
      <div class="flex flex-wrap items-center gap-2 mb-6">
        <input v-model="searchInput" @input="onSearchInput" type="text"
          placeholder="Search title or author…"
          class="flex-1 min-w-[12rem] border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
        <select v-model="statusInput" @change="applyFilters"
          class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
          <option value="">All statuses</option>
          <option value="ready">Ready</option>
          <option value="active">Active (pending/processing)</option>
          <option value="pending">Pending</option>
          <option value="processing">Processing</option>
          <option value="failed">Failed</option>
        </select>
        <button v-if="searchInput || statusInput" @click="clearFilters" class="text-xs text-slate-500 hover:text-slate-700 underline">
          Clear filters
        </button>
      </div>

      <!-- Bulk selection bar -->
      <div v-if="selectedIds.size > 0 || selectAllMatchingFilter" class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg px-4 py-2.5 mb-4 text-sm">
        <span class="text-blue-800">
          {{ selectAllMatchingFilter ? `All ${books.total} book(s) matching the current filter selected` : `${selectedIds.size} selected` }}
        </span>
        <div class="flex items-center gap-3">
          <button v-if="!selectAllMatchingFilter && selectedIds.size === books.data.length && books.total > books.data.length"
            @click="selectAllMatchingFilter = true" class="text-blue-700 hover:text-blue-900 underline">
            Select all {{ books.total }} matching this filter
          </button>
          <button @click="clearSelection" class="text-slate-500 hover:bg-slate-100 px-2 py-1 rounded-md transition-colors">Clear selection</button>
          <button @click="bulkDelete" class="text-red-600 hover:bg-red-50 px-2 py-1 rounded-md font-medium transition-colors">Delete selected</button>
        </div>
      </div>

      <div class="flex justify-end mb-4">
        <button @click="deleteAll" class="text-xs text-red-500 hover:text-red-700 underline">
          Delete all books ({{ books.total }})
        </button>
      </div>

      <div v-if="books.data.length === 0"
        class="bg-white border border-slate-200 rounded-xl px-6 py-12 text-center text-sm text-slate-400">
        No books match this view.
      </div>

      <template v-else>
        <!-- List mode -->
        <div v-if="viewMode === 'list'" class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th class="px-5 py-3 w-8">
                    <input type="checkbox" :checked="allOnPageSelected" @change="toggleSelectPage" />
                  </th>
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
                    <input type="checkbox" :checked="selectedIds.has(book.id)" @change="toggleSelect(book.id)" />
                  </td>
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
                    <span v-if="book.is_stuck" class="ml-1 text-xs px-2 py-0.5 rounded-full font-medium bg-orange-50 text-orange-600">stuck</span>
                    <span v-if="book.status === 'failed' && book.status_reason" class="block text-xs text-red-400 mt-1">
                      {{ book.status_reason }}
                    </span>
                  </td>
                  <td class="px-5 py-3 text-slate-400 text-xs whitespace-nowrap">{{ book.created_at }}</td>
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-2 whitespace-nowrap">
                      <button @click="openEdit(book)" class="text-xs text-blue-600 hover:bg-blue-50 hover:text-blue-800 px-2 py-1 rounded-md transition-colors">Edit</button>
                      <button v-if="book.status === 'failed' || book.is_stuck" @click="retry(book)" class="text-xs text-amber-600 hover:bg-amber-50 hover:text-amber-800 px-2 py-1 rounded-md transition-colors">Retry</button>
                      <button v-if="book.is_stuck" @click="markFailed(book)" class="text-xs text-red-500 hover:bg-red-50 hover:text-red-700 px-2 py-1 rounded-md transition-colors">Mark failed</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Grid mode -->
        <div v-if="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
          <div v-for="book in books.data" :key="book.id" class="bg-white border border-slate-200 rounded-xl p-3 relative">
            <input type="checkbox" :checked="selectedIds.has(book.id)" @change="toggleSelect(book.id)"
              class="absolute top-2 left-2 z-10 bg-white rounded" />
            <img v-if="book.cover_url" :src="book.cover_url" class="aspect-[2/3] object-cover w-full rounded-lg" />
            <div v-else class="aspect-[2/3] w-full bg-slate-100 rounded-lg"></div>
            <p class="font-medium text-slate-800 text-sm mt-2 line-clamp-2" :title="book.title">{{ book.title }}</p>
            <p class="text-slate-500 text-xs">{{ book.author ?? '—' }}</p>
            <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium mt-1" :class="statusBadge(book.status)">
              {{ book.status }}
            </span>
            <span v-if="book.is_stuck" class="ml-1 inline-block text-xs px-2 py-0.5 rounded-full font-medium mt-1 bg-orange-50 text-orange-600">stuck</span>
            <span v-if="book.status === 'failed' && book.status_reason" class="block text-xs text-red-400 mt-1">
              {{ book.status_reason }}
            </span>
            <div class="flex items-center gap-2 mt-2">
              <button @click="openEdit(book)" class="text-xs text-blue-600 hover:bg-blue-50 hover:text-blue-800 px-2 py-1 rounded-md transition-colors">Edit</button>
              <button v-if="book.status === 'failed' || book.is_stuck" @click="retry(book)" class="text-xs text-amber-600 hover:bg-amber-50 hover:text-amber-800 px-2 py-1 rounded-md transition-colors">Retry</button>
              <button v-if="book.is_stuck" @click="markFailed(book)" class="text-xs text-red-500 hover:bg-red-50 hover:text-red-700 px-2 py-1 rounded-md transition-colors">Fail</button>
            </div>
          </div>
        </div>

        <!-- Icons mode -->
        <div v-if="viewMode === 'icons'" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3">
          <div v-for="book in books.data" :key="book.id" :title="book.title" class="relative group">
            <input type="checkbox" :checked="selectedIds.has(book.id)" @change="toggleSelect(book.id)"
              class="absolute top-2 left-2 z-10 bg-white rounded" />
            <img v-if="book.cover_url" :src="book.cover_url" class="aspect-[2/3] object-cover w-full rounded-lg" />
            <div v-else class="aspect-[2/3] w-full bg-slate-100 rounded-lg"></div>

            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 rounded-lg transition flex items-center justify-center gap-2 hidden group-hover:flex">
              <button @click="openEdit(book)" class="bg-white/90 hover:bg-blue-600 rounded-full px-2 py-1 text-xs text-blue-600 hover:text-white shadow-sm transition-colors">Edit</button>
              <button v-if="book.status === 'failed' || book.is_stuck" @click="retry(book)" class="bg-white/90 hover:bg-amber-500 rounded-full px-2 py-1 text-xs text-amber-600 hover:text-white shadow-sm transition-colors">Retry</button>
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
            <button @click="editing = null" class="px-4 py-2 text-sm text-slate-500 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
            <button @click="submitEdit" class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const { confirmAction } = useConfirm()
const toast = useToast()

const props = defineProps({
  books: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

// ── Search / filter ──────────────────────────────────────────────────────────
const searchInput = ref(props.filters.search ?? '')
const statusInput = ref(props.filters.status ?? '')
let searchDebounce = null

const applyFilters = () => {
  router.get('/admin/library', { search: searchInput.value || undefined, status: statusInput.value || undefined }, {
    preserveState: true,
    replace: true,
    onSuccess: () => clearSelection(),
  })
}

const onSearchInput = () => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(applyFilters, 400)
}

const clearFilters = () => {
  searchInput.value = ''
  statusInput.value = ''
  applyFilters()
}

// ── Selection / bulk delete ──────────────────────────────────────────────────
const selectedIds = ref(new Set())
const selectAllMatchingFilter = ref(false)

const allOnPageSelected = computed(() =>
  props.books.data.length > 0 && props.books.data.every((b) => selectedIds.value.has(b.id))
)

const toggleSelect = (id) => {
  const next = new Set(selectedIds.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selectedIds.value = next
  selectAllMatchingFilter.value = false
}

const toggleSelectPage = () => {
  const next = new Set(selectedIds.value)
  if (allOnPageSelected.value) {
    props.books.data.forEach((b) => next.delete(b.id))
  } else {
    props.books.data.forEach((b) => next.add(b.id))
  }
  selectedIds.value = next
  selectAllMatchingFilter.value = false
}

const clearSelection = () => {
  selectedIds.value = new Set()
  selectAllMatchingFilter.value = false
}

const bulkDelete = async () => {
  const count = selectAllMatchingFilter.value ? props.books.total : selectedIds.value.size
  const label = selectAllMatchingFilter.value
    ? `all ${count} book(s) matching the current filter`
    : `${count} selected book(s)`

  const ok = await confirmAction({
    title: 'Delete books',
    message: `Delete ${label}? This cannot be undone.`,
    danger: true,
    confirmLabel: 'Delete',
  })
  if (!ok) return

  const payload = selectAllMatchingFilter.value
    ? { all_matching_filter: true, search: searchInput.value || undefined, status: statusInput.value || undefined }
    : { ids: Array.from(selectedIds.value) }

  try {
    const { data } = await axios.delete('/admin/library/books/bulk', { data: payload })
    toast.success(`Deleted ${data.deleted} book(s).`)
  } catch (err) {
    toast.error(err.response?.data?.message ?? 'Could not delete the selected books.')
  }
  clearSelection()
  router.reload({ only: ['books'] })
}

const deleteAll = async () => {
  if (props.books.total === 0) return

  const ok = await confirmAction({
    title: 'Delete all books',
    message: `This permanently removes all ${props.books.total} book(s) in the library, including any active filter. This cannot be undone.`,
    danger: true,
    requireText: 'DELETE',
    confirmLabel: 'Delete everything',
  })
  if (!ok) return

  try {
    const { data } = await axios.delete('/admin/library/books/bulk', { data: { all_matching_filter: true } })
    toast.success(`Deleted ${data.deleted} book(s).`)
  } catch (err) {
    toast.error(err.response?.data?.message ?? 'Could not delete the library.')
  }
  clearSelection()
  router.reload({ only: ['books'] })
}

const viewMode = ref('list')
const editing = ref(null)
const editForm = reactive({ title: '', author_name: '', description: '' })

const statusBadge = (status) => ({
  ready: 'bg-green-50 text-green-700',
  pending: 'bg-slate-100 text-slate-600',
  processing: 'bg-blue-50 text-blue-700',
  failed: 'bg-red-50 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600')

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
  try {
    await axios.patch(`/admin/library/books/${editing.value.id}`, payload)
    toast.success('Book updated.')
  } catch (err) {
    toast.error(err.response?.data?.message ?? 'Could not update the book.')
  }
  editing.value = null
  router.reload({ only: ['books'] })
}

const retry = async (book) => {
  try {
    const { data } = await axios.post(`/admin/library/books/${book.id}/retry`)
    if (data.status === 'failed') {
      toast.warning(data.status_reason ?? `"${book.title}" could not be retried.`)
    } else {
      toast.success(`Retrying "${book.title}".`)
    }
  } catch (err) {
    toast.error(err.response?.data?.message ?? 'Could not retry this book.')
  }
  router.reload({ only: ['books'] })
}

const markFailed = async (book) => {
  try {
    await axios.post(`/admin/library/books/${book.id}/mark-failed`)
    toast.warning(`"${book.title}" marked as failed.`)
  } catch (err) {
    toast.error(err.response?.data?.message ?? 'Could not update this book.')
  }
  router.reload({ only: ['books'] })
}
</script>

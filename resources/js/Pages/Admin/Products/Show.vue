<template>
  <AdminLayout>
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs text-slate-400">{{ project.workspace?.name }}</p>
          <h1 class="text-2xl font-bold text-slate-800">{{ project.name }}</h1>
          <p v-if="project.github_repo" class="text-xs text-slate-500 mt-1">
            🐙 <a :href="`https://github.com/${project.github_repo}`" target="_blank"
              class="hover:underline">{{ project.github_repo }}</a>
          </p>
          <p v-if="project.demo_url" class="text-xs text-slate-500 mt-0.5">
            🔗 <a :href="project.demo_url" target="_blank" class="hover:underline text-orange-600">{{ project.demo_url }}</a>
          </p>
        </div>
        <Link href="/admin/products" class="text-xs text-slate-400 hover:text-slate-700">← Products</Link>
      </div>

      <!-- Media Gallery -->
      <div class="bg-white border border-slate-200 rounded-xl p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-slate-800 text-sm">Media Gallery</h2>
          <div class="flex items-center gap-3">
            <span class="text-xs text-slate-400">{{ gallery.length }} file{{ gallery.length !== 1 ? 's' : '' }}</span>
            <label class="flex items-center gap-1.5 cursor-pointer text-xs px-3 py-1.5 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors font-medium">
              <input type="file" class="hidden" multiple :accept="acceptedTypes" @change="handleFiles" ref="fileInput" :disabled="uploading" />
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              {{ uploading ? 'Uploading…' : 'Upload' }}
            </label>
          </div>
        </div>

        <!-- Progress bar -->
        <div v-if="uploading" class="mb-4 h-1.5 bg-slate-100 rounded-full overflow-hidden">
          <div class="h-full bg-orange-400 rounded-full transition-all duration-300" :style="`width:${uploadProgress}%`"></div>
        </div>

        <!-- Error -->
        <p v-if="uploadError" class="text-xs text-red-500 mb-3 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ uploadError }}</p>

        <!-- Empty state -->
        <div v-if="!gallery.length && !uploading" class="border-2 border-dashed border-slate-200 rounded-xl p-12 text-center">
          <div class="text-4xl mb-3">🖼️</div>
          <p class="text-sm text-slate-500 mb-1">No media yet</p>
          <p class="text-xs text-slate-400">Upload images or videos to showcase this project</p>
        </div>

        <!-- Gallery grid -->
        <div v-else-if="gallery.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
          <div v-for="item in gallery" :key="item.id"
            class="group relative aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-100 cursor-pointer"
            @click="openLightbox(item)">

            <img v-if="item.is_image" :src="item.url" :alt="item.filename"
              class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />

            <div v-else class="w-full h-full flex flex-col items-center justify-center gap-2 bg-slate-900">
              <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z"/>
                </svg>
              </div>
              <p class="text-xs text-slate-300 truncate w-full px-3 text-center">{{ item.filename }}</p>
            </div>

            <!-- Hover overlay -->
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-200 flex items-center justify-center">
              <svg class="w-7 h-7 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200 drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </div>

            <!-- Delete -->
            <button @click.stop="confirmDelete(item)"
              class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-red-500 text-white text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow hover:bg-red-600">
              ✕
            </button>

            <!-- Video badge -->
            <span v-if="!item.is_image" class="absolute bottom-1.5 left-1.5 text-xs bg-black/60 text-white rounded px-1.5 py-0.5">🎬</span>
          </div>
        </div>
      </div>

    </div>

    <!-- Project Details Form -->
    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <h2 class="font-semibold text-slate-800 text-sm mb-4">Project Details</h2>
      <form @submit.prevent="saveDetails" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Short description (card)</label>
          <textarea v-model="detailForm.description" rows="2"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400 resize-none"
            placeholder="One-paragraph summary shown on the portfolio card…"/>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Full details (project page)</label>
          <textarea v-model="detailForm.details" rows="6"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400 resize-y"
            placeholder="Technologies, architecture, challenges, outcomes… Displayed on the public project detail page."/>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Demo / Live URL</label>
            <input v-model="detailForm.demo_url" type="url"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400"
              placeholder="https://…"/>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">GitHub repo (owner/repo)</label>
            <input v-model="detailForm.github_repo"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400"
              placeholder="username/repo-name"/>
          </div>
        </div>
        <div class="flex items-center justify-between pt-1">
          <div>
            <label class="text-xs font-medium text-slate-600 mr-2">Status</label>
            <select v-model="detailForm.status"
              class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:border-orange-400">
              <option value="active">Active</option>
              <option value="archived">Archived</option>
            </select>
          </div>
          <div class="flex items-center gap-3">
            <span v-if="saveSuccess" class="text-xs text-green-600">✓ Saved</span>
            <span v-if="saveError" class="text-xs text-red-500">{{ saveError }}</span>
            <button type="submit" :disabled="saving"
              class="px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors">
              {{ saving ? 'Saving…' : 'Save changes' }}
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
      <div v-if="lightbox" class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
           @click.self="closeLightbox">
        <div class="relative max-w-5xl w-full flex flex-col">
          <div class="flex items-center justify-between mb-3 px-1">
            <p class="text-white/70 text-xs truncate max-w-xs">{{ lightbox.filename }}</p>
            <div class="flex items-center gap-2">
              <a :href="lightbox.url" :download="lightbox.filename"
                class="text-xs text-white/60 hover:text-white px-3 py-1.5 rounded-lg border border-white/20 hover:border-white/40 transition-colors">
                ⬇ Download
              </a>
              <button @click="closeLightbox" class="text-white/70 hover:text-white text-xl leading-none px-2">✕</button>
            </div>
          </div>

          <div class="flex items-center justify-center min-h-0">
            <img v-if="lightbox.is_image" :src="lightbox.url" :alt="lightbox.filename"
              class="max-w-full max-h-[80vh] object-contain rounded-xl shadow-2xl" />
            <video v-else :src="lightbox.url" controls autoplay
              class="max-w-full max-h-[80vh] rounded-xl shadow-2xl bg-black" style="outline:none" />
          </div>

          <div class="flex items-center justify-center gap-4 mt-4">
            <button @click="lightboxPrev" :disabled="lightboxIndex === 0"
              class="text-white/60 hover:text-white disabled:opacity-30 px-4 py-2 rounded-lg border border-white/20 hover:border-white/40 disabled:border-white/10 text-sm transition-colors">
              ‹ Prev
            </button>
            <span class="text-white/40 text-xs">{{ lightboxIndex + 1 }} / {{ gallery.length }}</span>
            <button @click="lightboxNext" :disabled="lightboxIndex === gallery.length - 1"
              class="text-white/60 hover:text-white disabled:opacity-30 px-4 py-2 rounded-lg border border-white/20 hover:border-white/40 disabled:border-white/10 text-sm transition-colors">
              Next ›
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete confirm modal -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
           @click.self="deleteTarget = null">
        <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-2xl">
          <h3 class="font-bold text-slate-800 mb-2">Delete file?</h3>
          <p class="text-sm text-slate-500 mb-5 break-all">{{ deleteTarget.filename }}</p>
          <div class="flex gap-3 justify-end">
            <button @click="deleteTarget = null"
              class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
              Cancel
            </button>
            <button @click="deleteMedia" :disabled="deleting"
              class="px-4 py-2 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600 disabled:opacity-50 transition-colors">
              {{ deleting ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'

const props = defineProps({
  project: { type: Object, default: () => ({}) },
  media:   { type: Array,  default: () => [] },
})

// ── Details form ──────────────────────────────────────────────────────────────
const detailForm = reactive({
  description: props.project.description ?? '',
  details:     props.project.details ?? '',
  demo_url:    props.project.demo_url ?? '',
  github_repo: props.project.github_repo ?? '',
  status:      props.project.status ?? 'active',
})
const saving      = ref(false)
const saveSuccess = ref(false)
const saveError   = ref(null)

const saveDetails = () => {
  saving.value = true
  saveSuccess.value = false
  saveError.value = null
  router.put(`/admin/products/${props.project.id}`, detailForm, {
    preserveScroll: true,
    onSuccess: () => { saveSuccess.value = true; setTimeout(() => { saveSuccess.value = false }, 2500) },
    onError:   (e) => { saveError.value = Object.values(e)[0] ?? 'Save failed' },
    onFinish:  () => { saving.value = false },
  })
}

const acceptedTypes  = 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime'
const fileInput      = ref(null)
const uploading      = ref(false)
const uploadProgress = ref(0)
const uploadError    = ref(null)
const gallery        = ref([...props.media])

// ── Lightbox ─────────────────────────────────────────────────────────────────
const lightbox      = ref(null)
const lightboxIndex = ref(0)

const openLightbox  = (item) => {
  lightboxIndex.value = gallery.value.findIndex(m => m.id === item.id)
  lightbox.value = item
}
const closeLightbox = () => { lightbox.value = null }
const lightboxPrev  = () => {
  if (lightboxIndex.value > 0) {
    lightboxIndex.value--
    lightbox.value = gallery.value[lightboxIndex.value]
  }
}
const lightboxNext  = () => {
  if (lightboxIndex.value < gallery.value.length - 1) {
    lightboxIndex.value++
    lightbox.value = gallery.value[lightboxIndex.value]
  }
}

// ── Keyboard navigation ───────────────────────────────────────────────────────
const onKey = (e) => {
  if (!lightbox.value) return
  if (e.key === 'ArrowLeft')  lightboxPrev()
  if (e.key === 'ArrowRight') lightboxNext()
  if (e.key === 'Escape')     closeLightbox()
}
window.addEventListener('keydown', onKey)
onUnmounted(() => window.removeEventListener('keydown', onKey))

// ── Upload ────────────────────────────────────────────────────────────────────
const handleFiles = async (event) => {
  uploadError.value = null
  uploading.value = true
  uploadProgress.value = 0
  const files = Array.from(event.target.files)
  let done = 0

  for (const file of files) {
    try {
      const fd = new FormData()
      fd.append('file', file)
      const { data } = await axios.post('/admin/media', fd)
      gallery.value.push(data)
      done++
      uploadProgress.value = Math.round((done / files.length) * 100)
    } catch (e) {
      uploadError.value = e.response?.data?.errors?.file?.[0] ?? `Upload failed: ${file.name}`
    }
  }
  uploading.value = false
  uploadProgress.value = 0
  if (fileInput.value) fileInput.value.value = ''
}

// ── Delete ────────────────────────────────────────────────────────────────────
const deleteTarget = ref(null)
const deleting     = ref(false)

const confirmDelete = (item) => { deleteTarget.value = item }
const deleteMedia   = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await axios.delete(`/admin/media/${deleteTarget.value.id}`)
    gallery.value = gallery.value.filter(m => m.id !== deleteTarget.value.id)
    if (lightbox.value?.id === deleteTarget.value.id) closeLightbox()
    deleteTarget.value = null
  } catch {
    uploadError.value = 'Failed to delete file'
  }
  deleting.value = false
}
</script>

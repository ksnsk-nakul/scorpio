<template>
  <component v-if="activeTemplateComponent" :is="activeTemplateComponent" :book="book" />

  <template v-else>
  <Head>
    <title>{{ book.title }}</title>
    <meta name="description" :content="book.description ?? book.title" />
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="/library" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← Library</a>
        <span class="text-sm font-semibold text-slate-800 truncate max-w-xs">{{ book.title }}</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-4xl mx-auto px-6">
      <div class="flex flex-col sm:flex-row gap-8 mb-10">
        <div class="w-40 flex-shrink-0 mx-auto sm:mx-0">
          <div class="aspect-[2/3] rounded-lg overflow-hidden bg-slate-100 shadow-sm">
            <img v-if="book.cover_url" :src="book.cover_url" class="w-full h-full object-cover" />
          </div>
        </div>
        <div class="flex-1">
          <h1 class="text-2xl font-bold text-slate-800 mb-2">{{ book.title }}</h1>
          <a v-if="book.series" :href="`/library/series/${book.series.slug}`" class="block text-sm text-slate-500 hover:text-orange-500 hover:underline mb-1">
            {{ book.series.name }}, Vol. {{ book.series.volume_number }}
          </a>
          <a v-if="book.author" :href="`/library/authors/${book.author.slug}`" class="text-sm text-orange-500 hover:underline">
            {{ book.author.name }}
          </a>

          <!-- Reading status tracker -->
          <div class="mt-3">
            <a v-if="!user" href="/login" class="text-xs text-slate-500 hover:text-orange-500 underline">
              Log in to track your reading
            </a>
            <div v-else class="flex items-center gap-2">
              <button v-if="!followed" @click="follow"
                class="text-xs font-medium bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg transition-colors">
                + Follow
              </button>
              <select v-else v-model="currentStatus" @change="updateStatus"
                class="border border-slate-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-orange-400">
                <option value="reading">Reading</option>
                <option value="completed">Completed</option>
                <option value="on_hold">On-Hold</option>
                <option value="plan_to_read">Plan to Read</option>
                <option value="dropped">Dropped</option>
              </select>
              <button v-if="followed" @click="unfollow" class="text-xs text-slate-400 hover:text-red-500 underline">
                Unfollow
              </button>
            </div>
            <p v-if="book.my_progress?.status && book.my_progress.last_chapter_sort_order === null" class="text-xs text-slate-400 mt-1">
              Status: {{ book.my_progress.status }}
            </p>
          </div>

          <Link v-if="hasResumePoint"
            :href="`/library/books/${book.slug}/chapters/${book.my_progress.last_chapter_sort_order}`"
            class="inline-flex items-center gap-1.5 mt-4 text-sm font-medium bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg transition-colors">
            Continue reading — Chapter {{ book.my_progress.last_chapter_sort_order + 1 }}
          </Link>

          <p v-if="book.description" class="text-sm text-slate-600 leading-relaxed mt-4">{{ book.description }}</p>
          <dl class="mt-4 space-y-1 text-xs text-slate-400">
            <div v-if="book.publisher"><dt class="inline font-medium">Publisher:</dt> <dd class="inline">{{ book.publisher }}</dd></div>
            <div v-if="book.published_date"><dt class="inline font-medium">Published:</dt> <dd class="inline">{{ book.published_date }}</dd></div>
            <div v-if="book.language"><dt class="inline font-medium">Language:</dt> <dd class="inline">{{ book.language }}</dd></div>
          </dl>
        </div>
      </div>

      <h2 class="text-lg font-semibold text-slate-800 mb-3">Chapters</h2>
      <ol class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
        <li v-for="chapter in book.chapters.data" :key="chapter.sort_order">
          <Link
            :href="`/library/books/${book.slug}/chapters/${chapter.sort_order}`"
            class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50 transition-colors"
          >
            <span class="line-clamp-1 flex items-center gap-1.5" :title="chapter.title ?? `Chapter ${chapter.sort_order + 1}`">
              <span v-if="isChapterRead(chapter)" class="text-emerald-600">✓</span>
              {{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}
            </span>
            <span class="text-slate-300">›</span>
          </Link>
        </li>
      </ol>

      <div v-if="book.chapters.last_page > 1" class="flex flex-wrap gap-1 mt-4 justify-center">
        <Link
          v-for="(link, i) in book.chapters.links"
          :key="i"
          :href="link.url ?? '#'"
          v-html="link.label"
          class="px-3 py-1.5 text-sm rounded-lg"
          :class="link.active ? 'bg-orange-500 text-white' : link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 pointer-events-none'"
        />
      </div>

      <div v-if="book.series && book.series.other_volumes.length > 0" class="mt-10">
        <h2 class="text-lg font-semibold text-slate-800 mb-3">
          More in <a :href="`/library/series/${book.series.slug}`" class="text-orange-500 hover:underline">{{ book.series.name }}</a>
        </h2>
        <ul class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
          <li v-for="volume in book.series.other_volumes" :key="volume.slug">
            <Link
              :href="`/library/books/${volume.slug}`"
              class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-slate-50 transition-colors"
            >
              <div class="w-8 h-11 rounded overflow-hidden bg-slate-100 flex-shrink-0">
                <img v-if="volume.cover_url" :src="volume.cover_url" class="w-full h-full object-cover" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs text-slate-400">Vol. {{ volume.volume_number }}</p>
                <p class="line-clamp-1" :title="volume.title">{{ volume.title }}</p>
              </div>
              <span class="text-slate-300">›</span>
            </Link>
          </li>
        </ul>
      </div>
    </main>
  </div>
  <ToastContainer />
  </template>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { useActiveTemplate } from '@/composables/useActiveTemplate'
import { resolvePublicPage } from '@/templateRegistry'
import { useToast } from '@/composables/useToast'
import ToastContainer from '@/Components/ToastContainer.vue'

const props = defineProps({ book: { type: Object, required: true } })

// ── Template resolution ──────────────────────────────────────────────────────
const { publicTemplate } = useActiveTemplate()
const activeTemplateComponent = computed(() => resolvePublicPage(publicTemplate.value, 'BookDetail'))

// ── Reading status tracker ───────────────────────────────────────────────────
// This page is public (guests included), so the follow/status control only
// renders for logged-in users (checked via the shared `auth.user` Inertia prop,
// same pattern AdminLayout.vue uses) — guests get a "log in" prompt instead of
// a control that would just 401 against the API.
const page = usePage()
const user = computed(() => page.props.auth.user)
const toast = useToast()

// The backend doesn't currently return the viewer's existing LibraryEntry status
// on this page (out of scope for this pass — see task notes), so `followed`/
// `currentStatus` are session-local UI state rather than reflecting a persisted
// value on load: a fresh page load always shows "+ Follow" even for a book the
// user already follows. Picking a status (or following) still hits the real
// idempotent backend endpoints, so nothing is lost — just not reflected until acted on this visit.
const followed = ref(false)
const currentStatus = ref('reading')

// ── Reading progress (from `book.my_progress`, see LibraryController::show) ──
const hasResumePoint = computed(() =>
  props.book.my_progress?.last_chapter_sort_order !== null && props.book.my_progress?.last_chapter_sort_order !== undefined
)

const isChapterRead = (chapter) => {
  const lastRead = props.book.my_progress?.last_chapter_sort_order
  return lastRead !== null && lastRead !== undefined && chapter.sort_order <= lastRead
}

const follow = async () => {
  try {
    const { data } = await axios.post(`/library/books/${props.book.slug}/follow`)
    currentStatus.value = data.status
    followed.value = true
    toast.success('Added to your library.')
  } catch (err) {
    toast.error(err.response?.data?.message ?? 'Could not add this book to your library.')
  }
}

const updateStatus = async () => {
  try {
    await axios.patch(`/library/books/${props.book.slug}/status`, { status: currentStatus.value })
    toast.success('Reading status updated.')
  } catch (err) {
    toast.error(err.response?.data?.message ?? 'Could not update your reading status.')
  }
}

const unfollow = async () => {
  try {
    await axios.delete(`/library/books/${props.book.slug}/follow`)
    followed.value = false
    currentStatus.value = 'reading'
    toast.success('Removed from your library.')
  } catch (err) {
    toast.error(err.response?.data?.message ?? 'Could not remove this book from your library.')
  }
}
</script>

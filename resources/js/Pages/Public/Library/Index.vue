<template>
  <component v-if="activeTemplateComponent" :is="activeTemplateComponent" :books="books" />

  <template v-else>
  <Head>
    <title>Library</title>
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="/" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← Home</a>
        <span class="text-sm font-semibold text-slate-800">Library</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-6xl mx-auto px-6">
      <h1 class="text-3xl font-bold text-slate-800 mb-8">Library</h1>

      <div class="flex items-center gap-2 mb-6">
        <input v-model="searchInput" @input="onSearchInput" type="text"
          placeholder="Search title, author, or series…"
          class="flex-1 max-w-sm border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
        <button v-if="searchInput" @click="clearSearch" class="text-xs text-slate-500 hover:text-orange-500 underline">
          Clear
        </button>
      </div>

      <div v-if="books.data.length === 0" class="text-sm text-slate-400 py-12 text-center">
        No books available yet.
      </div>

      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
        <div v-for="book in books.data" :key="book.slug">
          <Link :href="`/library/books/${book.slug}`" class="group">
            <div class="aspect-[2/3] rounded-lg overflow-hidden bg-slate-100 mb-2 shadow-sm group-hover:shadow-md transition-shadow">
              <img v-if="book.cover_url" :src="book.cover_url" class="w-full h-full object-cover" />
            </div>
            <p class="text-sm font-medium text-slate-800 line-clamp-2" :title="book.title">{{ book.title }}</p>
            <p v-if="book.author" class="text-xs text-slate-400 mt-0.5 truncate">{{ book.author }}</p>
          </Link>
          <p v-if="book.series" class="text-xs text-slate-400 mt-0.5 truncate">
            Part of:
            <a :href="`/library/series/${book.series.slug}`" target="_blank" rel="noopener" class="hover:underline hover:text-orange-500">{{ book.series.name }}</a>,
            Vol. {{ book.series.volume_number }}
          </p>
        </div>
      </div>

      <div v-if="books.last_page > 1" class="flex flex-wrap gap-1 mt-10 justify-center">
        <Link
          v-for="(link, i) in books.links"
          :key="i"
          :href="link.url ?? '#'"
          v-html="link.label"
          class="px-3 py-1.5 text-sm rounded-lg"
          :class="link.active ? 'bg-orange-500 text-white' : link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 pointer-events-none'"
        />
      </div>
    </main>
  </div>
  </template>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useActiveTemplate } from '@/composables/useActiveTemplate'
import { resolvePublicPage } from '@/templateRegistry'

const props = defineProps({
  books: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

// ── Template resolution ──────────────────────────────────────────────────────
const { publicTemplate } = useActiveTemplate()
const activeTemplateComponent = computed(() => resolvePublicPage(publicTemplate.value, 'LibraryIndex'))

// ── Search ────────────────────────────────────────────────────────────────────
const searchInput = ref(props.filters.search ?? '')
let searchDebounce = null

const applySearch = () => {
  router.get('/library', { search: searchInput.value || undefined }, {
    preserveState: true,
    replace: true,
  })
}

const onSearchInput = () => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(applySearch, 400)
}

const clearSearch = () => {
  searchInput.value = ''
  applySearch()
}
</script>

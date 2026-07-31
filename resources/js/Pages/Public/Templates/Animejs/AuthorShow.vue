<template>
  <Head>
    <title>{{ author.name }}</title>
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <!-- ── Nav ───────────────────────────────────────────────────────────
         Not AnimeJsNav — it would render fine (its settings/sections props
         default), but with a generic "Portfolio" site name since
         LibraryController::author() doesn't pass real `settings`. Keeping
         the original's minimal "← Library | {name}" bar avoids that. -->
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="/library" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← Library</a>
        <span class="text-sm font-semibold text-slate-800">{{ author.name }}</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-6xl mx-auto px-6">
      <h1 class="text-2xl font-bold text-slate-800 mb-2">{{ author.name }}</h1>
      <p v-if="author.bio" class="text-sm text-slate-500 mb-8 max-w-2xl">{{ author.bio }}</p>

      <EmptyState v-if="books.data.length === 0" message="No books by this author yet." />

      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
        <Link v-for="book in books.data" :key="book.slug" :href="`/library/books/${book.slug}`" class="reveal-item group">
          <div class="aspect-[2/3] rounded-lg overflow-hidden bg-slate-100 mb-2 shadow-sm group-hover:shadow-md transition-shadow">
            <img v-if="book.cover_url" :src="book.cover_url" :alt="book.title" class="w-full h-full object-cover" />
          </div>
          <p class="text-sm font-medium text-slate-800 line-clamp-2" :title="book.title">{{ book.title }}</p>
        </Link>
      </div>

      <Pagination :links="books.links" />
    </main>
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import Pagination from '@/Components/Shared/Pagination.vue'
import EmptyState from '@/Components/Shared/EmptyState.vue'
import { useAnimeReveal } from '@/composables/useAnimeReveal'

defineProps({
  author: { type: Object, required: true },
  books: { type: Object, required: true },
})

// Book covers scroll-reveal with a stagger, same treatment LibraryIndex.vue
// gives its grid.
useAnimeReveal('.reveal-item')
</script>

<template>
  <Head>
    <title>Library</title>
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <LibraryNav back-href="/" back-label="Home" title="Library" />

    <main class="pt-20 pb-16 max-w-6xl mx-auto px-6">
      <h1 class="text-3xl font-bold text-slate-800 mb-8">Library</h1>

      <EmptyState v-if="books.data.length === 0" message="No books available yet." />

      <BookCoverGrid v-else :books="books.data" show-author />

      <Pagination :links="books.links" />
    </main>
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import Pagination from '@/Components/Shared/Pagination.vue'
import EmptyState from '@/Components/Shared/EmptyState.vue'
import LibraryNav from '@/Components/Shared/LibraryNav.vue'
import BookCoverGrid from '@/Components/Shared/BookCoverGrid.vue'
import { useAnimeReveal } from '@/composables/useAnimeReveal'

defineProps({ books: { type: Object, required: true } })

// Book covers scroll-reveal with a stagger, same treatment ProjectDetail.vue
// gives its gallery grid.
useAnimeReveal('.reveal-item')
</script>

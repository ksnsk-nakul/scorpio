<template>
  <Head>
    <title>{{ author.name }}</title>
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <LibraryNav back-href="/library" back-label="Library" :title="author.name" />

    <main class="pt-20 pb-16 max-w-6xl mx-auto px-6">
      <h1 class="text-2xl font-bold text-slate-800 mb-2">{{ author.name }}</h1>
      <p v-if="author.bio" class="text-sm text-slate-500 mb-8 max-w-2xl">{{ author.bio }}</p>

      <EmptyState v-if="books.data.length === 0" message="No books by this author yet." />

      <BookCoverGrid v-else :books="books.data" />

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

defineProps({
  author: { type: Object, required: true },
  books: { type: Object, required: true },
})
</script>

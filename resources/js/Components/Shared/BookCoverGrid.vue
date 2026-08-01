<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
    <Link v-for="book in books" :key="book.slug" :href="`/library/books/${book.slug}`" class="reveal-item group">
      <div class="aspect-[2/3] rounded-lg overflow-hidden bg-slate-100 mb-2 shadow-sm group-hover:shadow-md transition-shadow">
        <img v-if="book.cover_url" :src="book.cover_url" :alt="book.title" class="w-full h-full object-cover" />
      </div>
      <p class="text-sm font-medium text-slate-800 line-clamp-2" :title="book.title">{{ book.title }}</p>
      <p v-if="showAuthor && book.author" class="text-xs text-slate-400 mt-0.5 truncate">{{ book.author }}</p>
    </Link>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  books: { type: Array, required: true },
  showAuthor: { type: Boolean, default: false },
})
</script>

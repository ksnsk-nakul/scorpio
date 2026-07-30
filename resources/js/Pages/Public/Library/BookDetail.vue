<template>
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
          <a v-if="book.author" :href="`/library/authors/${book.author.slug}`" class="text-sm text-orange-500 hover:underline">
            {{ book.author.name }}
          </a>
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
        <li v-for="chapter in book.chapters" :key="chapter.sort_order">
          <Link
            :href="`/library/books/${book.slug}/chapters/${chapter.sort_order}`"
            class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50 transition-colors"
          >
            <span class="line-clamp-1" :title="chapter.title ?? `Chapter ${chapter.sort_order + 1}`">
              {{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}
            </span>
            <span class="text-slate-300">›</span>
          </Link>
        </li>
      </ol>
    </main>
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({ book: { type: Object, required: true } })
</script>

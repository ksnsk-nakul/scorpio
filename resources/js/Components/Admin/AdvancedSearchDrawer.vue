<!-- resources/js/Components/Admin/AdvancedSearchDrawer.vue -->
<!-- Docked messaging-widget style drawer (LinkedIn-style): a slim minimized bar
     pinned to the bottom-right that expands into a floating panel, mounted once
     globally in AdminLayout so it's available from any admin page. -->
<template>
  <div v-if="isOpen" class="fixed bottom-0 right-6 z-50">
    <!-- Minimized bar -->
    <div v-if="isMinimized" class="w-72 bg-white border border-slate-200 rounded-t-xl shadow-lg overflow-hidden">
      <div class="h-11 px-4 flex items-center justify-between bg-slate-900">
        <button @click="expand" class="flex-1 flex items-center gap-2 text-left text-sm font-medium text-white truncate">
          Advanced Search
        </button>
        <div class="flex items-center gap-1 flex-shrink-0">
          <button @click="expand" aria-label="Expand" class="p-1 text-slate-300 hover:text-white transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6" /></svg>
          </button>
          <button @click="close" aria-label="Close" class="p-1 text-slate-300 hover:text-white transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Expanded panel -->
    <div v-else class="h-[28rem] w-96 bg-white border border-slate-200 rounded-t-xl shadow-2xl flex flex-col overflow-hidden">
      <div class="h-11 px-4 flex items-center justify-between bg-slate-900 flex-shrink-0">
        <span class="text-sm font-medium text-white truncate">Advanced Search</span>
        <div class="flex items-center gap-1 flex-shrink-0">
          <button @click="minimize" aria-label="Minimize" class="p-1 text-slate-300 hover:text-white transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
          </button>
          <button @click="close" aria-label="Close" class="p-1 text-slate-300 hover:text-white transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
          </button>
        </div>
      </div>

      <div ref="messagesEl" role="log" aria-live="polite" class="flex-1 overflow-y-auto p-4 space-y-3">
        <div v-if="messages.length === 0" class="text-xs text-slate-400 text-center py-10">
          Ask a question about your library.
        </div>

        <div v-for="(m, i) in messages" :key="i" class="max-w-[85%]" :class="m.role === 'user' ? 'ml-auto' : ''">
          <div
            class="px-3 py-2 rounded-2xl text-sm"
            :class="m.role === 'user' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-800'"
          >
            {{ m.content }}
          </div>
          <div v-if="m.citations?.length" class="mt-1.5 flex flex-wrap gap-1.5">
            <template v-for="(c, ci) in m.citations" :key="ci">
              <a
                v-if="c.book_slug"
                :href="`/library/books/${c.book_slug}/chapters/${c.chapter_sort_order ?? 0}`"
                target="_blank"
                rel="noopener"
                class="text-xs px-2 py-0.5 rounded-full bg-orange-50 text-orange-600 hover:bg-orange-100 transition-colors"
              >
                {{ c.book_title }}<template v-if="c.chapter_title"> — {{ c.chapter_title }}</template>
              </a>
              <span
                v-else
                class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500"
              >
                {{ c.book_title }}<template v-if="c.chapter_title"> — {{ c.chapter_title }}</template>
              </span>
            </template>
          </div>
        </div>

        <div v-if="loading" class="flex items-center gap-2 text-xs text-slate-400">
          <span class="inline-block w-3 h-3 rounded-full border-2 border-slate-300 border-t-blue-500 animate-spin"></span>
          Asking…
        </div>
        <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
      </div>

      <form @submit.prevent="submit" class="border-t border-slate-200 p-3 flex-shrink-0">
        <div class="flex gap-2">
          <label for="advanced-search-question" class="sr-only">Ask about your library</label>
          <input
            id="advanced-search-question"
            v-model="question"
            type="text"
            placeholder="Ask about your library…"
            class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-900"
            :disabled="loading"
          />
          <button
            type="submit"
            :disabled="loading || !question.trim()"
            class="px-3 py-2 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-700 disabled:opacity-50 transition-colors flex-shrink-0"
          >
            Ask
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import { useAdvancedSearch } from '@/composables/useAdvancedSearch'

const { isOpen, isMinimized, messages, loading, error, close, minimize, expand, ask } = useAdvancedSearch()

const question = ref('')
const messagesEl = ref(null)

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  })
}

watch(() => messages.value.length, scrollToBottom)

const submit = async () => {
  const q = question.value
  question.value = ''
  await ask(q)
  scrollToBottom()
}
</script>

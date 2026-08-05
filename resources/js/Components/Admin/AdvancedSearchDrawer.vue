<!-- resources/js/Components/Admin/AdvancedSearchDrawer.vue -->
<!-- Docked messaging-widget style drawer (LinkedIn-style): a slim minimized bar
     pinned to the bottom-right that expands into a floating panel, mounted once
     globally in AdminLayout so it's available from any admin page. -->
<template>
  <!-- Floating trigger: small circular action button, visible whenever RAG is
       available and the drawer itself isn't open. Clicking it expands straight
       into the full panel (skipping the minimized-bar state, since getting here
       already took a deliberate click) and defensively kicks off loadHistory()
       so the user's last conversation is there when the panel appears. -->
  <button
    v-if="ragAvailable && !isOpen"
    @click="openDrawer"
    aria-label="Open Advanced Search"
    class="fixed bottom-6 right-6 z-50 w-12 h-12 rounded-full bg-slate-900 shadow-lg hover:bg-slate-700 flex items-center justify-center transition-colors"
  >
    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
    </svg>
  </button>

  <div v-if="isOpen && ragAvailable" class="fixed bottom-0 right-6 z-50">
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
          <button @click="confirmDelete" aria-label="Close" class="p-1 text-slate-300 hover:text-white transition-colors">
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
          <button @click="newChat" aria-label="New chat" title="New chat" class="p-1 text-slate-300 hover:text-white transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
          </button>
          <button @click="minimize" aria-label="Minimize" class="p-1 text-slate-300 hover:text-white transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
          </button>
          <button @click="confirmDelete" aria-label="Close" class="p-1 text-slate-300 hover:text-white transition-colors">
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
            <template v-for="(c, ci) in dedupedCitations(m.citations)" :key="ci">
              <!-- Link to the book, not a specific chapter: the RAG index's
                   chapter_sort_order can go stale relative to the current
                   chapters table (e.g. after a re-parse), producing a citation
                   that points at a chapter number that no longer exists. The
                   book page is always valid, and already links to the book's
                   series (a real "list") when it belongs to one. Citations are
                   deduplicated by book (see dedupedCitations) and shown as
                   book-only pills — the user only cares which books were used
                   as sources, not which chapters within them. -->
              <a
                v-if="c.book_slug"
                :href="`/library/books/${c.book_slug}`"
                target="_blank"
                rel="noopener"
                class="text-xs px-2 py-0.5 rounded-full bg-orange-50 text-orange-600 hover:bg-orange-100 transition-colors"
              >
                {{ c.book_title }}
              </a>
              <span
                v-else
                class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500"
              >
                {{ c.book_title }}
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
import { computed, nextTick, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useAdvancedSearch } from '@/composables/useAdvancedSearch'
import { useConfirm } from '@/composables/useConfirm'

const { isOpen, isMinimized, messages, loading, error, minimize, expand, newChat, deleteConversation, ask, loadHistory } = useAdvancedSearch()
const { confirmAction } = useConfirm()

// The `rag` Postgres connection availability is a page-level Inertia prop
// (shared from HandleInertiaRequests); the drawer reads it directly rather
// than taking it as a component prop so it stays a true "mount once globally"
// component in AdminLayout with no per-page wiring required.
const page = usePage()
const ragAvailable = computed(() => page.props.ragAvailable ?? true)

const question = ref('')
const messagesEl = ref(null)

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  })
}

watch(() => messages.value.length, scrollToBottom)

// Deduplicates a message's citations by book_slug (keeping the first
// occurrence of each unique book) so the same book doesn't appear once per
// cited chapter -- the user only wants to know which books were sourced.
const dedupedCitations = (citations) => {
  const seen = new Set()
  return (citations || []).filter((c) => {
    if (!c.book_slug || seen.has(c.book_slug)) return false
    seen.add(c.book_slug)
    return true
  })
}

// Called from the floating trigger button: expands straight into the full
// panel and defensively loads history (loadHistory() has its own
// historyLoaded guard, so this only actually fetches the first time the
// drawer is opened this session).
const openDrawer = () => {
  expand()
  loadHistory()
}

const confirmDelete = async () => {
  const ok = await confirmAction({
    title: 'Delete conversation',
    message: 'This clears your current conversation. It cannot be undone.',
    danger: true,
    confirmLabel: 'Delete',
  })
  if (!ok) return
  deleteConversation()
}

const submit = async () => {
  const q = question.value
  question.value = ''
  await ask(q)
  scrollToBottom()
}
</script>

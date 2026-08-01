<!-- resources/js/Pages/Admin/Library/Chat/Index.vue -->
<template>
  <Head title="Library Chat" />
  <AdminLayout>
    <div class="flex h-[calc(100vh-4rem)]">
      <aside class="w-64 flex-shrink-0 border-r border-slate-200 overflow-y-auto">
        <div class="p-4">
          <Link href="/admin/library/chat" class="block w-full text-center px-3 py-2 rounded-lg bg-slate-900 text-white text-sm hover:bg-slate-700 transition-colors">
            + New Chat
          </Link>
        </div>
        <nav class="px-2 space-y-1">
          <Link
            v-for="t in threads"
            :key="t.id"
            :href="`/admin/library/chat/${t.id}`"
            class="block px-3 py-2 rounded-lg text-sm truncate"
            :class="activeThread?.id === t.id ? 'bg-slate-100 text-slate-900 font-medium' : 'text-slate-600 hover:bg-slate-50'"
          >
            {{ t.title || 'Untitled thread' }}
          </Link>
          <p v-if="threads.length === 0" class="px-3 py-2 text-sm text-slate-400">No threads yet.</p>
        </nav>
      </aside>

      <section class="flex-1 flex flex-col">
        <div ref="messagesEl" role="log" aria-live="polite" class="flex-1 overflow-y-auto p-6 space-y-4">
          <div v-if="!activeThread" class="text-sm text-slate-400 text-center py-20">
            Ask a question about your library to start a new thread.
          </div>
          <template v-else>
            <div v-for="m in activeThread.messages" :key="m.id" class="max-w-2xl" :class="m.role === 'user' ? 'ml-auto' : ''">
              <div
                class="px-4 py-2.5 rounded-2xl text-sm"
                :class="m.role === 'user' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-800'"
              >
                {{ m.content }}
              </div>
              <div v-if="m.citations?.length" class="mt-1.5 flex flex-wrap gap-1.5">
                <template v-for="(c, i) in m.citations" :key="i">
                  <a
                    v-if="c.book_slug"
                    :href="`/library/books/${c.book_slug}/chapters/${c.chapter_sort_order ?? 0}`"
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
          </template>
        </div>

        <form @submit.prevent="submit" class="border-t border-slate-200 p-4">
          <div class="flex gap-3">
            <label for="chat-question" class="sr-only">Ask about your library</label>
            <input
              id="chat-question"
              v-model="form.question"
              type="text"
              placeholder="Ask about your library…"
              class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-slate-900"
              :disabled="form.processing"
            />
            <button
              type="submit"
              :disabled="form.processing || !form.question.trim()"
              class="px-5 py-2.5 bg-slate-900 text-white rounded-xl text-sm font-medium hover:bg-slate-700 disabled:opacity-50 transition-colors"
            >
              {{ form.processing ? 'Asking…' : 'Ask' }}
            </button>
          </div>
          <p v-if="form.errors.question" class="mt-2 text-sm text-red-600">{{ form.errors.question }}</p>
        </form>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { nextTick, ref, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  threads: { type: Array, default: () => [] },
  activeThread: { type: Object, default: null },
})

const form = useForm({ question: '', thread_id: props.activeThread?.id ?? null })
const messagesEl = ref(null)

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  })
}

watch(() => props.activeThread?.messages?.length, scrollToBottom, { immediate: true })

const submit = () => {
  form.thread_id = props.activeThread?.id ?? null
  form.post('/admin/library/chat', {
    preserveScroll: true,
    onSuccess: () => { form.question = ''; scrollToBottom() },
  })
}
</script>

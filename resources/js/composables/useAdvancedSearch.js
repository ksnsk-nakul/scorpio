import { reactive, toRefs } from 'vue'
import axios from 'axios'

// Module-singleton reactive state (same pattern as useToast.js) — every caller
// across the app shares one drawer instance, rendered once by
// <AdvancedSearchDrawer /> mounted in AdminLayout, so any admin page can open/
// ask into the same drawer without mounting its own. Messages are kept purely
// client-side for the browser session — no thread history is ever fetched from
// the server, so a page refresh starts a fresh conversation.
const state = reactive({
  isOpen: false,
  isMinimized: false,
  messages: [],
  threadId: null,
  loading: false,
  error: null,
})

function open({ minimized = false } = {}) {
  state.isOpen = true
  state.isMinimized = minimized
}

function close() {
  state.isOpen = false
  state.isMinimized = false
}

function minimize() {
  state.isMinimized = true
}

function expand() {
  state.isMinimized = false
}

async function ask(question) {
  const trimmed = (question ?? '').trim()
  if (!trimmed || state.loading) return

  state.messages.push({ role: 'user', content: trimmed })
  state.loading = true
  state.error = null

  try {
    const { data } = await axios.post('/admin/library/chat', {
      question: trimmed,
      thread_id: state.threadId,
    })
    state.threadId = data.thread_id
    state.messages.push({ role: 'assistant', content: data.answer, citations: data.citations ?? [] })
  } catch (err) {
    state.error = err.response?.data?.message ?? 'Something went wrong asking the library.'
  } finally {
    state.loading = false
  }
}

export function useAdvancedSearch() {
  return {
    ...toRefs(state),
    open,
    close,
    minimize,
    expand,
    ask,
  }
}

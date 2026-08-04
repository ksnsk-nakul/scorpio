import { reactive, toRefs } from 'vue'
import axios from 'axios'

// Module-singleton reactive state (same pattern as useToast.js) — every caller
// across the app shares one drawer instance, rendered once by
// <AdvancedSearchDrawer /> mounted in AdminLayout, so any admin page can open/
// ask into the same drawer without mounting its own. The drawer is docked
// (minimized) by default on any admin page — like LinkedIn's messaging widget,
// there's no separate "open" trigger elsewhere on the page. `close()` fully
// dismisses it for the rest of the session (until the next page load).
const state = reactive({
  isOpen: true,
  isMinimized: false,
  messages: [],
  threadId: null,
  loading: false,
  error: null,
})

// Guards loadHistory() so it only ever fetches once per page session, not on
// every AdvancedSearchDrawer re-mount (e.g. across Inertia navigations that
// tear down and remount AdminLayout's children).
let historyLoaded = false

async function loadHistory() {
  if (historyLoaded) return
  historyLoaded = true
  try {
    const { data } = await axios.get('/admin/library/chat/history')
    state.threadId = data.thread_id
    state.messages = data.messages.map((m) => ({ role: m.role, content: m.content, citations: m.citations }))
  } catch {
    // Silently ignore -- an empty/fresh conversation is a fine fallback, this is a
    // convenience hydration, not a critical action worth surfacing an error for.
  }
}

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
    loadHistory,
  }
}

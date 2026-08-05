import { reactive, toRefs } from 'vue'
import axios from 'axios'

// Module-singleton reactive state (same pattern as useToast.js) — every caller
// across the app shares one drawer instance, rendered once by
// <AdvancedSearchDrawer /> mounted in AdminLayout, so any admin page can open/
// ask into the same drawer without mounting its own. The drawer is closed by
// default; a small floating trigger button (rendered by AdvancedSearchDrawer
// itself) is what the user clicks to open it. `deleteConversation()` (wired
// to the drawer's Close/X button) both hides the drawer and deletes the
// underlying thread server-side.
const state = reactive({
  isOpen: false,
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
  state.isOpen = true
  state.isMinimized = false
}

// Starts a fresh conversation without deleting the previous one server-side —
// clears local state only, so the next `ask()` creates a brand-new thread
// (per store()'s `thread_id: null` handling). The old thread stays intact and
// will resurface next time loadHistory() runs (e.g. next page load).
function newChat() {
  state.messages = []
  state.threadId = null
}

// Actually deletes the current conversation (distinct from newChat(), which
// just stops looking at it, and from minimize(), which just collapses the
// panel). Clears client-side state and closes the drawer immediately, then
// best-effort deletes the thread server-side.
async function deleteConversation() {
  const idToDelete = state.threadId
  state.messages = []
  state.threadId = null
  state.isOpen = false
  if (idToDelete) {
    try {
      await axios.delete(`/admin/library/chat/${idToDelete}`)
    } catch {
      // Already cleared client-side regardless; a failed server-side delete just
      // means loadHistory() would resurrect it next time they open the drawer,
      // which is an acceptable degradation, not worth surfacing an error for.
    }
  }
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
    newChat,
    deleteConversation,
    ask,
    loadHistory,
  }
}

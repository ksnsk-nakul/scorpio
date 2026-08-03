import { reactive } from 'vue'

// Module-singleton reactive state (same pattern as useReaderTheme.js) — every
// caller across the app shares one toast queue, rendered once by <ToastContainer />
// mounted in AdminLayout, so any page can push a toast without its own container.
const state = reactive({ toasts: [] })
let nextId = 1

function push(type, message, timeout = 5000) {
  const id = nextId++
  state.toasts.push({ id, type, message })
  if (timeout) {
    setTimeout(() => dismiss(id), timeout)
  }
  return id
}

function dismiss(id) {
  state.toasts = state.toasts.filter((t) => t.id !== id)
}

export function useToast() {
  return {
    toasts: state.toasts,
    success: (message) => push('success', message),
    error: (message) => push('error', message),
    warning: (message) => push('warning', message),
    dismiss,
  }
}

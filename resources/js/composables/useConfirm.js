import { reactive } from 'vue'

// Module-singleton (same pattern as useToast.js) — one <ConfirmModal /> mounted
// once in AdminLayout serves every page's confirmation needs via a Promise, so
// callers can `if (!await confirmAction({...})) return` instead of native confirm()/
// prompt(), which can't be styled and blocks the whole tab while open.
const state = reactive({
  open: false,
  title: '',
  message: '',
  danger: false,
  requireText: null,
  confirmLabel: 'Confirm',
  resolve: null,
})

function confirmAction({ title, message, danger = false, requireText = null, confirmLabel = 'Confirm' }) {
  state.open = true
  state.title = title
  state.message = message
  state.danger = danger
  state.requireText = requireText
  state.confirmLabel = confirmLabel

  return new Promise((resolve) => {
    state.resolve = resolve
  })
}

function respond(value) {
  state.open = false
  const resolve = state.resolve
  state.resolve = null
  resolve?.(value)
}

export function useConfirm() {
  return { state, confirmAction, respond }
}

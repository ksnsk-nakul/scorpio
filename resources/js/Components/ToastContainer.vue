<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-[100] w-80 space-y-2" aria-live="polite">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          class="rounded-lg shadow-lg px-4 py-3 text-sm flex items-start justify-between gap-3"
          :class="styles[t.type]"
          role="status"
        >
          <div class="flex items-start gap-2">
            <span class="flex-shrink-0">{{ icons[t.type] }}</span>
            <span>{{ t.message }}</span>
          </div>
          <button @click="dismiss(t.id)" aria-label="Dismiss" class="opacity-70 hover:opacity-100 flex-shrink-0 leading-none">✕</button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const styles = {
  success: 'bg-green-600 text-white',
  error: 'bg-red-600 text-white',
  warning: 'bg-amber-500 text-white',
}
const icons = { success: '✓', error: '✕', warning: '⚠' }
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.2s ease; }
.toast-enter-from { opacity: 0; transform: translateY(-8px); }
.toast-leave-to { opacity: 0; transform: translateX(24px); }
.toast-move { transition: transform 0.2s ease; }
</style>

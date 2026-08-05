<template>
  <Teleport to="body">
    <div v-if="state.open" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/40 px-4" @keydown.esc="cancel">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-2">{{ state.title }}</h2>
        <p class="text-sm text-slate-600 mb-4">{{ state.message }}</p>

        <div v-if="state.requireText" class="mb-4">
          <label class="block text-xs text-slate-500 mb-1">
            Type <code class="font-semibold">{{ state.requireText }}</code> to confirm
          </label>
          <input v-model="typedText" type="text" autofocus
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400" />
        </div>

        <div class="flex justify-end gap-2">
          <button @click="cancel" class="px-3 py-1.5 text-sm rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
            Cancel
          </button>
          <button
            @click="confirm"
            :disabled="state.requireText && typedText !== state.requireText"
            class="px-3 py-1.5 text-sm rounded-lg text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            :class="state.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
          >
            {{ state.confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const { state, respond } = useConfirm()
const typedText = ref('')

watch(() => state.open, (open) => {
  if (open) typedText.value = ''
})

const confirm = () => respond(true)
const cancel = () => respond(false)
</script>

<template>
  <Teleport to="body">
    <div v-if="modal && !dismissed" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 relative">
        <button @click="dismiss" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-xl leading-none">×</button>
        <h2 class="text-lg font-bold text-slate-900 mb-2">{{ modal.title }}</h2>
        <p class="text-slate-600 text-sm leading-relaxed mb-4">{{ modal.body }}</p>
        <div class="flex justify-end gap-2">
          <button @click="dismiss" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Dismiss</button>
          <a v-if="modal.cta_label && modal.cta_url" :href="modal.cta_url" target="_blank" rel="noopener"
            class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
            {{ modal.cta_label }}
          </a>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  announcements: { type: Array, default: () => [] },
})

const dismissed = ref(false)
const modal = computed(() => props.announcements.find(a => a.display === 'modal'))

function dismiss() {
  dismissed.value = true
}
</script>

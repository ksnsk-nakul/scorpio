<template>
  <div v-if="visible && banner" :class="bannerClass" class="relative px-4 py-3 text-sm flex items-start gap-3">
    <span class="flex-1">
      <strong v-if="banner.title" class="font-semibold">{{ banner.title }}:</strong>
      {{ banner.body }}
      <a v-if="banner.cta_label && banner.cta_url" :href="banner.cta_url" target="_blank" rel="noopener"
        class="ml-2 underline font-medium hover:opacity-80">{{ banner.cta_label }}</a>
    </span>
    <button @click="dismiss" class="flex-shrink-0 opacity-60 hover:opacity-100 text-lg leading-none">×</button>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  announcements: { type: Array, default: () => [] },
})

const dismissed = ref(new Set())
const banner = computed(() => props.announcements.find(a => a.display === 'banner' && !dismissed.value.has(a.id)))
const visible = computed(() => !!banner.value)

const typeColors = {
  info:    'bg-blue-50 text-blue-800 border-b border-blue-200',
  warning: 'bg-yellow-50 text-yellow-800 border-b border-yellow-200',
  success: 'bg-green-50 text-green-800 border-b border-green-200',
  ad:      'bg-orange-50 text-orange-800 border-b border-orange-200',
}
const bannerClass = computed(() => typeColors[banner.value?.type] ?? typeColors.info)

function dismiss() {
  if (banner.value) dismissed.value.add(banner.value.id)
}
</script>

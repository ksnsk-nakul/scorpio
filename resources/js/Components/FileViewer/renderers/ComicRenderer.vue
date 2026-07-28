<template>
  <div class="relative w-full h-full min-h-[220px] bg-slate-900 flex items-center justify-center overflow-hidden">
    <img v-if="pages.length" :src="pages[page - 1]" class="max-h-full max-w-full object-contain" />
    <p v-else class="text-slate-400 text-sm">No pages found for this archive.</p>

    <button
      v-if="page > 1"
      data-testid="prev-page"
      class="absolute left-2 top-1/2 -translate-y-1/2 text-white text-2xl"
      @click="page--"
    >‹</button>
    <button
      v-if="page < pages.length"
      data-testid="next-page"
      class="absolute right-2 top-1/2 -translate-y-1/2 text-white text-2xl"
      @click="page++"
    >›</button>

    <div
      v-if="pages.length"
      data-testid="page-indicator"
      class="absolute bottom-2 left-1/2 -translate-x-1/2 text-white text-xs bg-black/40 rounded px-2 py-0.5"
    >{{ page }} / {{ pages.length }}</div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({ media: { type: Object, required: true } })
const pages = computed(() => props.media.comic_page_urls ?? [])
const page = ref(1)
</script>

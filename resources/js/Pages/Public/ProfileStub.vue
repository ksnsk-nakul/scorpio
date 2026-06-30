<template>
  <div class="min-h-screen bg-white text-slate-900 font-sans flex items-center justify-center px-6">
    <div class="text-center max-w-sm">
      <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-5 text-xl font-semibold text-slate-400">
        {{ initials }}
      </div>
      <h1 class="text-xl font-bold text-slate-900 mb-2">{{ owner.name }}</h1>
      <p class="text-sm text-slate-400 mb-1">@{{ owner.username }}</p>
      <p class="text-sm text-slate-400 mt-4">This page hasn't been published yet.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  owner: { type: Object, default: () => ({}) },
})

const initials = computed(() =>
  (props.owner.name ?? '?')
    .split(' ')
    .map(w => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
)
</script>

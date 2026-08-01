<template>
  <!-- Not AnimeJsNav — it would render fine (its settings/sections props
       default), but with a generic "Portfolio" site name since the library
       controllers don't pass real `settings`. This minimal "← {backLabel} |
       {title}" bar avoids that. -->
  <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
      <a :href="backHref" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← {{ backLabel }}</a>
      <span class="text-sm font-semibold text-slate-800" :class="{ 'truncate max-w-xs': titleTruncate }">{{ title }}</span>
    </div>
  </nav>
</template>

<script setup>
defineProps({
  backHref: { type: String, required: true },
  backLabel: { type: String, required: true },
  title: { type: String, required: true },
  titleTruncate: { type: Boolean, default: false },
})
</script>

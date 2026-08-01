<template>
  <footer class="border-t border-slate-100 py-10 text-center">
    <p class="text-sm font-semibold text-slate-600 mb-3">{{ settings?.site_name || 'Portfolio' }}</p>
    <p class="text-xs text-slate-300">© {{ year }} · Built with Laravel &amp; Vue</p>
    <div class="flex items-center justify-center gap-4 mt-3">
      <a href="/terms" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Terms</a>
      <span class="text-slate-200">·</span>
      <a href="/privacy" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Privacy</a>
      <span class="text-slate-200">·</span>
      <a href="/refund" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Refund Policy</a>
    </div>
    <a v-if="settings?.show_donate_banner" href="/donate"
      class="inline-flex items-center gap-1.5 text-xs text-pink-400 hover:text-pink-600 transition-colors mt-3">
      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
      </svg>
      Support my work
    </a>
  </footer>
</template>

<script setup>
defineProps({
  settings: { type: Object, default: () => ({}) },
})

const year = new Date().getFullYear()
</script>

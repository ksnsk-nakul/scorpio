<template>
  <slot v-if="allowed" />
  <div v-else class="relative">
    <div class="opacity-30 pointer-events-none select-none" aria-hidden="true">
      <slot />
    </div>
    <div class="absolute inset-0 flex items-center justify-center bg-white/60 backdrop-blur-sm rounded-xl">
      <div class="text-center px-6 py-4 max-w-xs">
        <p class="text-sm font-semibold text-slate-800 mb-1">{{ title }}</p>
        <p class="text-xs text-slate-500 mb-3">{{ message }}</p>
        <Link href="/admin/billing" class="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold rounded-lg transition-colors">
          Upgrade Plan
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  allowed:  { type: Boolean, required: true },
  title:    { type: String, default: 'Feature not available' },
  message:  { type: String, default: 'Upgrade your plan to unlock this feature.' },
})
</script>

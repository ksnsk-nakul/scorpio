<template>
  <nav v-if="links.length > 3" class="flex items-center gap-1 justify-center mt-8">
    <Link
      v-for="(link, i) in links"
      :key="i"
      :href="link.url ?? '#'"
      :class="[
        'px-3 py-1.5 text-sm rounded-lg transition-colors',
        link.active ? 'bg-orange-500 text-white' : 'text-slate-600 hover:bg-slate-100',
        !link.url ? 'pointer-events-none opacity-40' : '',
      ]"
      v-html="link.label"
    />
  </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  links: { type: Array, required: true },
})
</script>

<template>
  <Link :href="href" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">
    ← {{ label }}
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  href: { type: String, required: true },
  label: { type: String, required: true },
})
</script>

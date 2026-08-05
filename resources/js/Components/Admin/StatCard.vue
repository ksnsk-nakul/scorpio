<template>
  <component :is="href ? Link : 'div'" :href="href"
    class="bg-white border border-slate-200 rounded-xl p-5 block"
    :class="href ? 'hover:border-blue-300 hover:shadow-sm transition-colors' : ''">
    <div class="flex items-center justify-between">
      <p class="text-xs text-slate-400 uppercase tracking-wide">{{ label }}</p>
      <span v-if="href" class="text-xs text-blue-500">See all →</span>
    </div>
    <p class="text-3xl font-bold mt-1" :class="colorClass">{{ value }}</p>
  </component>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  label: String,
  value: Number,
  color: { type: String, default: 'slate' },
  href: { type: String, default: null },
})

const colorClass = ({
  slate: 'text-slate-800',
  amber: 'text-amber-500',
  blue:  'text-blue-600',
  red:   'text-red-500',
}[props.color]) ?? 'text-slate-800'
</script>

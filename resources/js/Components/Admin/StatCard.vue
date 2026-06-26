<template>
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <p class="text-xs text-slate-400 uppercase tracking-wide">{{ label }}</p>
    <p class="text-3xl font-bold mt-1" :class="colorClass">{{ value }}</p>
  </div>
</template>

<script setup>
const props = defineProps({
  label: String,
  value: Number,
  color: { type: String, default: 'slate' },
})

const colorClass = {
  slate: 'text-slate-800',
  amber: 'text-amber-500',
  blue:  'text-blue-600',
}[props.color]
</script>

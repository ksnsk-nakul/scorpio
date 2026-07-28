<template>
  <img :src="media.url" :alt="media.filename" class="w-full h-full object-contain bg-slate-900" />
</template>

<script setup>
defineProps({ media: { type: Object, required: true } })
</script>

<template>
  <div class="w-full h-full min-h-[100px] flex items-center justify-center bg-slate-100 p-4">
    <audio :src="media.url" controls class="w-full" />
  </div>
</template>

<script setup>
defineProps({ media: { type: Object, required: true } })
</script>

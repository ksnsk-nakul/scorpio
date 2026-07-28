<template>
  <video :src="media.url" controls class="w-full h-full bg-black" />
</template>

<script setup>
defineProps({ media: { type: Object, required: true } })
</script>

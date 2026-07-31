<template>
  <div class="text-slate-400 py-12 text-center">
    {{ message }}
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: 'Nothing here yet.' },
})
</script>

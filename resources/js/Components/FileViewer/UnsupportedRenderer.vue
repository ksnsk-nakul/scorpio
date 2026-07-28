<template>
  <div class="w-full h-full min-h-[220px] flex flex-col items-center justify-center gap-2 bg-slate-100 text-slate-500 text-sm p-4 text-center">
    <p>Preview not available{{ reason ? ` — ${reason}` : '.' }}</p>
    <a v-if="downloadUrl" :href="downloadUrl" class="text-blue-600 hover:underline" download>Download to view</a>
  </div>
</template>

<script setup>
defineProps({ reason: { type: String, default: null }, downloadUrl: { type: String, default: null } })
</script>

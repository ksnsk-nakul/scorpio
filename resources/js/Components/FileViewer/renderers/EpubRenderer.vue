<template>
  <div ref="container" class="w-full h-full min-h-[320px] bg-white"></div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import ePub from 'epubjs'

const props = defineProps({ media: { type: Object, required: true } })
const container = ref(null)

onMounted(() => {
  const book = ePub(props.media.url)
  const rendition = book.renderTo(container.value, { width: '100%', height: '100%' })
  rendition.display()
})
</script>

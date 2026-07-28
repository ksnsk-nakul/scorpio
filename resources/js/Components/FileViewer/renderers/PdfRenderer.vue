<template>
  <div class="relative w-full h-full min-h-[220px] bg-slate-900 flex flex-col items-center overflow-auto">
    <canvas ref="canvas"></canvas>
    <div v-if="loading" class="absolute inset-0 flex items-center justify-center text-slate-300 text-sm">
      Loading…
    </div>
    <div v-if="numPages > 1" class="sticky bottom-0 flex items-center justify-center gap-4 py-1 text-white text-sm bg-black/40 w-full">
      <button :disabled="page <= 1" @click="goTo(page - 1)">‹</button>
      <span>{{ page }} / {{ numPages }}</span>
      <button :disabled="page >= numPages" @click="goTo(page + 1)">›</button>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import * as pdfjsLib from 'pdfjs-dist'
import pdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url'

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker

const props = defineProps({ media: { type: Object, required: true } })

const canvas = ref(null)
const loading = ref(true)
const page = ref(1)
const numPages = ref(0)
let pdfDoc = null

const renderPage = async (num) => {
  const pdfPage = await pdfDoc.getPage(num)
  const viewport = pdfPage.getViewport({ scale: 1.2 })
  canvas.value.width = viewport.width
  canvas.value.height = viewport.height
  await pdfPage.render({ canvasContext: canvas.value.getContext('2d'), viewport }).promise
}

const goTo = async (num) => {
  page.value = num
  await renderPage(num)
}

onMounted(async () => {
  pdfDoc = await pdfjsLib.getDocument(props.media.url).promise
  numPages.value = pdfDoc.numPages
  await renderPage(page.value)
  loading.value = false
})
</script>

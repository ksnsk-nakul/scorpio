<template>
  <div ref="container" class="relative w-full h-full min-h-[220px] bg-slate-900 flex flex-col items-center overflow-auto">
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
import { onBeforeUnmount, onMounted, ref } from 'vue'
import * as pdfjsLib from 'pdfjs-dist'
import pdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import { computeScaleForWidth } from '../pdfScale'

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker

const RESIZE_DEBOUNCE_MS = 150

const props = defineProps({ media: { type: Object, required: true } })

const container = ref(null)
const canvas = ref(null)
const loading = ref(true)
const page = ref(1)
const numPages = ref(0)
let pdfDoc = null
let resizeObserver = null
let resizeTimer = null

const renderPage = async (num) => {
  const pdfPage = await pdfDoc.getPage(num)
  const nativeWidth = pdfPage.getViewport({ scale: 1 }).width
  const scale = computeScaleForWidth(container.value?.clientWidth, nativeWidth)
  const viewport = pdfPage.getViewport({ scale })
  canvas.value.width = viewport.width
  canvas.value.height = viewport.height
  await pdfPage.render({ canvasContext: canvas.value.getContext('2d'), viewport }).promise
}

const goTo = async (num) => {
  page.value = num
  await renderPage(num)
}

function scheduleRerender() {
  clearTimeout(resizeTimer)
  resizeTimer = setTimeout(() => {
    if (pdfDoc) renderPage(page.value)
  }, RESIZE_DEBOUNCE_MS)
}

onMounted(async () => {
  pdfDoc = await pdfjsLib.getDocument(props.media.url).promise
  numPages.value = pdfDoc.numPages
  await renderPage(page.value)
  loading.value = false

  // Re-render at the correct scale when the container's available width
  // changes — e.g. the FileViewer fullscreen toggle or a window resize.
  if (typeof ResizeObserver !== 'undefined' && container.value) {
    resizeObserver = new ResizeObserver(scheduleRerender)
    resizeObserver.observe(container.value)
  }
})

onBeforeUnmount(() => {
  clearTimeout(resizeTimer)
  resizeObserver?.disconnect()
})
</script>

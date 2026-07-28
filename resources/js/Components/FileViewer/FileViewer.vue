<template>
  <div
    ref="root"
    class="relative rounded-lg border border-slate-200 overflow-hidden"
    :class="fullscreen ? 'fixed inset-0 z-50 rounded-none border-0' : 'w-full h-full min-h-[220px]'"
    @mouseenter="showControls = true"
    @mouseleave="showControls = false"
  >
    <ProcessingState v-if="media.status === 'pending' || media.status === 'processing'" />
    <UnsupportedRenderer
      v-else-if="media.status === 'failed'"
      :reason="media.status_reason"
      :download-url="media.url"
    />
    <UnsupportedRenderer v-else-if="renderer === 'unsupported'" :download-url="media.url" />
    <component :is="rendererComponent" v-else :media="media" />

    <div
      class="absolute inset-x-0 top-0 flex items-center justify-between px-3 py-1.5 text-xs text-white transition-opacity duration-150 pointer-events-none"
      :class="showControls ? 'opacity-100' : 'opacity-0'"
      style="background: linear-gradient(to bottom, rgba(0,0,0,.55), transparent)"
    >
      <span class="truncate">{{ media.filename }}</span>
      <button
        data-testid="toggle-fullscreen"
        class="pointer-events-auto"
        @click="toggleFullscreen"
      >{{ fullscreen ? '✕' : '⛶' }}</button>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { resolveRenderer } from './rendererMap'
import ImageRenderer from './renderers/ImageRenderer.vue'
import VideoRenderer from './renderers/VideoRenderer.vue'
import AudioRenderer from './renderers/AudioRenderer.vue'
import PdfRenderer from './renderers/PdfRenderer.vue'
import TextRenderer from './renderers/TextRenderer.vue'
import OfficeRenderer from './renderers/OfficeRenderer.vue'
import ComicRenderer from './renderers/ComicRenderer.vue'
import EpubRenderer from './renderers/EpubRenderer.vue'
import ProcessingState from './ProcessingState.vue'
import UnsupportedRenderer from './UnsupportedRenderer.vue'

const props = defineProps({ media: { type: Object, required: true } })

const root = ref(null)
const fullscreen = ref(false)
const showControls = ref(false)

async function toggleFullscreen() {
  if (fullscreen.value) {
    if (document.fullscreenElement && document.exitFullscreen) {
      try {
        await document.exitFullscreen()
      } catch {
        // ignore — browser may refuse, fall back to CSS-only state
      }
    }
    fullscreen.value = false
    return
  }

  fullscreen.value = true
  if (root.value?.requestFullscreen) {
    try {
      await root.value.requestFullscreen()
    } catch {
      // ignore — not called from a user gesture, unsupported, etc. CSS class still applies.
    }
  }
}

function handleFullscreenChange() {
  if (!document.fullscreenElement) {
    fullscreen.value = false
  }
}

onMounted(() => {
  document.addEventListener('fullscreenchange', handleFullscreenChange)
})

onBeforeUnmount(() => {
  document.removeEventListener('fullscreenchange', handleFullscreenChange)
})

const renderer = computed(() => resolveRenderer(props.media))

const COMPONENT_MAP = {
  image: ImageRenderer,
  video: VideoRenderer,
  audio: AudioRenderer,
  pdf: PdfRenderer,
  text: TextRenderer,
  markdown: TextRenderer,
  csv: TextRenderer,
  office: OfficeRenderer,
  comic: ComicRenderer,
  epub: EpubRenderer,
}

const rendererComponent = computed(() => COMPONENT_MAP[renderer.value])
</script>

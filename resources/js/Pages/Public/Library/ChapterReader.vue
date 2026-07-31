<template>
  <Head>
    <title>{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }} · {{ book.title }}</title>
  </Head>

  <div class="min-h-screen font-sans" :class="themeClass">
    <nav class="sticky top-0 z-40 backdrop-blur-xl border-b border-current/10" :class="themeClass">
      <div class="max-w-3xl mx-auto px-6 h-14 flex items-center justify-between text-sm">
        <a :href="`/library/books/${book.slug}`" class="opacity-70 hover:opacity-100 transition-opacity truncate max-w-[10rem]">
          ← {{ book.title }}
        </a>
        <button @click="drawerOpen = true" class="opacity-70 hover:opacity-100 text-lg leading-none" aria-label="Reading settings">⚙</button>
      </div>
    </nav>

    <main class="max-w-3xl mx-auto px-6 py-10">
      <h1 class="text-xl font-bold mb-6">{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}</h1>
      <div class="markdown-body" :style="fontStyle" v-html="chapter.content"></div>

      <div class="flex items-center justify-between mt-12 pt-6 border-t border-current/10 text-sm">
        <Link v-if="hasPrev" :href="`/library/books/${book.slug}/chapters/${chapter.sort_order - 1}`" class="opacity-70 hover:opacity-100">← Previous</Link>
        <span v-else></span>
        <Link v-if="hasNext" :href="`/library/books/${book.slug}/chapters/${chapter.sort_order + 1}`" class="opacity-70 hover:opacity-100">Next →</Link>
        <span v-else></span>
      </div>
    </main>

    <ReaderSettingsDrawer :open="drawerOpen" @close="drawerOpen = false" />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { useReaderTheme } from '@/composables/useReaderTheme'
import { useReaderMode } from '@/composables/useReaderMode'
import ReaderSettingsDrawer from '@/Components/ReaderSettingsDrawer.vue'

defineProps({
  book: { type: Object, required: true },
  chapter: { type: Object, required: true },
  hasPrev: { type: Boolean, required: true },
  hasNext: { type: Boolean, required: true },
})

const { themeClass, fontStyle, setTheme, increaseFontSize, decreaseFontSize } = useReaderTheme()
const { mode, pxPerFrame, isPlaying, play, pause } = useReaderMode()

const drawerOpen = ref(false)

let rafId = null
const autoscrollTick = () => {
  if (isPlaying.value) {
    window.scrollBy(0, pxPerFrame.value)
  }
  rafId = requestAnimationFrame(autoscrollTick)
}

const pauseOnInteraction = () => {
  if (isPlaying.value) pause()
}

onMounted(() => {
  rafId = requestAnimationFrame(autoscrollTick)
  window.addEventListener('wheel', pauseOnInteraction, { passive: true })
  window.addEventListener('touchstart', pauseOnInteraction, { passive: true })
  window.addEventListener('mousedown', pauseOnInteraction)
  window.addEventListener('keydown', pauseOnInteraction)
})

onUnmounted(() => {
  cancelAnimationFrame(rafId)
  window.removeEventListener('wheel', pauseOnInteraction)
  window.removeEventListener('touchstart', pauseOnInteraction)
  window.removeEventListener('mousedown', pauseOnInteraction)
  window.removeEventListener('keydown', pauseOnInteraction)
})
</script>

<style scoped>
/*
 * Same hand-rolled markdown typography as
 * resources/js/Components/FileViewer/renderers/TextRenderer.vue — Tailwind's
 * preflight strips default heading/list styling, and this app has no
 * @tailwindcss/typography dependency. color: inherit throughout so it keeps
 * working across all four reader themes.
 */
.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3),
.markdown-body :deep(h4),
.markdown-body :deep(h5),
.markdown-body :deep(h6) {
  color: inherit;
  font-weight: 700;
  line-height: 1.25;
  margin-top: 1em;
  margin-bottom: 0.5em;
}

.markdown-body :deep(h1) { font-size: 1.75em; }
.markdown-body :deep(h2) { font-size: 1.5em; }
.markdown-body :deep(h3) { font-size: 1.25em; }
.markdown-body :deep(h4) { font-size: 1.1em; }
.markdown-body :deep(h5) { font-size: 1em; }
.markdown-body :deep(h6) { font-size: 0.875em; }

.markdown-body :deep(p),
.markdown-body :deep(blockquote) {
  margin-top: 0.5em;
  margin-bottom: 0.5em;
}

.markdown-body :deep(ul),
.markdown-body :deep(ol) {
  margin-top: 0.5em;
  margin-bottom: 0.5em;
  padding-left: 1.5em;
}

.markdown-body :deep(ul) { list-style: disc; }
.markdown-body :deep(ol) { list-style: decimal; }
.markdown-body :deep(li) { margin-top: 0.25em; }

.markdown-body :deep(blockquote) {
  border-left: 3px solid currentColor;
  padding-left: 0.75em;
  opacity: 0.85;
}

.markdown-body :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 0.5em;
}
</style>

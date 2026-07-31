<template>
  <Head>
    <title>{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }} · {{ book.title }}</title>
  </Head>

  <div ref="rootEl" class="min-h-screen font-sans outline-none" :class="themeClass" tabindex="0"
    @keydown.left="mode === 'h-page' && goToPage(-1)" @keydown.right="mode === 'h-page' && goToPage(1)"
    @keydown.up="mode === 'v-page' && goToPage(-1)" @keydown.down="mode === 'v-page' && goToPage(1)"
    @touchstart="onTouchStart" @touchend="onTouchEnd">
    <nav class="sticky top-0 z-40 backdrop-blur-xl border-b border-current/10" :class="themeClass">
      <div class="max-w-3xl mx-auto px-6 h-14 flex items-center justify-between text-sm">
        <a :href="`/library/books/${book.slug}`" class="opacity-70 hover:opacity-100 transition-opacity truncate max-w-[10rem]">
          ← {{ book.title }}
        </a>
        <button @click="drawerOpen = true" class="opacity-70 hover:opacity-100 text-lg leading-none" aria-label="Reading settings">⚙</button>
      </div>
    </nav>

    <main v-if="mode === 'scroll' || mode === 'autoscroll'" class="max-w-3xl mx-auto px-6 py-10">
      <h1 class="text-xl font-bold mb-6">{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}</h1>
      <div class="markdown-body" :style="fontStyle" v-html="chapter.content"></div>

      <div class="flex items-center justify-between mt-12 pt-6 border-t border-current/10 text-sm">
        <Link v-if="hasPrev" :href="`/library/books/${book.slug}/chapters/${chapter.sort_order - 1}`" class="opacity-70 hover:opacity-100">← Previous</Link>
        <span v-else></span>
        <Link v-if="hasNext" :href="`/library/books/${book.slug}/chapters/${chapter.sort_order + 1}`" class="opacity-70 hover:opacity-100">Next →</Link>
        <span v-else></span>
      </div>
    </main>

    <main v-else-if="mode === 'h-page'" ref="pagedViewportEl" class="overflow-hidden max-w-3xl mx-auto relative" :style="{ height: 'calc(100vh - 3.5rem)' }">
      <button class="absolute left-0 top-0 h-full w-1/3 z-10" aria-label="Previous page" @click="goToPage(-1)"></button>
      <button class="absolute right-0 top-0 h-full w-1/3 z-10" aria-label="Next page" @click="goToPage(1)"></button>
      <div ref="pagedEl" class="markdown-body px-6 py-10 h-full"
        :style="{ ...fontStyle, columnWidth: pageWidth + 'px', columnGap: 0, columnFill: 'auto', transform: `translateX(-${currentPage * pageWidth}px)`, transition: 'transform 0.25s ease' }">
        <h1 class="text-xl font-bold mb-6">{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}</h1>
        <div v-html="chapter.content"></div>
      </div>
    </main>

    <main v-else-if="mode === 'v-page'" ref="pagedViewportEl" class="overflow-hidden max-w-3xl mx-auto relative" :style="{ height: 'calc(100vh - 3.5rem)' }">
      <button class="absolute top-0 left-0 w-full h-1/3 z-10" aria-label="Previous page" @click="goToPage(-1)"></button>
      <button class="absolute bottom-0 left-0 w-full h-1/3 z-10" aria-label="Next page" @click="goToPage(1)"></button>
      <div ref="pagedEl" class="markdown-body px-6 py-10" :style="{ ...fontStyle, transform: `translateY(-${currentPage * pageHeight}px)`, transition: 'transform 0.25s ease' }">
        <h1 class="text-xl font-bold mb-6">{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}</h1>
        <div v-html="chapter.content"></div>
      </div>
    </main>

    <ReaderSettingsDrawer :open="drawerOpen" @close="drawerOpen = false" />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useReaderTheme } from '@/composables/useReaderTheme'
import { useReaderMode } from '@/composables/useReaderMode'
import ReaderSettingsDrawer from '@/Components/ReaderSettingsDrawer.vue'

const props = defineProps({
  book: { type: Object, required: true },
  chapter: { type: Object, required: true },
  hasPrev: { type: Boolean, required: true },
  hasNext: { type: Boolean, required: true },
})

const AUTOSCROLL_RESUME_KEY = 'library-autoscroll-resume'

const { themeClass, fontStyle, setTheme, increaseFontSize, decreaseFontSize } = useReaderTheme()
const { mode, pxPerFrame, isPlaying, play, pause } = useReaderMode()

const mountedAt = Date.now()
const MIN_AUTOSCROLL_DWELL_MS = 3000

const drawerOpen = ref(false)
const rootEl = ref(null)

const pagedViewportEl = ref(null)
const pagedEl = ref(null)
const currentPage = ref(0)
const pageWidth = ref(0)
const pageHeight = ref(0)
const totalPages = ref(1)

let rafId = null
let navigating = false
const checkAutoscrollBoundary = () => {
  if (mode.value !== 'autoscroll' || !isPlaying.value || navigating) return
  if (Date.now() - mountedAt < MIN_AUTOSCROLL_DWELL_MS) return
  const atBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4
  if (!atBottom) return
  if (props.hasNext) {
    navigating = true
    sessionStorage.setItem(AUTOSCROLL_RESUME_KEY, '1')
    router.visit(`/library/books/${props.book.slug}/chapters/${props.chapter.sort_order + 1}`, {
      onFinish: () => { navigating = false },
    })
  } else {
    pause()
  }
}

const autoscrollTick = () => {
  if (isPlaying.value) {
    window.scrollBy(0, pxPerFrame.value)
    checkAutoscrollBoundary()
  }
  rafId = requestAnimationFrame(autoscrollTick)
}

const pauseOnInteraction = () => {
  if (isPlaying.value) pause()
}

const measurePages = async () => {
  if (mode.value !== 'h-page' && mode.value !== 'v-page') return
  await nextTick()
  if (!pagedViewportEl.value || !pagedEl.value) return
  pageWidth.value = pagedViewportEl.value.clientWidth
  pageHeight.value = pagedViewportEl.value.clientHeight
  const size = mode.value === 'h-page' ? pagedEl.value.scrollWidth : pagedEl.value.scrollHeight
  const pageSize = mode.value === 'h-page' ? pageWidth.value : pageHeight.value
  totalPages.value = Math.max(1, Math.ceil(size / pageSize))
  currentPage.value = 0
}

const goToPage = (delta) => {
  if (navigating) return
  if (mode.value !== 'h-page' && mode.value !== 'v-page') return
  const next = currentPage.value + delta
  if (next < 0) {
    if (props.hasPrev) {
      navigating = true
      router.visit(`/library/books/${props.book.slug}/chapters/${props.chapter.sort_order - 1}`, {
        onFinish: () => { navigating = false },
      })
    }
    return
  }
  if (next >= totalPages.value) {
    if (props.hasNext) {
      navigating = true
      router.visit(`/library/books/${props.book.slug}/chapters/${props.chapter.sort_order + 1}`, {
        onFinish: () => { navigating = false },
      })
    }
    return
  }
  currentPage.value = next
}

watch(mode, measurePages)
watch(() => fontStyle.value.fontSize, measurePages)

let touchStartX = 0
let touchStartY = 0

const onTouchStart = (e) => {
  touchStartX = e.changedTouches[0].clientX
  touchStartY = e.changedTouches[0].clientY
}

const onTouchEnd = (e) => {
  const dx = e.changedTouches[0].clientX - touchStartX
  const dy = e.changedTouches[0].clientY - touchStartY
  const SWIPE_THRESHOLD = 50
  if (mode.value === 'h-page' && Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(dy)) {
    goToPage(dx < 0 ? 1 : -1)
  } else if (mode.value === 'v-page' && Math.abs(dy) > SWIPE_THRESHOLD && Math.abs(dy) > Math.abs(dx)) {
    goToPage(dy < 0 ? 1 : -1)
  }
}

onMounted(() => {
  rafId = requestAnimationFrame(autoscrollTick)
  window.addEventListener('wheel', pauseOnInteraction, { passive: true })
  window.addEventListener('touchstart', pauseOnInteraction, { passive: true })
  window.addEventListener('mousedown', pauseOnInteraction)
  window.addEventListener('keydown', pauseOnInteraction)

  if (sessionStorage.getItem(AUTOSCROLL_RESUME_KEY)) {
    sessionStorage.removeItem(AUTOSCROLL_RESUME_KEY)
    if (mode.value === 'autoscroll') play()
  }

  measurePages()
  window.addEventListener('resize', measurePages)
  rootEl.value?.focus()
})

onUnmounted(() => {
  cancelAnimationFrame(rafId)
  window.removeEventListener('wheel', pauseOnInteraction)
  window.removeEventListener('touchstart', pauseOnInteraction)
  window.removeEventListener('mousedown', pauseOnInteraction)
  window.removeEventListener('keydown', pauseOnInteraction)
  window.removeEventListener('resize', measurePages)
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

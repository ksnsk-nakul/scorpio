<template>
  <Head>
    <title>{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }} · {{ book.title }}</title>
  </Head>

  <div ref="rootEl" class="min-h-dvh pb-[env(safe-area-inset-bottom)] font-sans outline-none" :class="themeClass" tabindex="-1"
    @keydown.left="onKeyLeft" @keydown.right="onKeyRight"
    @keydown.up="onKeyUp" @keydown.down="onKeyDown"
    @keydown.space.prevent="onKeySpace" @keydown.esc="drawerOpen = false"
    @touchstart="onTouchStart" @touchend="onTouchEnd">
    <nav class="sticky top-0 z-40 backdrop-blur-xl border-b border-current/10 pt-[env(safe-area-inset-top)]" :class="themeClass">
      <div class="max-w-3xl mx-auto px-6 h-14 flex items-center justify-between text-sm">
        <a :href="`/library/books/${book.slug}`" class="opacity-70 hover:opacity-100 transition-opacity truncate max-w-[10rem]">
          ← {{ book.title }}
        </a>
        <button @click="drawerOpen = true" class="opacity-70 hover:opacity-100 text-lg leading-none" aria-label="Reading settings">⚙</button>
      </div>
    </nav>

    <main v-if="mode === 'scroll' || mode === 'autoscroll'" class="max-w-3xl mx-auto px-6 py-10 pb-[env(safe-area-inset-bottom)]">
      <h1 class="text-xl font-bold mb-6">{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}</h1>
      <!-- Scroll mode has no fixed "page" height, but a raw embedded image (a
           cover image as the first spine item is common) still shouldn't be
           allowed to render at its full natural size and dominate the whole
           screen — bound it to roughly one viewport height, same containment
           principle as the paged modes, just against the viewport instead of
           a measured page height. -->
      <div class="markdown-body" :style="{ ...fontStyle, '--page-height': 'calc(100dvh - 3.5rem)' }" v-html="chapter.content"></div>

      <div class="flex items-center justify-between mt-12 pt-6 border-t border-current/10 text-sm">
        <Link v-if="hasPrev" :href="`/library/books/${book.slug}/chapters/${chapter.sort_order - 1}`" class="opacity-70 hover:opacity-100">← Previous</Link>
        <span v-else></span>
        <Link v-if="hasNext" :href="`/library/books/${book.slug}/chapters/${chapter.sort_order + 1}`" class="opacity-70 hover:opacity-100">Next →</Link>
        <span v-else></span>
      </div>
    </main>

    <!-- Three layers, deliberately: overflow-hidden clips at an element's
         PADDING-box edge, not its content-box edge. If padding lived on
         either the clipping element or the inner content element, the
         clip boundary would always be wider than where one page's content
         actually ends, leaving the next page's leading edge visible in that
         gap. So padding/centering lives on an outer layer that is neither
         clipped nor transformed; the clipping layer and the content layer
         both have zero padding, so the clip boundary exactly matches the
         page's rendered width/height with no slack.

         Pagination itself is no longer a CSS side-effect: `pages` is a
         pre-computed array of per-page HTML strings (see paginateContent()),
         each built from whole block-level nodes that were measured to fit
         within one page's height. The clip layer's overflow-hidden below is
         now just a safety net for the rare single-block-taller-than-a-page
         case, not load-bearing for pagination itself. -->
    <main v-else-if="mode === 'h-page'" class="max-w-3xl mx-auto relative px-6 py-10 pb-[env(safe-area-inset-bottom)]" :style="{ height: 'calc(100dvh - 3.5rem - env(safe-area-inset-bottom))' }">
      <div ref="pagedViewportEl" class="overflow-hidden relative h-full w-full">
        <button class="absolute left-0 top-0 h-full w-1/3 z-10" aria-label="Previous page" @click="goToPage(-1)"></button>
        <button class="absolute right-0 top-0 h-full w-1/3 z-10" aria-label="Next page" @click="goToPage(1)"></button>
        <div :key="currentPage" data-page class="markdown-body h-full page-fade"
          :style="{ ...fontStyle, '--page-height': pageHeight + 'px' }"
          v-html="pages[currentPage] ?? ''"></div>
      </div>
    </main>

    <main v-else-if="mode === 'v-page'" class="max-w-3xl mx-auto relative px-6 py-10 pb-[env(safe-area-inset-bottom)]" :style="{ height: 'calc(100dvh - 3.5rem - env(safe-area-inset-bottom))' }">
      <div ref="pagedViewportEl" class="overflow-hidden relative h-full w-full">
        <button class="absolute top-0 left-0 w-full h-1/3 z-10" aria-label="Previous page" @click="goToPage(-1)"></button>
        <button class="absolute bottom-0 left-0 w-full h-1/3 z-10" aria-label="Next page" @click="goToPage(1)"></button>
        <div :key="currentPage" data-page class="markdown-body page-fade"
          :style="{ ...fontStyle, '--page-height': pageHeight + 'px' }"
          v-html="pages[currentPage] ?? ''"></div>
      </div>
    </main>

    <!-- Off-screen measuring container: mounted inside the component's real
         DOM tree (not display:none, which would report zero height) so the
         scoped .markdown-body typography rules below and the live fontStyle
         apply identically to how a page actually renders. paginateContent()
         appends the chapter's block nodes here, reads each one's rendered
         height via getBoundingClientRect(), then clears it. -->
    <div v-if="mode === 'h-page' || mode === 'v-page'" ref="measureEl" class="markdown-body"
      :style="{ ...fontStyle, position: 'absolute', visibility: 'hidden', left: '-9999px', top: 0, width: pageWidth + 'px' }"></div>

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
const measureEl = ref(null)
const currentPage = ref(0)
const pageWidth = ref(0)
const pageHeight = ref(0)
const pages = ref([])
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

// Deterministic, controllable pagination: parse the chapter's HTML into
// block-level nodes, measure each one's real rendered height at the page
// width, then greedily pack whole blocks onto pages so a page never breaks
// mid-element the way CSS column-width used to (column breaks are an
// emergent browser layout decision with no control over avoiding awkward
// splits around headings/images).
const paginateContent = () => {
  if (!pagedViewportEl.value || !measureEl.value) return

  // pagedViewportEl (the overflow-hidden clipping layer) is deliberately
  // padding-free — see the template comment above the h-page/v-page markup
  // — so clientWidth/clientHeight here already equal exactly the space one
  // page has to render into, with no padding to account for.
  pageWidth.value = pagedViewportEl.value.clientWidth
  pageHeight.value = pagedViewportEl.value.clientHeight

  const doc = new DOMParser().parseFromString(props.chapter.content ?? '', 'text/html')
  // EPUBs (especially Kobo-format sources) commonly wrap a chapter's real
  // paragraphs in one or more single-child container divs, e.g.
  // <div id="book-columns"><div id="book-inner"><p>...</p><p>...</p>...
  // </div></div>. Taking doc.body.children directly in that case yields ONE
  // giant unsplittable "block" (the outer wrapper) instead of the ~40 actual
  // paragraphs inside it, which defeats pagination entirely — everything
  // ends up crammed onto a single oversized page. Descend through any lone
  // wrapping element (not just <div>) to find the real sibling blocks.
  let unwrapped = doc.body
  while (unwrapped.children.length === 1 && unwrapped.children[0].children.length > 0) {
    unwrapped = unwrapped.children[0]
  }
  const blocks = Array.from(unwrapped.children)

  const titleEl = document.createElement('h1')
  titleEl.className = 'text-xl font-bold mb-6'
  titleEl.textContent = props.chapter.title ?? `Chapter ${props.chapter.sort_order + 1}`
  blocks.unshift(titleEl)

  // Render all blocks into the off-screen measuring container at once (one
  // reflow) rather than reflowing per-node, then read each one's height.
  const container = measureEl.value
  container.replaceChildren(...blocks)
  container.style.width = pageWidth.value + 'px'

  const heights = blocks.map((block) => block.getBoundingClientRect().height)

  const limit = pageHeight.value || Infinity
  const newPages = []
  let current = []
  let runningHeight = 0

  blocks.forEach((block, i) => {
    const h = heights[i]
    if (current.length > 0 && runningHeight + h > limit) {
      newPages.push(current.map((el) => el.outerHTML).join(''))
      current = []
      runningHeight = 0
    }
    // A single block taller than one page (an oversized image/paragraph) is
    // still placed on its own page rather than looped on forever — this one
    // page may overflow slightly; the container's overflow-hidden and the
    // image max-height CSS rule below bound how bad that can look.
    current.push(block)
    runningHeight += h
  })
  if (current.length > 0) {
    newPages.push(current.map((el) => el.outerHTML).join(''))
  }

  pages.value = newPages.length > 0 ? newPages : ['']
  totalPages.value = pages.value.length
  currentPage.value = Math.min(currentPage.value, totalPages.value - 1)

  // Images without explicit width/height attributes report zero height
  // until they've loaded, which can shift which page a block lands on.
  // Re-paginate once any still-loading images in this chapter resolve.
  const pendingImages = blocks.flatMap((b) => Array.from(b.querySelectorAll ? b.querySelectorAll('img') : []))
  pendingImages.forEach((img) => {
    if (!img.complete) {
      img.addEventListener('load', paginateContent, { once: true })
      img.addEventListener('error', paginateContent, { once: true })
    }
  })

  // Clear the measuring container so repeated resizes/font-size changes
  // don't leak DOM nodes across repagination passes.
  container.replaceChildren()
}

const measurePages = async () => {
  currentPage.value = 0
  if (mode.value !== 'h-page' && mode.value !== 'v-page') return
  await nextTick()
  paginateContent()
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

watch(mode, measurePages, { flush: 'post' })
watch(() => fontStyle.value.fontSize, measurePages, { flush: 'post' })

const goToChapter = (delta) => {
  if (navigating) return
  const target = props.chapter.sort_order + delta
  if (delta < 0 && !props.hasPrev) return
  if (delta > 0 && !props.hasNext) return
  navigating = true
  router.visit(`/library/books/${props.book.slug}/chapters/${target}`, {
    onFinish: () => { navigating = false },
  })
}

const onKeyLeft = () => {
  if (mode.value === 'h-page') goToPage(-1)
  else goToChapter(-1)
}
const onKeyRight = () => {
  if (mode.value === 'h-page') goToPage(1)
  else goToChapter(1)
}
const onKeyUp = () => {
  if (mode.value === 'v-page') goToPage(-1)
  else goToChapter(-1)
}
const onKeyDown = () => {
  if (mode.value === 'v-page') goToPage(1)
  else goToChapter(1)
}
const onKeySpace = () => {
  if (mode.value === 'h-page' || mode.value === 'v-page') goToPage(1)
  else goToChapter(1)
}

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

/* Pages are now swapped by re-rendering v-html rather than sliding via
   transform, since pagination is pre-computed block packing, not a CSS
   column offset. A brief fade softens the swap; :key="currentPage" on the
   element re-triggers this transition on every page change. */
.page-fade {
  animation: page-fade-in 0.15s ease;
}

@keyframes page-fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

.markdown-body :deep(img) {
  max-width: 100%;
  /* In paged modes (h-page/v-page) the parent sets --page-height to the
     available page height so a tall image is scaled down to fit within a
     single page instead of being clipped by the page's fixed-height,
     overflow-hidden container. Scroll mode never sets --page-height, so
     this falls back to no constraint there. */
  max-height: var(--page-height, none);
  height: auto;
  width: auto;
  object-fit: contain;
  display: block;
  margin: 0 auto;
  border-radius: 0.5em;
}
</style>

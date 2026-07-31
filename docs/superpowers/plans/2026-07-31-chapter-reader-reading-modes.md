# Chapter Reader Reading Modes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add four reading modes (Scroll, H-Page, V-Page, Autoscroll) to the public Chapter Reader, behind a unified settings drawer, per `docs/superpowers/specs/2026-07-31-chapter-reader-reading-modes-design.md`.

**Architecture:** A new `useReaderMode` composable (module-singleton, localStorage-persisted, mirrors the existing `useReaderTheme` pattern) drives which of three rendering layouts `ChapterReader.vue` shows. H-Page uses a CSS multi-column layout translated by a JS-measured page width; V-Page uses viewport-height-increment smooth scrolling; Autoscroll uses a `requestAnimationFrame` loop. A new `ReaderSettingsDrawer.vue` houses all controls (theme, font, mode, autoscroll speed) behind a single gear-icon trigger.

**Tech Stack:** Vue 3 Composition API, Inertia.js v2, Tailwind v4, Vitest + @vue/test-utils (no backend changes).

**Implementation note (resolved during planning):** the spec describes V-Page as CSS `scroll-snap` on fixed-height page sections. True scroll-snap requires discrete DOM elements to snap to, which would require splitting chapter HTML by measured height (the JS-measurement approach the spec explicitly rejected in favor of letting the browser do reflow). This plan implements V-Page instead as viewport-height-increment smooth scrolling on the same continuous content — same "flip one screen at a time" UX, same zero-content-splitting property, no contradiction with the approved CSS-native approach. H-Page's multi-column technique is unaffected and implemented exactly as specced.

---

### Task 1: `useReaderMode` composable

**Files:**
- Create: `resources/js/composables/useReaderMode.js`
- Test: `tests/js/useReaderMode.test.js`

- [ ] **Step 1: Write the failing tests**

```js
// tests/js/useReaderMode.test.js
import { describe, it, expect, beforeEach } from 'vitest'

beforeEach(() => {
  localStorage.clear()
  vi.resetModules()
})

describe('useReaderMode', () => {
  it('defaults to scroll mode at medium autoscroll speed, not playing', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { mode, autoscrollSpeed, isPlaying } = useReaderMode()
    expect(mode.value).toBe('scroll')
    expect(autoscrollSpeed.value).toBe('medium')
    expect(isPlaying.value).toBe(false)
  })

  it('persists mode changes to localStorage', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { setMode } = useReaderMode()
    setMode('h-page')
    const stored = JSON.parse(localStorage.getItem('library-reader-mode'))
    expect(stored.mode).toBe('h-page')
  })

  it.each(['scroll', 'h-page', 'v-page', 'autoscroll'])('can set the %s mode', async (mode) => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const api = useReaderMode()
    api.setMode(mode)
    expect(api.mode.value).toBe(mode)
  })

  it.each(['slow', 'medium', 'fast'])('can set the %s autoscroll speed and persists it', async (speed) => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { setAutoscrollSpeed, autoscrollSpeed } = useReaderMode()
    setAutoscrollSpeed(speed)
    expect(autoscrollSpeed.value).toBe(speed)
    const stored = JSON.parse(localStorage.getItem('library-reader-mode'))
    expect(stored.autoscrollSpeed).toBe(speed)
  })

  it('exposes a pxPerFrame that increases with speed', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { setAutoscrollSpeed, pxPerFrame } = useReaderMode()
    setAutoscrollSpeed('slow')
    const slow = pxPerFrame.value
    setAutoscrollSpeed('fast')
    const fast = pxPerFrame.value
    expect(fast).toBeGreaterThan(slow)
  })

  it('play/pause/togglePlay control isPlaying', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { isPlaying, play, pause, togglePlay } = useReaderMode()
    play()
    expect(isPlaying.value).toBe(true)
    pause()
    expect(isPlaying.value).toBe(false)
    togglePlay()
    expect(isPlaying.value).toBe(true)
  })

  it('switching mode always pauses autoscroll', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { play, isPlaying, setMode } = useReaderMode()
    play()
    setMode('h-page')
    expect(isPlaying.value).toBe(false)
  })

  it('shares state across multiple calls in the same session', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const a = useReaderMode()
    const b = useReaderMode()
    a.setMode('autoscroll')
    expect(b.mode.value).toBe('autoscroll')
  })
})
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm run test:unit -- useReaderMode`
Expected: FAIL — `Cannot find module '@/composables/useReaderMode'`

- [ ] **Step 3: Implement the composable**

```js
// resources/js/composables/useReaderMode.js
import { computed, ref, watch } from 'vue'

const DEFAULT_MODE = 'scroll'
const DEFAULT_SPEED = 'medium'
const STORAGE_KEY = 'library-reader-mode'

const SPEED_PX_PER_FRAME = {
  slow: 0.5,
  medium: 1.2,
  fast: 2.4,
}

function loadPreferences() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : { mode: DEFAULT_MODE, autoscrollSpeed: DEFAULT_SPEED }
  } catch {
    return { mode: DEFAULT_MODE, autoscrollSpeed: DEFAULT_SPEED }
  }
}

const preferences = ref(loadPreferences())
const isPlaying = ref(false)

watch(preferences, (value) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(value))
}, { deep: true, flush: 'sync' })

export function useReaderMode() {
  const mode = computed(() => preferences.value.mode)
  const autoscrollSpeed = computed(() => preferences.value.autoscrollSpeed)
  const pxPerFrame = computed(() => SPEED_PX_PER_FRAME[preferences.value.autoscrollSpeed] ?? SPEED_PX_PER_FRAME[DEFAULT_SPEED])

  const setMode = (value) => {
    preferences.value = { ...preferences.value, mode: value }
    isPlaying.value = false
  }
  const setAutoscrollSpeed = (speed) => {
    preferences.value = { ...preferences.value, autoscrollSpeed: speed }
  }
  const play = () => { isPlaying.value = true }
  const pause = () => { isPlaying.value = false }
  const togglePlay = () => { isPlaying.value = !isPlaying.value }

  return { mode, autoscrollSpeed, pxPerFrame, isPlaying, setMode, setAutoscrollSpeed, play, pause, togglePlay }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npm run test:unit -- useReaderMode`
Expected: PASS (8 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useReaderMode.js tests/js/useReaderMode.test.js
git commit -m "feat: add useReaderMode composable for chapter reader reading modes"
```

---

### Task 2: `ReaderSettingsDrawer` component

**Files:**
- Create: `resources/js/Components/ReaderSettingsDrawer.vue`
- Test: `tests/js/ReaderSettingsDrawer.test.js`

- [ ] **Step 1: Write the failing tests**

```js
// tests/js/ReaderSettingsDrawer.test.js
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'

beforeEach(() => {
  localStorage.clear()
  vi.resetModules()
})

describe('ReaderSettingsDrawer', () => {
  it('renders nothing when closed', async () => {
    const { default: ReaderSettingsDrawer } = await import('@/Components/ReaderSettingsDrawer.vue')
    const wrapper = mount(ReaderSettingsDrawer, { props: { open: false } })
    expect(wrapper.text()).not.toContain('Reading Settings')
  })

  it('renders theme, font, and reading-mode sections when open', async () => {
    const { default: ReaderSettingsDrawer } = await import('@/Components/ReaderSettingsDrawer.vue')
    const wrapper = mount(ReaderSettingsDrawer, { props: { open: true } })
    expect(wrapper.text()).toContain('Reading Settings')
    expect(wrapper.text()).toContain('Sepia')
    expect(wrapper.text()).toContain('A+')
    expect(wrapper.text()).toContain('H-Page')
  })

  it('only shows autoscroll speed controls when mode is autoscroll', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { default: ReaderSettingsDrawer } = await import('@/Components/ReaderSettingsDrawer.vue')
    const { setMode } = useReaderMode()

    const wrapper = mount(ReaderSettingsDrawer, { props: { open: true } })
    expect(wrapper.text()).not.toContain('Autoscroll Speed')

    setMode('autoscroll')
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Autoscroll Speed')
    expect(wrapper.text()).toContain('Slow')
    expect(wrapper.text()).toContain('Play')
  })

  it('emits close when the backdrop or close button is clicked', async () => {
    const { default: ReaderSettingsDrawer } = await import('@/Components/ReaderSettingsDrawer.vue')
    const wrapper = mount(ReaderSettingsDrawer, { props: { open: true } })
    await wrapper.find('[data-testid="drawer-close"]').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('setMode buttons update the shared useReaderMode state', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { default: ReaderSettingsDrawer } = await import('@/Components/ReaderSettingsDrawer.vue')
    const { mode } = useReaderMode()

    const wrapper = mount(ReaderSettingsDrawer, { props: { open: true } })
    await wrapper.find('[data-testid="mode-h-page"]').trigger('click')
    expect(mode.value).toBe('h-page')
  })
})
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm run test:unit -- ReaderSettingsDrawer`
Expected: FAIL — `Cannot find module '@/Components/ReaderSettingsDrawer.vue'`

- [ ] **Step 3: Implement the component**

```vue
<!-- resources/js/Components/ReaderSettingsDrawer.vue -->
<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/30" @click="$emit('close')"></div>
      <div class="absolute top-0 right-0 h-full w-72 max-w-[85vw] shadow-xl overflow-y-auto" :class="themeClass">
        <div class="flex items-center justify-between px-5 py-4 border-b border-current/10">
          <h2 class="font-semibold text-sm">Reading Settings</h2>
          <button data-testid="drawer-close" @click="$emit('close')" class="opacity-70 hover:opacity-100 text-lg leading-none">✕</button>
        </div>

        <div class="px-5 py-4 space-y-6 text-sm">
          <section>
            <h3 class="text-xs uppercase tracking-wide opacity-60 mb-2">Theme</h3>
            <div class="flex flex-wrap gap-3">
              <button @click="setTheme('white')" class="opacity-70 hover:opacity-100">White</button>
              <button @click="setTheme('sepia')" class="opacity-70 hover:opacity-100">Sepia</button>
              <button @click="setTheme('sepia-dark')" class="opacity-70 hover:opacity-100">Dark Sepia</button>
              <button @click="setTheme('black')" class="opacity-70 hover:opacity-100">Black</button>
            </div>
          </section>

          <section>
            <h3 class="text-xs uppercase tracking-wide opacity-60 mb-2">Font size</h3>
            <div class="flex gap-3">
              <button @click="decreaseFontSize" class="opacity-70 hover:opacity-100">A−</button>
              <button @click="increaseFontSize" class="opacity-70 hover:opacity-100">A+</button>
            </div>
          </section>

          <section>
            <h3 class="text-xs uppercase tracking-wide opacity-60 mb-2">Reading Mode</h3>
            <div class="flex flex-wrap gap-3">
              <button data-testid="mode-scroll" @click="setMode('scroll')" class="opacity-70 hover:opacity-100" :class="mode === 'scroll' ? 'font-bold opacity-100' : ''">Scroll</button>
              <button data-testid="mode-h-page" @click="setMode('h-page')" class="opacity-70 hover:opacity-100" :class="mode === 'h-page' ? 'font-bold opacity-100' : ''">H-Page</button>
              <button data-testid="mode-v-page" @click="setMode('v-page')" class="opacity-70 hover:opacity-100" :class="mode === 'v-page' ? 'font-bold opacity-100' : ''">V-Page</button>
              <button data-testid="mode-autoscroll" @click="setMode('autoscroll')" class="opacity-70 hover:opacity-100" :class="mode === 'autoscroll' ? 'font-bold opacity-100' : ''">Autoscroll</button>
            </div>
          </section>

          <section v-if="mode === 'autoscroll'">
            <h3 class="text-xs uppercase tracking-wide opacity-60 mb-2">Autoscroll Speed</h3>
            <div class="flex flex-wrap gap-3 mb-4">
              <button @click="setAutoscrollSpeed('slow')" class="opacity-70 hover:opacity-100" :class="autoscrollSpeed === 'slow' ? 'font-bold opacity-100' : ''">Slow</button>
              <button @click="setAutoscrollSpeed('medium')" class="opacity-70 hover:opacity-100" :class="autoscrollSpeed === 'medium' ? 'font-bold opacity-100' : ''">Medium</button>
              <button @click="setAutoscrollSpeed('fast')" class="opacity-70 hover:opacity-100" :class="autoscrollSpeed === 'fast' ? 'font-bold opacity-100' : ''">Fast</button>
            </div>
            <button @click="togglePlay" class="px-3 py-1.5 rounded-lg border border-current/20 hover:bg-current/10">
              {{ isPlaying ? 'Pause' : 'Play' }}
            </button>
          </section>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { useReaderTheme } from '@/composables/useReaderTheme'
import { useReaderMode } from '@/composables/useReaderMode'

defineProps({ open: { type: Boolean, default: false } })
defineEmits(['close'])

const { themeClass, setTheme, increaseFontSize, decreaseFontSize } = useReaderTheme()
const { mode, autoscrollSpeed, isPlaying, setMode, setAutoscrollSpeed, togglePlay } = useReaderMode()
</script>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npm run test:unit -- ReaderSettingsDrawer`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/ReaderSettingsDrawer.vue tests/js/ReaderSettingsDrawer.test.js
git commit -m "feat: add ReaderSettingsDrawer component"
```

---

### Task 3: Wire the drawer into `ChapterReader.vue`, simplify the toolbar

**Files:**
- Modify: `resources/js/Pages/Public/Library/ChapterReader.vue`

- [ ] **Step 1: Replace the toolbar and add the drawer**

Replace the `<nav>` block (lines 7–21 of the current file) with:

```vue
    <nav class="sticky top-0 z-40 backdrop-blur-xl border-b border-current/10" :class="themeClass">
      <div class="max-w-3xl mx-auto px-6 h-14 flex items-center justify-between text-sm">
        <a :href="`/library/books/${book.slug}`" class="opacity-70 hover:opacity-100 transition-opacity truncate max-w-[10rem]">
          ← {{ book.title }}
        </a>
        <button @click="drawerOpen = true" class="opacity-70 hover:opacity-100 text-lg leading-none" aria-label="Reading settings">⚙</button>
      </div>
    </nav>
```

Add the drawer as the last element inside the root `<div class="min-h-screen ...">`, immediately before its closing tag:

```vue
    <ReaderSettingsDrawer :open="drawerOpen" @close="drawerOpen = false" />
```

- [ ] **Step 2: Update the script block**

Replace the `<script setup>` block with:

```vue
<script setup>
import { ref } from 'vue'
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
const { mode } = useReaderMode()

const drawerOpen = ref(false)
</script>
```

- [ ] **Step 3: Manually verify scroll mode is unaffected**

Run: `npm run build`
Expected: builds with no errors. Load a chapter in the browser (dev server already running per Task 9 of the public-UI plan) — confirm the toolbar now shows `← Book Title` and a `⚙` button, clicking `⚙` opens a right-side drawer with Theme/Font size/Reading Mode sections, and the White/Sepia/Dark Sepia/Black buttons inside it still change the reading theme exactly as before.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Public/Library/ChapterReader.vue
git commit -m "feat: move chapter reader theme/font controls into a settings drawer"
```

---

### Task 4: Autoscroll mode

**Files:**
- Modify: `resources/js/Pages/Public/Library/ChapterReader.vue`

- [ ] **Step 1: Add the autoscroll loop to the script block**

Add to the `<script setup>` block (after the `drawerOpen` line):

```js
import { onMounted, onUnmounted, watch } from 'vue'

const { pxPerFrame, isPlaying, play, pause } = useReaderMode()

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
```

Note: `import { onMounted, onUnmounted, watch } from 'vue'` should be merged into the existing `import { ref } from 'vue'` line from Task 3, i.e. the final import line is:

```js
import { ref, onMounted, onUnmounted, watch } from 'vue'
```

- [ ] **Step 2: Manually verify**

Run: `npm run build`, reload a chapter, open the drawer, switch to Autoscroll, click Play. Expected: the page scrolls smoothly downward. Manually scroll or click anywhere: expected the scrolling stops (drawer's button now reads "Play" again if reopened).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Public/Library/ChapterReader.vue
git commit -m "feat: add autoscroll mode with pause-on-interaction"
```

---

### Task 5: Forward chapter auto-advance for autoscroll (sessionStorage resume)

**Files:**
- Modify: `resources/js/Pages/Public/Library/ChapterReader.vue`

- [ ] **Step 1: Add boundary detection and resume-on-mount logic**

Add to the `<script setup>` block:

```js
import { router } from '@inertiajs/vue3'

const AUTOSCROLL_RESUME_KEY = 'library-autoscroll-resume'

const checkAutoscrollBoundary = () => {
  if (mode.value !== 'autoscroll' || !isPlaying.value) return
  const atBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4
  if (!atBottom) return
  if (props.hasNext) {
    sessionStorage.setItem(AUTOSCROLL_RESUME_KEY, '1')
    router.visit(`/library/books/${props.book.slug}/chapters/${props.chapter.sort_order + 1}`)
  } else {
    pause()
  }
}
```

Note: `defineProps` already returns the props object when captured, so change the props declaration from:

```js
defineProps({
```

to:

```js
const props = defineProps({
```

(this makes `props.hasNext`, `props.book`, `props.chapter` available; the template already uses the destructured prop names directly via Vue's automatic template exposure, which still works unchanged since `defineProps` results remain available as top-level bindings in `<script setup>` — but explicit `props.x` access requires capturing the return value, which this step adds.)

Wire the boundary check into the autoscroll loop by updating `autoscrollTick`:

```js
const autoscrollTick = () => {
  if (isPlaying.value) {
    window.scrollBy(0, pxPerFrame.value)
    checkAutoscrollBoundary()
  }
  rafId = requestAnimationFrame(autoscrollTick)
}
```

Add the resume check to `onMounted` (append inside the existing `onMounted(() => { ... })` block from Task 4):

```js
  if (sessionStorage.getItem(AUTOSCROLL_RESUME_KEY)) {
    sessionStorage.removeItem(AUTOSCROLL_RESUME_KEY)
    play()
  }
```

- [ ] **Step 2: Manually verify**

On a book with at least 2 chapters: open chapter 1 in Autoscroll mode, click Play, and let it scroll to the bottom. Expected: it automatically navigates to chapter 2 and continues autoscrolling without needing to press Play again. On the book's last chapter, let autoscroll reach the bottom. Expected: it stops there with no navigation and no error.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Public/Library/ChapterReader.vue
git commit -m "feat: auto-advance autoscroll to the next chapter at the bottom"
```

---

### Task 6: H-Page mode (CSS multi-column pagination)

**Files:**
- Modify: `resources/js/Pages/Public/Library/ChapterReader.vue`

- [ ] **Step 1: Replace the `<main>` block with mode-conditional layouts**

Replace the current single `<main>` block (the one containing `chapter.content` and the Previous/Next links) with:

```vue
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

    <main v-else-if="mode === 'h-page'" ref="pagedViewportEl" class="overflow-hidden max-w-3xl mx-auto" :style="{ height: 'calc(100vh - 3.5rem)' }">
      <div ref="pagedEl" class="markdown-body px-6 py-10 h-full"
        :style="{ ...fontStyle, columnWidth: pageWidth + 'px', columnGap: 0, columnFill: 'auto', transform: `translateX(-${currentPage * pageWidth}px)`, transition: 'transform 0.25s ease' }">
        <h1 class="text-xl font-bold mb-6">{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}</h1>
        <div v-html="chapter.content"></div>
      </div>
    </main>

    <main v-else-if="mode === 'v-page'" ref="pagedViewportEl" class="overflow-hidden max-w-3xl mx-auto" :style="{ height: 'calc(100vh - 3.5rem)' }">
      <div ref="pagedEl" class="markdown-body px-6 py-10" :style="{ ...fontStyle, transform: `translateY(-${currentPage * pageHeight}px)`, transition: 'transform 0.25s ease' }">
        <h1 class="text-xl font-bold mb-6">{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}</h1>
        <div v-html="chapter.content"></div>
      </div>
    </main>
```

- [ ] **Step 2: Add page-measurement and navigation state to the script block**

```js
import { nextTick } from 'vue'

const pagedViewportEl = ref(null)
const pagedEl = ref(null)
const currentPage = ref(0)
const pageWidth = ref(0)
const pageHeight = ref(0)
const totalPages = ref(1)

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
  if (mode.value !== 'h-page' && mode.value !== 'v-page') return
  const next = currentPage.value + delta
  if (next < 0) {
    if (props.hasPrev) {
      router.visit(`/library/books/${props.book.slug}/chapters/${props.chapter.sort_order - 1}`)
    }
    return
  }
  if (next >= totalPages.value) {
    if (props.hasNext) {
      router.visit(`/library/books/${props.book.slug}/chapters/${props.chapter.sort_order + 1}`)
    }
    return
  }
  currentPage.value = next
}

watch(mode, measurePages)
watch(() => fontStyle.value.fontSize, measurePages)

onMounted(() => {
  measurePages()
  window.addEventListener('resize', measurePages)
})
onUnmounted(() => {
  window.removeEventListener('resize', measurePages)
})
```

(Merge the new `onMounted`/`onUnmounted` bodies into the single existing `onMounted`/`onUnmounted` functions from Tasks 4–5 rather than declaring them twice — there must be only one `onMounted(...)` call and one `onUnmounted(...)` call in the file.)

- [ ] **Step 3: Add input handlers (click zones, arrow keys, swipe)**

Merge these attributes onto the existing root `<div class="min-h-screen ...">` from the top of the file:

```vue
  <div class="min-h-screen font-sans" :class="themeClass" tabindex="0"
    @keydown.left="mode === 'h-page' && goToPage(-1)" @keydown.right="mode === 'h-page' && goToPage(1)"
    @keydown.up="mode === 'v-page' && goToPage(-1)" @keydown.down="mode === 'v-page' && goToPage(1)"
    @touchstart="onTouchStart" @touchend="onTouchEnd">
```

Add a click-zone handler only inside the H-Page/V-Page `<main>` elements, e.g. for H-Page:

```vue
    <main v-else-if="mode === 'h-page'" ref="pagedViewportEl" class="overflow-hidden max-w-3xl mx-auto relative" :style="{ height: 'calc(100vh - 3.5rem)' }">
      <button class="absolute left-0 top-0 h-full w-1/3 z-10" aria-label="Previous page" @click="goToPage(-1)"></button>
      <button class="absolute right-0 top-0 h-full w-1/3 z-10" aria-label="Next page" @click="goToPage(1)"></button>
      <div ref="pagedEl" ...
```

(apply the same two click-zone buttons, adjusted to `w-full h-1/3` top/bottom instead of left/right, inside the V-Page `<main>`.)

Add the swipe handlers to the script block:

```js
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
```

- [ ] **Step 4: Manually verify H-Page**

Run: `npm run build`, open a chapter, switch to H-Page in the drawer. Expected: content reflows into a single screen-width page; clicking the right third of the screen (or pressing →) advances one page with a smooth slide; clicking the left third (or ←) goes back; reaching the last page and advancing again navigates to the next chapter (if any); going back past the first page navigates to the previous chapter's page 1 (if any).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Public/Library/ChapterReader.vue
git commit -m "feat: add H-Page CSS-column pagination with tap/arrow/swipe navigation"
```

---

### Task 7: V-Page mode (viewport-height paging)

**Files:**
- Modify: `resources/js/Pages/Public/Library/ChapterReader.vue`

- [ ] **Step 1: Manually verify V-Page** (the rendering, measurement, and `goToPage` logic were already added in Task 6 since H-Page and V-Page share the same state/functions — this task is verification-only)

Run: `npm run build`, open a chapter, switch to V-Page in the drawer. Expected: content is clipped to one viewport-height "page"; clicking the bottom third of the screen (or pressing ↓) advances one page with a smooth vertical slide; clicking the top third (or ↑) goes back; forward/backward chapter-boundary behavior matches H-Page.

- [ ] **Step 2: Commit** (only if Step 1 surfaced fixes)

```bash
git add resources/js/Pages/Public/Library/ChapterReader.vue
git commit -m "fix: address V-Page verification findings"
```

---

### Task 8: Full manual verification pass

**Files:** none (verification only)

- [ ] **Step 1: Run the JS test suite**

Run: `npm run test:unit`
Expected: all tests pass, including the pre-existing `useReaderTheme`, `TextRenderer`, `FileViewer`, `ComicRenderer`, `rendererMap`, `pdfScale` suites plus the two new suites from Tasks 1–2.

- [ ] **Step 2: Run the full build**

Run: `npm run build`
Expected: no errors.

- [ ] **Step 3: Manual browser checklist**

Using a real multi-chapter book in the running dev environment, verify each item from the spec's Testing Approach section:

- All 4 modes render and are switchable from the drawer.
- Mode and autoscroll speed persist across a chapter navigation and a hard page reload.
- H-Page and V-Page: click zones, arrow keys, and touch swipe all navigate pages correctly.
- Forward auto-advance into the next chapter works for H-Page, V-Page, and Autoscroll; autoscroll resumes playing automatically after the auto-advance.
- Reaching the true last chapter's end stops cleanly in every mode (no crash, no infinite navigation loop).
- Backward page-flip past page 1 lands on the previous chapter's page 1.
- Autoscroll auto-pauses on manual scroll/click/tap, and resumes correctly via the drawer's Play button.
- The existing Previous/Next links at the bottom still work, unchanged, in every mode.
- Theme and font-size controls still work identically now that they live in the drawer.
- Drawer opens/closes correctly, and doesn't visually break on mobile viewport widths.

- [ ] **Step 4: Commit any fixes found during manual verification, then this plan is complete.**

<template>
  <nav class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur border-b border-slate-100 transition-shadow duration-300"
       :class="scrolled ? 'shadow-md' : ''">
    <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
      <a href="/" class="font-semibold text-slate-800 tracking-tight hover:text-orange-500 transition-colors">
        {{ settings?.site_name || 'Portfolio' }}
      </a>

      <!-- Desktop links -->
      <div class="hidden md:flex items-center gap-1">
        <a v-for="link in sectionLinks" :key="link.href" :href="link.href"
          class="relative px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-orange-500 transition-colors duration-200 rounded-lg hover:bg-orange-50"
          :class="activeSection === link.id ? 'text-orange-500' : ''">
          {{ link.label }}
          <span class="absolute bottom-0 left-3 right-3 h-0.5 bg-orange-500 rounded-full transition-all duration-300 origin-left"
                :class="activeSection === link.id ? 'scale-x-100' : 'scale-x-0'"></span>
        </a>
        <a href="/library"
          class="px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-orange-500 transition-colors duration-200 rounded-lg hover:bg-orange-50">
          Library
        </a>
      </div>

      <!-- Desktop auth CTA -->
      <div class="hidden md:flex items-center gap-3">
        <template v-if="isLoggedIn">
          <a v-if="isAdmin" href="/admin/dashboard"
            class="text-sm px-4 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-700 transition-colors duration-200">
            Dashboard →
          </a>
        </template>
        <template v-else>
          <a href="/login"
            class="text-sm px-4 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors duration-200">
            Login
          </a>
          <a href="/register"
            class="text-sm px-4 py-1.5 rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition-colors duration-200">
            Sign up
          </a>
        </template>
      </div>

      <!-- Mobile hamburger -->
      <button type="button"
        class="md:hidden p-2 -mr-2 text-slate-600 hover:text-orange-500 transition-colors"
        :aria-expanded="mobileOpen" :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
        aria-controls="mobile-nav-panel"
        @click="toggleMobile">
        <svg v-if="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
        <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Mobile slide-down panel -->
    <div id="mobile-nav-panel" ref="mobilePanel" class="md:hidden overflow-hidden" style="height: 0px; opacity: 0;"
         :inert="!mobileOpen">
      <div class="px-6 pb-4 pt-3 border-t border-slate-100 flex flex-col gap-1">
        <a v-for="link in sectionLinks" :key="link.href" :href="link.href"
          class="px-3 py-2 text-sm font-medium rounded-lg transition-colors"
          :class="activeSection === link.id ? 'text-orange-500 bg-orange-50' : 'text-slate-600 hover:bg-slate-50'"
          @click="closeMobile">
          {{ link.label }}
        </a>
        <a href="/library"
          class="px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg transition-colors"
          @click="closeMobile">
          Library
        </a>

        <div class="flex items-center gap-3 mt-2 pt-3 border-t border-slate-100">
          <template v-if="isLoggedIn">
            <a v-if="isAdmin" href="/admin/dashboard"
              class="text-sm px-4 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-700 transition-colors duration-200">
              Dashboard →
            </a>
          </template>
          <template v-else>
            <a href="/login"
              class="text-sm px-4 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors duration-200">
              Login
            </a>
            <a href="/register"
              class="text-sm px-4 py-1.5 rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition-colors duration-200">
              Sign up
            </a>
          </template>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { animate } from 'animejs'
import { usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  // Optional anchor-link list, e.g. [{ id: 'about', label: 'About' }, ...].
  // Pages without in-page sections (everything but Portfolio) pass [].
  sections: { type: Array, default: () => [] },
})

// ── Auth-aware CTA ───────────────────────────────────────────────────────────
const { props: pageProps } = usePage()
const isLoggedIn = computed(() => !!pageProps.auth?.user)
const isAdmin = computed(() => pageProps.auth?.roles?.includes('admin') ?? false)

// ── Section links + scroll-spy ───────────────────────────────────────────────
const sectionLinks = computed(() =>
  props.sections.map(s => ({ id: s.id, label: s.label, href: `#${s.id}` }))
)

const scrolled = ref(false)
const activeSection = ref(props.sections[0]?.id ?? null)

const onScroll = () => {
  scrolled.value = window.scrollY > 20

  const ids = props.sections.map(s => s.id)
  if (!ids.length) return

  for (const id of [...ids].reverse()) {
    const el = document.getElementById(id)
    if (el && window.scrollY >= el.offsetTop - 100) {
      activeSection.value = id
      break
    }
  }
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
  onScroll()
})
onUnmounted(() => window.removeEventListener('scroll', onScroll))

// ── Mobile hamburger menu (interaction-triggered, animated with anime.js) ───
const mobileOpen = ref(false)
const mobilePanel = ref(null)

const toggleMobile = () => (mobileOpen.value ? closeMobile() : openMobile())

const onKey = (e) => {
  if (e.key === 'Escape' && mobileOpen.value) closeMobile()
}

onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

const openMobile = async () => {
  mobileOpen.value = true
  await nextTick()
  const el = mobilePanel.value
  if (!el) return

  const targetHeight = el.scrollHeight
  animate(el, {
    height: [0, targetHeight],
    opacity: [0, 1],
    duration: 300,
    ease: 'outQuad',
    onComplete: () => {
      el.style.height = 'auto'
    },
  })
}

const closeMobile = () => {
  const el = mobilePanel.value
  if (!el) {
    mobileOpen.value = false
    return
  }

  const currentHeight = el.scrollHeight
  animate(el, {
    height: [currentHeight, 0],
    opacity: [1, 0],
    duration: 250,
    ease: 'inQuad',
    onComplete: () => {
      mobileOpen.value = false
    },
  })
}
</script>

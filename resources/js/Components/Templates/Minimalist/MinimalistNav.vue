<template>
  <nav class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
      <a href="/" class="font-semibold text-slate-800 tracking-tight hover:text-orange-500 transition-colors">
        {{ settings?.site_name || 'Portfolio' }}
      </a>

      <!-- Desktop links -->
      <div class="hidden md:flex items-center gap-1">
        <a v-for="link in sectionLinks" :key="link.href" :href="link.href"
          class="px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-orange-500 transition-colors duration-200 rounded-lg hover:bg-orange-50">
          {{ link.label }}
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

    <!-- Mobile panel — CSS grid-rows accordion (0fr <-> 1fr) instead of a
         fixed max-height: animates to the panel's true content height with
         no clipping risk, regardless of how many sections/links a page
         passes in. -->
    <div id="mobile-nav-panel" class="md:hidden grid transition-[grid-template-rows] duration-150"
         :class="mobileOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
         :inert="!mobileOpen">
      <div class="overflow-hidden">
        <div class="px-6 pb-4 pt-3 border-t border-slate-100 flex flex-col gap-1">
          <a v-for="link in sectionLinks" :key="link.href" :href="link.href"
            class="px-3 py-2 text-sm font-medium rounded-lg transition-colors text-slate-600 hover:bg-slate-50"
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
    </div>
  </nav>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'

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

// ── Section links ─────────────────────────────────────────────────────────────
// Plain anchor links; native browser scrolling handles navigation. No
// scroll-spy / active-section highlighting in this template.
const sectionLinks = computed(() =>
  props.sections.map(s => ({ id: s.id, label: s.label, href: `#${s.id}` }))
)

// ── Mobile hamburger menu (plain toggle, no motion) ──────────────────────────
const mobileOpen = ref(false)

const toggleMobile = () => (mobileOpen.value = !mobileOpen.value)
const closeMobile = () => (mobileOpen.value = false)

const onKey = (e) => {
  if (e.key === 'Escape' && mobileOpen.value) closeMobile()
}

onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

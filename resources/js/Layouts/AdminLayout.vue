<template>
  <div class="flex h-screen overflow-hidden bg-slate-50">
    <!-- Sidebar -->
    <aside class="w-60 bg-slate-900 flex flex-col flex-shrink-0">
      <div class="h-16 px-5 flex items-center gap-2.5 border-b border-slate-800/80 flex-shrink-0">
        <div class="w-7 h-7 rounded-lg bg-orange-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">P</div>
        <span class="text-sm font-semibold text-white tracking-tight truncate">Portfolio CMS</span>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
        <Link
          v-for="item in nav"
          :key="item.href"
          :href="item.href"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
          :class="isActive(item.href)
            ? 'bg-orange-500/10 text-orange-400'
            : 'text-slate-400 hover:text-white hover:bg-slate-800/70'"
        >
          <svg class="w-[18px] h-[18px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path :d="item.icon || DEFAULT_ICON" />
          </svg>
          <span class="truncate">{{ item.label }}</span>
        </Link>
      </nav>

      <div class="px-4 py-4 border-t border-slate-800/80 flex items-center justify-between gap-2 flex-shrink-0">
        <div class="flex items-center gap-2 min-w-0">
          <div class="w-7 h-7 rounded-full bg-slate-700 flex items-center justify-center text-[11px] font-semibold text-slate-200 flex-shrink-0">
            {{ userInitial }}
          </div>
          <span class="text-xs text-slate-400 truncate">{{ page.props.auth.user?.name }}</span>
        </div>
        <Link
          href="/logout"
          method="post"
          as="button"
          class="text-xs text-red-400 hover:text-red-300 flex-shrink-0"
        >
          Logout
        </Link>
      </div>
    </aside>

    <!-- Main content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Demo mode banner -->
      <div v-if="page.props.demo" class="bg-amber-400 text-amber-900 text-xs font-medium px-6 py-2 flex items-center gap-2 flex-shrink-0">
        <span>⚡ Demo Mode</span>
        <span class="opacity-60">—</span>
        <span>You're exploring a live demo — any edits are visible on the public site and reset periodically.</span>
        <a href="/" target="_blank" class="ml-auto underline hover:no-underline flex-shrink-0">View public site ↗</a>
      </div>

      <AnnouncementBanner :announcements="announcements" />

      <!-- Topbar -->
      <header class="h-14 flex-shrink-0 flex items-center justify-between px-6 border-b border-slate-200 bg-white">
        <div class="flex items-center gap-2 text-sm min-w-0">
          <span class="text-slate-400">Admin</span>
          <template v-if="activeNavLabel">
            <span class="text-slate-300">/</span>
            <span class="font-semibold text-slate-800 truncate">{{ activeNavLabel }}</span>
          </template>
        </div>
        <Link href="/admin/profile" aria-label="View profile" class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-800 transition-colors flex-shrink-0">
          <span class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-[11px] font-semibold text-slate-600">
            {{ userInitial }}
          </span>
        </Link>
      </header>

      <main class="flex-1 overflow-y-auto">
        <div class="p-6">
          <slot />
        </div>
      </main>
    </div>

    <AnnouncementModal :announcements="announcements" />
    <ToastContainer />
    <ConfirmModal />
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AnnouncementBanner from '@/Components/AnnouncementBanner.vue'
import AnnouncementModal from '@/Components/AnnouncementModal.vue'
import ToastContainer from '@/Components/ToastContainer.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import { useToast } from '@/composables/useToast'

const page = usePage()
const announcements = computed(() => page.props.announcements ?? [])

// Every controller across the app already flashes success/error/warning into the
// session (HandleInertiaRequests shares them as page.props.flash.*) — surfacing
// them here as toasts means every existing and future page gets create/update/
// delete alerts for free, without each page needing its own inline banner.
const toast = useToast()
watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) toast.success(flash.success)
    if (flash?.error) toast.error(flash.error)
    if (flash?.warning) toast.warning(flash.warning)
  },
  { deep: true }
)

const userRoles  = computed(() => page.props.auth.roles ?? [])
const isAdmin    = computed(() => userRoles.value.includes('admin'))
const isEditor   = computed(() => userRoles.value.includes('editor'))
const isViewer   = computed(() => userRoles.value.includes('viewer'))

const userInitial = computed(() => (page.props.auth.user?.name || '?').charAt(0).toUpperCase())

// Fallback icon shown for any nav item without an `icon` — a plain visible
// circle outline, so a missing-icon state is obvious rather than looking
// like a rendering bug.
const DEFAULT_ICON = 'M12 4a8 8 0 100 16 8 8 0 000-16z'

// roles: which roles can see this link (matches backend middleware)
// icon: simple hand-drawn line icon path (no icon library dependency)
const allNav = [
  { label: 'Dashboard',     href: '/admin/dashboard',     roles: ['admin','editor','viewer'], icon: 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z' },
  { label: 'Tasks',         href: '/admin/tasks',         roles: ['admin','editor'], icon: 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11' },
  { label: 'Pages',         href: '/admin/pages',         roles: ['admin','editor','viewer'], icon: 'M6 3h8l4 4v14H6V3zm8 0v4h4M9 11h6M9 14h6M9 17h4' },
  { label: 'Library',       href: '/admin/library',      roles: ['admin','editor','viewer'], icon: 'M12 6c-1.4-.8-3-1.2-4.8-1.2S3.8 5.2 2.5 6v13c1.3-.8 3-1.2 4.7-1.2s3.4.4 4.8 1.2m0-13c1.4-.8 3-1.2 4.8-1.2s3.4.4 4.7 1.2v13c-1.3-.8-3-1.2-4.7-1.2s-3.4.4-4.8 1.2m0-13v13' },
  { label: 'EdTech',        href: '/admin/edtech',        roles: ['admin','editor','viewer'], icon: 'M12 14l9-5-9-5-9 5 9 5zM12 14l6.16-3.42A12.05 12.05 0 0121 12v4M12 14v7m-4-3.5v-4l4 2.2' },
  { label: 'Service Cards', href: '/admin/service-cards', roles: ['admin','editor','viewer'], icon: 'M3.5 6.5h17a1 1 0 011 1V17a1 1 0 01-1 1h-17a1 1 0 01-1-1V7.5a1 1 0 011-1zM2.5 10h19M6 14.5h3' },
  { label: 'Products',      href: '/admin/products',      roles: ['admin','editor','viewer'], icon: 'M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3zM4 7.5L12 12l8-4.5M12 12v9' },
  { label: 'GitHub',        href: '/admin/github',        roles: ['admin','editor','viewer'], icon: 'M9 8l-4 4 4 4M15 8l4 4-4 4M13.5 6.5l-3 11' },
  { label: 'Content',       href: '/admin/content',       roles: ['admin','editor','viewer'], icon: 'M7 3.5h7l4 4V20a.5.5 0 01-.5.5h-11a.5.5 0 01-.5-.5v-16a.5.5 0 01.5-.5zM14 3.5V8h4.5M9.5 12h5M9.5 15h5M9.5 18h3' },
  { label: 'Profile',       href: '/admin/profile',       roles: ['admin','editor','viewer'], icon: 'M12 12.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM5 19.5c.9-3 3.5-5 7-5s6.1 2 7 5' },
  { label: 'Billing',          href: '/admin/billing',          roles: ['admin','editor','viewer'], icon: 'M5.5 4h13a1 1 0 011 1v15l-2.5-1.5L14 20l-2-1.5L10 20l-2.5-1.5L5 20V5a1 1 0 01.5-1zM8.5 8.5h7M8.5 12h7M8.5 15.5h4' },
  { label: 'Wallet',           href: '/admin/wallet',           roles: ['admin','editor','viewer'], icon: 'M4 7a1.5 1.5 0 011.5-1.5h13A1.5 1.5 0 0120 7v1.5H4V7zM4 8.5h16V17a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 17V8.5zM15.5 12.75a1 1 0 100 2 1 1 0 000-2z' },
  { label: 'Payment Methods',  href: '/admin/payment-methods',  roles: ['admin','editor','viewer'], icon: 'M3.5 9.5L12 4.5l8.5 5M5 10v8.5M19 10v8.5M9 10v8.5M15 10v8.5M3.5 18.5h17' },
  { label: 'Users',         href: '/admin/users',         roles: ['admin'], icon: 'M9 12.5a3 3 0 100-6 3 3 0 000 6zM3.5 19c.7-2.7 2.9-4.5 5.5-4.5s4.8 1.8 5.5 4.5M16 11a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM14.5 14.5c2 .3 3.6 1.8 4 4' },
  { label: 'Settings',      href: '/admin/settings',      roles: ['admin'], icon: 'M12 8.8a3.2 3.2 0 100 6.4 3.2 3.2 0 000-6.4zM12 2v2.3M12 19.7V22M4.6 4.6l1.6 1.6M17.8 17.8l1.6 1.6M2 12h2.3M19.7 12H22M4.6 19.4l1.6-1.6M17.8 6.2l1.6-1.6' },
  { label: 'Organizations', href: '/admin/organizations', roles: ['admin','editor','viewer'], icon: 'M4.5 20.5V6l6.5-3v17.5M13.5 20.5V10l6 2.5v8M4.5 20.5h15M8 9h.01M8 12.5h.01M8 16h.01' },
  { label: 'Announcements', href: '/admin/announcements', roles: ['admin'], icon: 'M3.5 10.5v4a1 1 0 001 1h2l5 3v-12l-5 3h-2a1 1 0 00-1 1zM15 9.5a3 3 0 010 6M17.3 6.5a6.5 6.5 0 010 12' },
]

// The `rag` Postgres connection availability (missing driver, unreachable host,
// bad creds, etc.) also gates the "Advanced Search" drawer trigger on the Library
// page — kept here (rather than moved into the drawer composable) since it's
// already sourced from this shared page prop and other admin pages may want it.
const ragAvailable = computed(() => page.props.ragAvailable ?? true)
const nav = computed(() => allNav
  .filter(item => item.roles.some(r => userRoles.value.includes(r))))

// Some nav hrefs can be prefixes of others (e.g. /admin/library and any future
// nested route). Pick the longest matching href rather than just "does the URL
// start with this href", so only the most specific nav item lights up.
const isActive = (href) => {
  const matches = nav.value.filter(item => page.url.startsWith(item.href))
  if (matches.length === 0) return false
  const longest = matches.reduce((a, b) => (b.href.length > a.href.length ? b : a))
  return longest.href === href
}

const activeNavLabel = computed(() => nav.value.find(item => isActive(item.href))?.label)
</script>

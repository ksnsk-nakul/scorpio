<template>
  <div class="flex h-screen overflow-hidden bg-slate-50">
    <!-- Sidebar -->
    <aside class="w-56 bg-slate-900 flex flex-col flex-shrink-0">
      <div class="px-4 py-5 text-xs font-semibold text-slate-500 uppercase tracking-widest">
        Portfolio CMS
      </div>
      <nav class="flex-1 px-3 space-y-1">
        <Link
          v-for="item in nav"
          :key="item.href"
          :href="item.href"
          class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition"
          :class="isActive(item.href)
            ? 'bg-blue-600 text-white'
            : 'text-slate-400 hover:text-white hover:bg-slate-800'"
        >
          {{ item.label }}
        </Link>
      </nav>
      <div class="px-4 py-4 border-t border-slate-800 flex items-center justify-between">
        <span class="text-xs text-slate-400 truncate">{{ page.props.auth.user?.name }}</span>
        <Link
          href="/logout"
          method="post"
          as="button"
          class="text-xs text-red-400 hover:text-red-300 ml-2 flex-shrink-0"
        >
          Logout
        </Link>
      </div>
    </aside>

    <!-- Main content -->
    <main class="flex-1 overflow-y-auto">
      <!-- Demo mode banner -->
      <div v-if="page.props.demo" class="bg-amber-400 text-amber-900 text-xs font-medium px-6 py-2 flex items-center gap-2">
        <span>⚡ Demo Mode</span>
        <span class="opacity-60">—</span>
        <span>You're exploring a live demo — any edits are visible on the public site and reset periodically.</span>
        <a href="/" target="_blank" class="ml-auto underline hover:no-underline flex-shrink-0">View public site ↗</a>
      </div>
      <AnnouncementBanner :announcements="announcements" />
      <div class="p-6">
        <slot />
      </div>
    </main>
    <AnnouncementModal :announcements="announcements" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AnnouncementBanner from '@/Components/AnnouncementBanner.vue'
import AnnouncementModal from '@/Components/AnnouncementModal.vue'

const page = usePage()
const announcements = computed(() => page.props.announcements ?? [])

const userRoles  = computed(() => page.props.auth.roles ?? [])
const isAdmin    = computed(() => userRoles.value.includes('admin'))
const isEditor   = computed(() => userRoles.value.includes('editor'))
const isViewer   = computed(() => userRoles.value.includes('viewer'))

// roles: which roles can see this link (matches backend middleware)
const allNav = [
  { label: 'Dashboard',     href: '/admin/dashboard',     roles: ['admin','editor','viewer'] },
  { label: 'Pages',         href: '/admin/pages',         roles: ['admin','editor','viewer'] },
  { label: 'Service Cards', href: '/admin/service-cards', roles: ['admin','editor','viewer'] },
  { label: 'Products',      href: '/admin/products',      roles: ['admin','editor','viewer'] },
  { label: 'GitHub',        href: '/admin/github',        roles: ['admin','editor','viewer'] },
  { label: 'Content',       href: '/admin/content',       roles: ['admin','editor','viewer'] },
  { label: 'Profile',       href: '/admin/profile',       roles: ['admin','editor','viewer'] },
  { label: 'Billing',          href: '/admin/billing',          roles: ['admin','editor','viewer'] },
  { label: 'Wallet',           href: '/admin/wallet',           roles: ['admin','editor','viewer'] },
  { label: 'Payment Methods',  href: '/admin/payment-methods',  roles: ['admin','editor','viewer'] },
  { label: 'Users',         href: '/admin/users',         roles: ['admin'] },
  { label: 'Payment',       href: '/admin/payment',       roles: ['admin'] },
  { label: 'Settings',      href: '/admin/settings',      roles: ['admin'] },
  { label: 'Organizations', href: '/admin/organizations', roles: ['admin','editor','viewer'] },
  { label: 'Announcements', href: '/admin/announcements', roles: ['admin'] },
]

const nav = computed(() => allNav.filter(item => item.roles.some(r => userRoles.value.includes(r))))

const isActive = (href) => page.url.startsWith(href)
</script>

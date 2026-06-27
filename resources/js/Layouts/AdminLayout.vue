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
      <div class="p-6">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const isAdmin = computed(() => page.props.auth.roles?.includes('admin'))

const allNav = [
  { label: 'Dashboard',     href: '/admin/dashboard',     adminOnly: false },
  { label: 'Pages',         href: '/admin/pages',         adminOnly: false },
  { label: 'Service Cards', href: '/admin/service-cards', adminOnly: false },
  { label: 'Projects',      href: '/admin/projects',      adminOnly: false },
  { label: 'GitHub',        href: '/admin/github',        adminOnly: false },
  { label: 'Users',         href: '/admin/users',         adminOnly: true  },
  { label: 'Settings',      href: '/admin/settings',      adminOnly: true  },
  { label: 'Integrations',  href: '/admin/integrations',  adminOnly: true  },
]

const nav = computed(() => allNav.filter(item => !item.adminOnly || isAdmin.value))

const isActive = (href) => page.url.startsWith(href)
</script>

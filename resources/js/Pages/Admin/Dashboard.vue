<template>
  <AdminLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
        <p class="text-sm text-slate-500">{{ today }}</p>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Pages"         :value="stats.pages"        href="/admin/pages" />
        <StatCard label="Service Cards" :value="stats.serviceCards" href="/admin/service-cards" />
        <StatCard label="Open Tasks"    :value="stats.openTasks"    href="/admin/tasks?status=open" color="amber" />
        <StatCard label="Overdue"       :value="stats.overdueTasks" href="/admin/tasks?overdue=1" color="red" />
        <StatCard v-if="stats.users !== null" label="Users" :value="stats.users" href="/admin/users" />
        <StatCard v-if="stats.unreadMessages" label="New Messages" :value="stats.unreadMessages" color="orange" />
      </div>

      <!-- Contact form submissions -->
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">
            Contact Messages
            <span v-if="unreadCount" class="ml-1.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-orange-500 text-white text-xs font-bold">{{ unreadCount }}</span>
          </h2>
        </div>
        <div v-if="!localContacts.length" class="px-5 py-8 text-sm text-slate-400 text-center">No messages yet.</div>
        <ul class="divide-y divide-slate-100">
          <li v-for="msg in localContacts" :key="msg.id"
            class="px-5 py-4 transition-colors"
            :class="msg.read ? 'bg-white' : 'bg-orange-50'">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 mb-0.5">
                  <span class="text-sm font-semibold text-slate-800">{{ msg.name }}</span>
                  <span class="text-xs text-slate-400">{{ msg.email }}</span>
                  <span v-if="!msg.read" class="text-xs bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded-full font-medium">New</span>
                </div>
                <p class="text-sm text-slate-600 leading-relaxed line-clamp-2">{{ msg.message }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ timeAgo(msg.created_at) }}</p>
              </div>
              <button v-if="!msg.read" @click="markRead(msg)"
                class="text-xs text-slate-400 hover:text-slate-700 shrink-0 mt-0.5 transition-colors">
                Mark read
              </button>
            </div>
          </li>
        </ul>
      </div>

      <!-- Recent Tasks -->
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-slate-700">Recent Tasks</h2>
          <Link href="/admin/tasks" class="text-xs text-blue-500 hover:text-blue-700">See all →</Link>
        </div>
        <div v-if="recentTasks.length === 0" class="text-sm text-slate-400">No tasks yet.</div>
        <ul class="space-y-2">
          <li v-for="task in recentTasks" :key="task.id" class="flex items-center gap-3 text-sm">
            <span :class="statusColor(task.status)" class="w-2 h-2 rounded-full flex-shrink-0" />
            <span class="flex-1 text-slate-700">{{ task.title }}</span>
            <span v-if="isOverdue(task)" class="text-xs font-medium text-red-500">Overdue</span>
            <span class="text-xs text-slate-400">{{ task.project?.name }}</span>
          </li>
        </ul>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatCard from '@/Components/Admin/StatCard.vue'

const props = defineProps({
  stats:       { type: Object, default: () => ({}) },
  recentTasks: { type: Array,  default: () => [] },
  contacts:    { type: Array,  default: () => [] },
})

const today = new Date().toLocaleDateString('en-US', {
  weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
})

// ── Contacts ──────────────────────────────────────────────────────────────────
const localContacts = ref(props.contacts.map(c => ({ ...c })))
const unreadCount   = computed(() => localContacts.value.filter(c => !c.read).length)

const markRead = (msg) => {
  router.patch(`/admin/dashboard/contacts/${msg.id}/read`, {}, {
    preserveScroll: true,
    onSuccess: () => { msg.read = true },
  })
}

const timeAgo = (iso) => {
  const diff = Date.now() - new Date(iso)
  const m = Math.floor(diff / 60000)
  if (m < 1)  return 'just now'
  if (m < 60) return `${m}m ago`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h}h ago`
  return `${Math.floor(h / 24)}d ago`
}

// ── Tasks ─────────────────────────────────────────────────────────────────────
const statusColor = s => ({
  open:        'bg-amber-400',
  in_progress: 'bg-blue-400',
  done:        'bg-green-400',
  closed:      'bg-slate-300',
}[s] ?? 'bg-slate-300')

const isOverdue = (task) =>
  task.due_date && !['done', 'closed'].includes(task.status) && new Date(task.due_date) < new Date(new Date().toDateString())
</script>

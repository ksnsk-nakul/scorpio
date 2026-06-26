<template>
  <AdminLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
        <p class="text-sm text-slate-500">{{ today }}</p>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Pages"         :value="stats.pages" />
        <StatCard label="Service Cards" :value="stats.serviceCards" />
        <StatCard label="Open Tasks"    :value="stats.openTasks" color="amber" />
        <StatCard label="Users"         :value="stats.users" />
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Recent Tasks</h2>
        <div v-if="recentTasks.length === 0" class="text-sm text-slate-400">No tasks yet.</div>
        <ul class="space-y-2">
          <li v-for="task in recentTasks" :key="task.id" class="flex items-center gap-3 text-sm">
            <span :class="statusColor(task.status)" class="w-2 h-2 rounded-full flex-shrink-0" />
            <span class="flex-1 text-slate-700">{{ task.title }}</span>
            <span class="text-xs text-slate-400">{{ task.project?.name }}</span>
          </li>
        </ul>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatCard from '@/Components/Admin/StatCard.vue'

defineProps({ stats: Object, recentTasks: Array })

const today = new Date().toLocaleDateString('en-US', {
  weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
})

const statusColor = s => ({
  open:        'bg-amber-400',
  in_progress: 'bg-blue-400',
  done:        'bg-green-400',
  closed:      'bg-slate-300',
}[s] ?? 'bg-slate-300')
</script>

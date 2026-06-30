<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Tasks</h1>
      <Link href="/admin/products" class="text-sm text-blue-600 hover:underline">View by Product →</Link>
    </div>

    <!-- Filters + view toggle -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex gap-3">
        <select v-model="filter.status" @change="applyFilters"
          class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none">
          <option value="">All Statuses</option>
          <option v-for="s in ['open','in_progress','done','closed']" :key="s" :value="s">{{ s }}</option>
        </select>
        <select v-model="filter.priority" @change="applyFilters"
          class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none">
          <option value="">All Priorities</option>
          <option v-for="p in ['low','medium','high']" :key="p" :value="p">{{ p }}</option>
        </select>
      </div>
      <div class="flex gap-1 bg-slate-100 rounded-lg p-1">
        <button @click="setView('list')"
          class="text-xs font-medium px-3 py-1.5 rounded transition"
          :class="view === 'list' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-400'">List</button>
        <button @click="setView('board')"
          class="text-xs font-medium px-3 py-1.5 rounded transition"
          :class="view === 'board' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-400'">Board</button>
      </div>
    </div>

    <!-- List view -->
    <div v-if="view === 'list'" class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <ul class="divide-y divide-slate-100">
        <li v-for="task in tasks.data" :key="task.id" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
          <span :class="statusBadge(task.status)" class="text-xs px-2 py-0.5 rounded-full">{{ task.status }}</span>
          <Link :href="`/admin/tasks/${task.id}`" class="flex-1 text-sm text-slate-700 hover:underline">{{ task.title }}</Link>
          <span v-if="task.subtasks_count" class="text-xs text-slate-400">{{ task.done_subtasks_count }}/{{ task.subtasks_count }} subtasks</span>
          <span v-if="isOverdue(task)" class="text-xs font-medium text-red-500">Overdue</span>
          <span class="text-xs text-slate-400">{{ task.project?.name }}</span>
        </li>
        <li v-if="tasks.data.length === 0" class="px-5 py-8 text-center text-slate-400 text-sm">No tasks found.</li>
      </ul>
    </div>

    <!-- Board view -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="col in columns" :key="col.status"
        class="bg-slate-50 rounded-xl p-3"
        @dragover.prevent
        @drop="onDrop(col.status)">
        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3 px-1">
          {{ col.label }} <span class="text-slate-300">({{ tasksByStatus(col.status).length }})</span>
        </h3>
        <div class="space-y-2">
          <div v-for="task in tasksByStatus(col.status)" :key="task.id"
            draggable="true"
            @dragstart="dragTask = task"
            class="bg-white rounded-lg border border-slate-200 p-3 cursor-grab active:cursor-grabbing shadow-sm">
            <Link :href="`/admin/tasks/${task.id}`" class="text-sm font-medium text-slate-700 hover:underline block mb-1">
              {{ task.title }}
            </Link>
            <div class="flex items-center gap-2 text-xs text-slate-400">
              <span :class="priorityDot(task.priority)" class="w-1.5 h-1.5 rounded-full" />
              <span>{{ task.project?.name }}</span>
              <span v-if="task.subtasks_count" class="ml-auto">{{ task.done_subtasks_count }}/{{ task.subtasks_count }}</span>
              <span v-if="isOverdue(task)" class="text-red-500 font-medium">Overdue</span>
            </div>
          </div>
          <p v-if="tasksByStatus(col.status).length === 0" class="text-xs text-slate-300 text-center py-4">No tasks</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ tasks: [Object, Array], projects: Array, filters: Object, view: { type: String, default: 'list' } })

const filter = reactive({ status: props.filters?.status ?? '', priority: props.filters?.priority ?? '' })

const applyFilters = () => router.get('/admin/tasks', { ...filter, view: props.view }, { preserveState: true, replace: true })
const setView = (v) => router.get('/admin/tasks', { ...filter, view: v }, { preserveState: true, replace: true })

const statusBadge = s => ({
  open: 'bg-amber-100 text-amber-700',
  in_progress: 'bg-blue-100 text-blue-700',
  done: 'bg-green-100 text-green-700',
  closed: 'bg-slate-100 text-slate-500',
}[s] ?? 'bg-slate-100 text-slate-500')

const priorityDot = p => ({
  high: 'bg-red-400', medium: 'bg-amber-400', low: 'bg-slate-300',
}[p] ?? 'bg-slate-300')

const isOverdue = (task) =>
  task.due_date && !['done', 'closed'].includes(task.status) && new Date(task.due_date) < new Date(new Date().toDateString())

// --- Board view ---
const columns = [
  { status: 'open',        label: 'Open' },
  { status: 'in_progress', label: 'In Progress' },
  { status: 'done',        label: 'Done' },
  { status: 'closed',      label: 'Closed' },
]

const tasksByStatus = (status) => (Array.isArray(props.tasks) ? props.tasks : []).filter(t => t.status === status)

const dragTask = ref(null)
const onDrop = (status) => {
  if (!dragTask.value || dragTask.value.status === status) return
  const task = dragTask.value
  dragTask.value = null
  router.patch(`/admin/tasks/${task.id}/status`, { status }, {
    preserveScroll: true,
    preserveState: true,
    only: ['tasks'],
  })
}
</script>

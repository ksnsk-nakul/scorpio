<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Tasks</h1>
      <Link href="/admin/products" class="text-sm text-blue-600 hover:underline">View by Product →</Link>
    </div>

    <!-- Filters -->
    <div class="flex gap-3 mb-4">
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

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <ul class="divide-y divide-slate-100">
        <li v-for="task in tasks.data" :key="task.id" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
          <span :class="statusBadge(task.status)" class="text-xs px-2 py-0.5 rounded-full">{{ task.status }}</span>
          <Link :href="`/admin/tasks/${task.id}`" class="flex-1 text-sm text-slate-700 hover:underline">{{ task.title }}</Link>
          <span class="text-xs text-slate-400">{{ task.project?.name }}</span>
        </li>
        <li v-if="tasks.data.length === 0" class="px-5 py-8 text-center text-slate-400 text-sm">No tasks found.</li>
      </ul>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ tasks: Object, projects: Array, filters: Object })

const filter = reactive({ status: props.filters?.status ?? '', priority: props.filters?.priority ?? '' })

const applyFilters = () => router.get('/admin/tasks', filter, { preserveState: true, replace: true })

const statusBadge = s => ({
  open: 'bg-amber-100 text-amber-700',
  in_progress: 'bg-blue-100 text-blue-700',
  done: 'bg-green-100 text-green-700',
  closed: 'bg-slate-100 text-slate-500',
}[s] ?? 'bg-slate-100 text-slate-500')
</script>

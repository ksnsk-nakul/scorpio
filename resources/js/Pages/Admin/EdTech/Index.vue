<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">EdTech Courses</h1>

      <table class="w-full text-sm border border-slate-100 rounded-xl overflow-hidden">
        <thead class="bg-slate-50 text-left text-xs text-slate-500 uppercase">
          <tr>
            <th class="px-4 py-2">Code</th>
            <th class="px-4 py-2">Title</th>
            <th class="px-4 py-2">Status</th>
            <th class="px-4 py-2">Modules</th>
            <th class="px-4 py-2">Topics</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="course in courses.data" :key="course.id">
            <td class="px-4 py-2 font-mono text-xs">{{ course.code }}</td>
            <td class="px-4 py-2">{{ course.title }}</td>
            <td class="px-4 py-2">
              <span class="px-2 py-0.5 rounded-full text-xs" :class="statusBadge(course.status)">{{ course.status }}</span>
            </td>
            <td class="px-4 py-2">{{ course.module_count }}</td>
            <td class="px-4 py-2">{{ course.topic_count }}</td>
            <td class="px-4 py-2">
              <button @click="reimport(course.id)" class="text-xs text-orange-500 hover:underline">Re-import</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

defineProps({ courses: { type: Object, required: true } })

const statusBadge = (status) => ({
  ready: 'bg-green-50 text-green-700',
  pending: 'bg-slate-100 text-slate-600',
  importing: 'bg-blue-50 text-blue-700',
  failed: 'bg-red-50 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600')

const reimport = async (id) => {
  await axios.post(`/admin/edtech/courses/${id}/reimport`)
  router.reload({ only: ['courses'] })
}
</script>

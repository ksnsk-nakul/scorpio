<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">Enrollments</h1>

      <table class="w-full text-sm border border-slate-100 rounded-xl overflow-hidden">
        <thead class="bg-slate-50 text-left text-xs text-slate-500 uppercase">
          <tr>
            <th class="px-4 py-2">Learner</th>
            <th class="px-4 py-2">Course</th>
            <th class="px-4 py-2">Tier</th>
            <th class="px-4 py-2">Amount</th>
            <th class="px-4 py-2">Enrolled</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="e in enrollments.data" :key="e.id">
            <td class="px-4 py-2">{{ e.learner_name }}</td>
            <td class="px-4 py-2">{{ e.course_title }}</td>
            <td class="px-4 py-2">{{ e.tier_name }}</td>
            <td class="px-4 py-2">₹{{ (e.amount_paise / 100).toLocaleString('en-IN') }}</td>
            <td class="px-4 py-2 text-xs text-slate-500">{{ e.enrolled_at }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ enrollments: { type: Object, required: true } })
</script>

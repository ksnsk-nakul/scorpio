<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Workspaces</h1>
      <button @click="showCreate = true"
        class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">+ New Workspace</button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div v-for="ws in workspaces" :key="ws.id" class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="font-semibold text-slate-800">{{ ws.name }}</h2>
        <p class="text-xs text-slate-400 mt-1 mb-3">{{ ws.description }}</p>
        <p class="text-xs text-slate-500">{{ ws.projects_count }} projects</p>
        <Link :href="`/admin/products?workspace=${ws.id}`" class="text-xs text-blue-600 hover:underline mt-3 block">
          View Products →
        </Link>
      </div>
      <div v-if="workspaces.length === 0" class="col-span-3 text-center text-slate-400 py-12 text-sm">
        No workspaces yet.
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-80 shadow-xl">
          <h2 class="font-semibold mb-4 text-slate-800">New Workspace</h2>
          <input v-model="form.name" placeholder="Name"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none" />
          <textarea v-model="form.description" placeholder="Description (optional)"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-4 outline-none resize-none min-h-16" />
          <div class="flex gap-2 justify-end">
            <button @click="showCreate = false" class="px-4 py-2 text-sm text-slate-500">Cancel</button>
            <button @click="form.post('/admin/workspaces', { onSuccess: () => showCreate = false })"
              :disabled="form.processing"
              class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg">Create</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ workspaces: Array })
const showCreate = ref(false)
const form = useForm({ name: '', description: '' })
</script>

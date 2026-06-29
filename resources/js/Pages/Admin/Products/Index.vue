<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Products</h1>
      <button @click="showCreate = true"
        class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">+ New Product</button>
    </div>

    <div v-for="ws in workspaces" :key="ws.id" class="mb-8">
      <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ ws.name }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Link
          v-for="p in ws.projects"
          :key="p.id"
          :href="`/admin/products/${p.id}`"
          class="bg-white border border-slate-200 rounded-xl p-5 hover:border-blue-300 transition block"
        >
          <div class="flex items-start justify-between mb-2">
            <h3 class="font-semibold text-slate-800 text-sm">{{ p.name }}</h3>
            <span :class="p.status === 'active' ? 'text-green-600' : 'text-slate-400'"
              class="text-xs">{{ p.status }}</span>
          </div>
          <p v-if="p.github_repo" class="text-xs text-slate-400">🐙 {{ p.github_repo }}</p>
        </Link>
        <div v-if="ws.projects.length === 0" class="text-xs text-slate-400">No products in this workspace.</div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-80 shadow-xl">
          <h2 class="font-semibold mb-4 text-slate-800">New Product</h2>
          <select v-model="form.workspace_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none">
            <option :value="null">— Select workspace —</option>
            <option v-for="ws in workspaces" :key="ws.id" :value="ws.id">{{ ws.name }}</option>
          </select>
          <input v-model="form.name" placeholder="Product name"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-4 outline-none" />
          <div class="flex gap-2 justify-end">
            <button @click="showCreate = false" class="px-4 py-2 text-sm text-slate-500">Cancel</button>
            <button @click="form.post('/admin/products', { onSuccess: () => showCreate = false })"
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
const form = useForm({ workspace_id: null, name: '' })
</script>

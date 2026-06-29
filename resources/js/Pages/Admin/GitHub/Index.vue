<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">GitHub</h1>

      <div v-if="!hasToken" class="bg-white border border-slate-200 rounded-xl p-6 mb-6">
        <h2 class="font-semibold text-slate-800 mb-1">Connect GitHub</h2>
        <p class="text-sm text-slate-500 mb-4">Enter a personal access token with <code>repo</code> scope.</p>
        <form @submit.prevent="connectForm.post('/admin/github/token')">
          <input v-model="connectForm.token" type="password" placeholder="ghp_..."
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none" />
          <button type="submit" :disabled="connectForm.processing"
            class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">Connect</button>
        </form>
      </div>

      <div v-else class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center justify-between">
        <span class="text-sm text-green-800">GitHub connected</span>
        <Link href="/admin/github/token" method="delete" as="button"
          class="text-xs text-red-500 hover:text-red-700">Disconnect</Link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800 text-sm">Repositories ({{ repos.length }})</h2>
          </div>
          <ul class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
            <li v-for="repo in repos" :key="repo.id" class="px-5 py-3">
              <p class="text-sm font-medium text-slate-800">{{ repo.full_name }}</p>
              <p class="text-xs text-slate-400 mt-0.5">{{ repo.description }}</p>
            </li>
            <li v-if="repos.length === 0" class="px-5 py-8 text-center text-sm text-slate-400">
              {{ hasToken ? 'No repositories found.' : 'Connect GitHub to see repos.' }}
            </li>
          </ul>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800 text-sm">Linked Projects</h2>
          </div>
          <ul class="divide-y divide-slate-100">
            <li v-for="p in projects" :key="p.id" class="px-5 py-3 flex items-center gap-3">
              <div class="flex-1">
                <p class="text-sm font-medium text-slate-800">{{ p.name }}</p>
                <p class="text-xs text-slate-400">{{ p.github_repo }}</p>
              </div>
              <Link :href="`/admin/github/projects/${p.id}/sync`" method="post" as="button"
                class="text-xs bg-slate-100 text-slate-600 rounded px-2 py-1 hover:bg-slate-200">
                Sync Issues
              </Link>
            </li>
            <li v-if="projects.length === 0" class="px-5 py-8 text-center text-sm text-slate-400">
              No projects linked to GitHub repos.
            </li>
          </ul>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ repos: Array, projects: Array, hasToken: Boolean })

const connectForm = useForm({ token: '' })
</script>

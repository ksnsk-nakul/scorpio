<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">GitHub</h1>

      <div v-if="!hasToken" class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6 text-sm text-amber-800">
        No GitHub token configured. Connect your account on the
        <Link href="/admin/github" class="font-medium underline">GitHub</Link>
        page.
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
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ repos: Array, projects: Array, hasToken: Boolean })
</script>

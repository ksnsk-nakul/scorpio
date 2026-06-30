<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">GitHub</h1>

      <div v-if="!hasToken" class="bg-white border border-slate-200 rounded-xl p-6 mb-6">
        <h2 class="font-semibold text-slate-800 mb-1">Connect GitHub</h2>
        <p class="text-sm text-slate-500 mb-5">Authorise Scorpio to access your repositories.</p>

        <!-- OAuth (primary) -->
        <a href="/auth/github"
          class="flex items-center justify-center gap-2 w-full bg-slate-900 text-white text-sm font-medium py-2.5 rounded-lg hover:bg-slate-700 transition mb-4">
          <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
          Connect with GitHub
        </a>

        <!-- Divider -->
        <div class="flex items-center gap-3 mb-4">
          <div class="flex-1 border-t border-slate-100" />
          <span class="text-xs text-slate-400">or use a personal access token</span>
          <div class="flex-1 border-t border-slate-100" />
        </div>

        <!-- PAT fallback -->
        <form @submit.prevent="connectForm.post('/admin/github/token')">
          <input v-model="connectForm.token" type="password" placeholder="ghp_..."
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none" />
          <p v-if="connectForm.errors.token" class="text-xs text-red-500 mb-2">{{ connectForm.errors.token }}</p>
          <button type="submit" :disabled="connectForm.processing"
            class="bg-slate-100 text-slate-700 text-sm px-4 py-2 rounded-lg hover:bg-slate-200">Save Token</button>
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

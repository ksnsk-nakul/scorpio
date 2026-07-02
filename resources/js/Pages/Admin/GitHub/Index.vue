<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">GitHub</h1>
        <button v-if="hasToken" @click="refresh" :disabled="refreshing"
          class="flex items-center gap-1.5 text-xs px-3 py-1.5 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition-colors">
          <svg class="w-3.5 h-3.5 transition-transform duration-500" :class="refreshing ? 'animate-spin' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          {{ refreshing ? 'Refreshing…' : 'Refresh' }}
        </button>
      </div>

      <!-- Not connected -->
      <div v-if="!hasToken" class="bg-white border border-slate-200 rounded-xl p-6 mb-6">
        <h2 class="font-semibold text-slate-800 mb-1">Connect GitHub</h2>
        <p class="text-sm text-slate-500 mb-5">Authorise to access your repositories.</p>

        <template v-if="hasOAuthClient">
          <a href="/auth/github"
            class="flex items-center justify-center gap-2 w-full bg-slate-900 text-white text-sm font-medium py-2.5 rounded-lg hover:bg-slate-700 transition mb-4">
            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
            Connect with GitHub
          </a>
          <div class="flex items-center gap-3 mb-4">
            <div class="flex-1 border-t border-slate-100" />
            <span class="text-xs text-slate-400">or use a personal access token</span>
            <div class="flex-1 border-t border-slate-100" />
          </div>
        </template>
        <template v-else>
          <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-4 text-xs text-amber-800">
            GitHub OAuth is not configured. Set <code class="font-mono">GITHUB_CLIENT_ID</code> and
            <code class="font-mono">GITHUB_CLIENT_SECRET</code> in <code class="font-mono">.env</code>.
            Use a personal access token below instead.
          </div>
        </template>

        <form @submit.prevent="connectForm.post('/admin/github/token')">
          <input v-model="connectForm.token" type="password" placeholder="ghp_..."
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none" />
          <p v-if="connectForm.errors.token" class="text-xs text-red-500 mb-2">{{ connectForm.errors.token }}</p>
          <button type="submit" :disabled="connectForm.processing"
            class="bg-slate-100 text-slate-700 text-sm px-4 py-2 rounded-lg hover:bg-slate-200">Save Token</button>
        </form>
      </div>

      <!-- Connected banner -->
      <div v-else class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
          <span class="text-sm text-green-800 font-medium">GitHub connected</span>
          <span v-if="lastRefreshed" class="text-xs text-green-600 opacity-70">· refreshed {{ lastRefreshed }}</span>
        </div>
        <Link href="/admin/github/token" method="delete" as="button"
          class="text-xs text-red-500 hover:text-red-700 transition-colors">Disconnect</Link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Repositories -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800 text-sm">
              Repositories
              <span class="text-slate-400 font-normal">({{ repos.length }})</span>
            </h2>
            <input v-if="repos.length" v-model="repoSearch" placeholder="Search…"
              class="text-xs border border-slate-200 rounded-lg px-2 py-1 outline-none w-32 focus:w-44 transition-all" />
          </div>
          <ul class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
            <li v-for="repo in filteredRepos" :key="repo.id" class="px-5 py-3 group">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <a :href="repo.html_url" target="_blank" rel="noopener"
                    class="text-sm font-medium text-slate-800 hover:text-orange-600 transition-colors truncate block">
                    {{ repo.full_name }}
                  </a>
                  <p class="text-xs text-slate-400 mt-0.5 truncate">{{ repo.description }}</p>
                </div>
                <div class="flex items-center gap-1.5 shrink-0 mt-0.5">
                  <span v-if="repo.language" class="text-xs bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded">{{ repo.language }}</span>
                  <span v-if="repo.private" class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">private</span>
                  <span v-if="linkedRepoNames.has(repo.full_name.toLowerCase())"
                    class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">linked</span>
                  <button v-else @click="addAsProduct(repo)"
                    :disabled="linking === repo.full_name"
                    class="text-xs bg-orange-50 text-orange-600 border border-orange-200 hover:bg-orange-100 px-2 py-0.5 rounded transition-colors disabled:opacity-50">
                    {{ linking === repo.full_name ? '…' : '+ Product' }}
                  </button>
                </div>
              </div>
            </li>
            <li v-if="!repos.length" class="px-5 py-8 text-center text-sm text-slate-400">
              <div class="text-2xl mb-2">📭</div>
              {{ hasToken ? 'No repositories found.' : 'Connect GitHub to see repos.' }}
            </li>
            <li v-else-if="!filteredRepos.length" class="px-5 py-8 text-center text-sm text-slate-400">
              No repos matching "{{ repoSearch }}"
            </li>
          </ul>
        </div>

        <!-- Linked Products -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800 text-sm">
              Linked Products
              <span class="text-slate-400 font-normal">({{ projects.length }})</span>
            </h2>
          </div>
          <ul class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
            <li v-for="p in projects" :key="p.id" class="px-5 py-3">
              <div class="flex items-center gap-3 mb-1">
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-slate-800 truncate">{{ p.name }}</p>
                  <a :href="`https://github.com/${p.github_repo}`" target="_blank" rel="noopener"
                    class="text-xs text-slate-400 hover:text-orange-600 transition-colors truncate block">
                    {{ p.github_repo }}
                  </a>
                </div>
                <div class="flex gap-1.5 shrink-0">
                  <Link :href="`/admin/github/projects/${p.id}/sync`" method="post" as="button"
                    class="text-xs bg-slate-100 text-slate-600 rounded-lg px-2.5 py-1 hover:bg-slate-200 transition-colors">
                    Sync
                  </Link>
                  <Link :href="`/admin/github/projects/${p.id}/webhook`" method="post" as="button"
                    class="text-xs bg-slate-100 text-slate-600 rounded-lg px-2.5 py-1 hover:bg-slate-200 transition-colors">
                    Webhook
                  </Link>
                </div>
              </div>

              <!-- Webhook credentials panel -->
              <div v-if="webhookFor === p.id" class="bg-slate-50 rounded-xl p-3 mt-2 text-xs space-y-2">
                <p class="text-slate-500">
                  Add this webhook in repo Settings → Webhooks with content type
                  <code class="bg-slate-200 px-1 rounded">application/json</code>
                  and event <code class="bg-slate-200 px-1 rounded">Issues</code>:
                </p>
                <div>
                  <p class="text-slate-400 mb-0.5 font-medium">Payload URL</p>
                  <div class="flex items-center gap-1">
                    <code class="flex-1 block bg-white border border-slate-200 rounded px-2 py-1 break-all text-slate-700">{{ webhookUrl }}</code>
                    <button @click="copy(webhookUrl)" class="text-slate-400 hover:text-slate-700 px-1">📋</button>
                  </div>
                </div>
                <div>
                  <p class="text-slate-400 mb-0.5 font-medium">Secret</p>
                  <div class="flex items-center gap-1">
                    <code class="flex-1 block bg-white border border-slate-200 rounded px-2 py-1 break-all text-slate-700">{{ webhookSecret }}</code>
                    <button @click="copy(webhookSecret)" class="text-slate-400 hover:text-slate-700 px-1">📋</button>
                  </div>
                </div>
                <p class="text-amber-600 font-medium">⚠ This secret is only shown once — copy it now.</p>
              </div>
            </li>
            <li v-if="!projects.length" class="px-5 py-8 text-center text-sm text-slate-400">
              <div class="text-2xl mb-2">🔗</div>
              No products linked to GitHub repos.
            </li>
          </ul>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  repos:          { type: Array, default: () => [] },
  projects:       { type: Array, default: () => [] },
  hasToken:       Boolean,
  hasOAuthClient: Boolean,
})

const connectForm = useForm({ token: '' })

const { props: pageProps } = usePage()
const webhookFor    = computed(() => pageProps.flash?.webhook_project_id ?? null)
const webhookUrl    = computed(() => pageProps.flash?.webhook_url ?? '')
const webhookSecret = computed(() => pageProps.flash?.webhook_secret ?? '')

// ── Refresh ───────────────────────────────────────────────────────────────────
const refreshing    = ref(false)
const lastRefreshed = ref(null)

const refresh = () => {
  refreshing.value = true
  router.reload({
    only: ['repos', 'projects'],
    onFinish: () => {
      refreshing.value = false
      lastRefreshed.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    },
  })
}

// Auto-refresh once on mount when connected
onMounted(() => {
  if (props.hasToken) refresh()
})

// ── Repo search ───────────────────────────────────────────────────────────────
const repoSearch    = ref('')
const filteredRepos = computed(() =>
  repoSearch.value.trim()
    ? props.repos.filter(r => r.full_name.toLowerCase().includes(repoSearch.value.toLowerCase()))
    : props.repos
)

// ── Already-linked repo names (for "linked" badge) ────────────────────────────
const linkedRepoNames = computed(() =>
  new Set(props.projects.map(p => p.github_repo?.toLowerCase()).filter(Boolean))
)

// ── Add repo as Product ───────────────────────────────────────────────────────
const linking = ref(null)
const addAsProduct = (repo) => {
  linking.value = repo.full_name
  router.post('/admin/github/repos/link', { full_name: repo.full_name, description: repo.description ?? '' }, {
    preserveScroll: true,
    onFinish: () => { linking.value = null },
    onSuccess: () => refresh(),
  })
}

// ── Copy to clipboard ─────────────────────────────────────────────────────────
const copy = (text) => navigator.clipboard?.writeText(text)
</script>

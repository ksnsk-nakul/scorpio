<template>
  <Head>
    <title>{{ pageTitle }}</title>
    <meta name="description" :content="pageDescription" />
    <meta property="og:title" :content="pageTitle" />
    <meta property="og:description" :content="pageDescription" />
    <meta property="og:type" content="profile" />
    <meta v-if="settings.og_image" property="og:image" :content="settings.og_image" />
    <meta name="twitter:card" :content="settings.og_image ? 'summary_large_image' : 'summary'" />
    <meta name="twitter:title" :content="pageTitle" />
    <meta name="twitter:description" :content="pageDescription" />
    <meta v-if="settings.og_image" name="twitter:image" :content="settings.og_image" />
  </Head>

  <div class="min-h-screen bg-white text-slate-900 font-sans">

    <!-- Nav -->
    <nav class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur border-b border-slate-100">
      <div class="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
        <span class="font-semibold text-slate-800 tracking-tight">
          {{ settings.site_name || owner.name }}
        </span>
        <a
          v-if="isAdmin"
          href="/admin/dashboard"
          class="text-sm px-4 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-700 transition-colors"
        >
          Dashboard →
        </a>
      </div>
    </nav>

    <!-- Page content -->
    <main class="pt-14">
      <template v-if="page" v-for="block in (page.blocks ?? [])" :key="block.order">

        <!-- Hero -->
        <section v-if="block.type === 'hero'" class="py-28 px-6 text-center max-w-3xl mx-auto">
          <h1 class="text-5xl font-bold leading-tight tracking-tight text-slate-900 mb-4">
            {{ block.data.heading }}
          </h1>
          <p class="text-xl text-slate-500">{{ block.data.subheading }}</p>
        </section>

        <!-- Text -->
        <section v-else-if="block.type === 'text'" class="py-16 px-6 max-w-3xl mx-auto prose prose-slate">
          <p class="whitespace-pre-line">{{ block.data.content }}</p>
        </section>

        <!-- Text + Image -->
        <section v-else-if="block.type === 'text_image'" class="py-16 px-6 max-w-5xl mx-auto flex flex-col md:flex-row gap-12 items-center">
          <div class="flex-1 prose prose-slate">
            <p class="whitespace-pre-line">{{ block.data.text }}</p>
          </div>
          <div v-if="block.data.image" class="flex-1">
            <img :src="block.data.image" :alt="block.data.alt ?? ''" class="rounded-2xl shadow-md w-full object-cover" />
          </div>
        </section>

        <!-- Service Cards -->
        <section v-else-if="block.type === 'service_cards'" class="py-16 px-6 max-w-5xl mx-auto">
          <div v-if="block.data.heading" class="text-center mb-10">
            <h2 class="text-3xl font-bold text-slate-900">{{ block.data.heading }}</h2>
          </div>
          <div v-if="page.service_cards?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
              v-for="card in page.service_cards"
              :key="card.id"
              class="rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition-shadow"
            >
              <div v-if="card.icon" class="text-3xl mb-3">{{ card.icon }}</div>
              <h3 class="font-semibold text-slate-900 mb-2">{{ card.title }}</h3>
              <p class="text-sm text-slate-500">{{ card.description }}</p>
            </div>
          </div>
        </section>

        <!-- Project Grid -->
        <section v-else-if="block.type === 'project_grid'" class="py-16 px-6 max-w-5xl mx-auto">
          <div v-if="block.data.heading" class="text-center mb-10">
            <h2 class="text-3xl font-bold text-slate-900">{{ block.data.heading }}</h2>
          </div>
          <!-- DB workspace products (when workspace linked) -->
          <div v-if="block.data.workspace_id && workspaces[block.data.workspace_id]?.projects?.length"
            class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <a v-for="project in workspaces[block.data.workspace_id].projects"
              :key="project.id"
              :href="project.github_repo ? `https://github.com/${project.github_repo}` : '#'"
              :target="project.github_repo ? '_blank' : '_self'"
              rel="noopener"
              class="group rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition-shadow block">
              <h3 class="font-semibold text-slate-900 group-hover:text-blue-600 transition-colors mb-1">{{ project.name }}</h3>
              <p class="text-sm text-slate-500">{{ project.description }}</p>
              <span v-if="project.github_repo" class="text-xs text-slate-400 mt-2 block">🐙 {{ project.github_repo }}</span>
            </a>
          </div>
          <!-- Inline JSON fallback -->
          <div v-else-if="block.data.projects?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <a v-for="project in block.data.projects"
              :key="project.id ?? project.title"
              :href="project.url ?? '#'"
              target="_blank"
              class="group rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition-shadow block">
              <h3 class="font-semibold text-slate-900 group-hover:text-blue-600 transition-colors mb-1">{{ project.title }}</h3>
              <p class="text-sm text-slate-500">{{ project.description }}</p>
            </a>
          </div>
        </section>

        <!-- Contact Form -->
        <section v-else-if="block.type === 'contact_form'" class="py-16 px-6 max-w-2xl mx-auto">
          <h2 class="text-3xl font-bold text-slate-900 text-center mb-10">
            {{ block.data.heading ?? 'Get in touch' }}
          </h2>
          <div class="flex flex-col md:flex-row gap-10">
            <!-- Contact details -->
            <div class="md:w-48 flex-shrink-0 space-y-4 text-sm text-slate-600">
              <div v-if="block.data.email">
                <p class="text-xs text-slate-400 mb-0.5">Email</p>
                <a :href="`mailto:${block.data.email}`" class="text-slate-800 hover:text-blue-600 break-all">{{ block.data.email }}</a>
              </div>
              <div v-if="block.data.phone">
                <p class="text-xs text-slate-400 mb-0.5">Phone</p>
                <a :href="`tel:${block.data.phone}`" class="text-slate-800 hover:text-blue-600">{{ block.data.phone }}</a>
              </div>
              <div v-if="block.data.links?.length" class="space-y-1">
                <p class="text-xs text-slate-400 mb-0.5">Links</p>
                <a v-for="link in block.data.links" :key="link.label"
                  :href="link.url" target="_blank" rel="noopener"
                  class="block text-slate-800 hover:text-blue-600">
                  {{ link.label }} ↗
                </a>
              </div>
            </div>
            <!-- Form -->
            <form @submit.prevent class="flex-1 space-y-4">
              <input type="text" placeholder="Name" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
              <input type="email" placeholder="Email" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
              <textarea rows="4" placeholder="Message" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-slate-900 resize-none" />
              <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-xl text-sm hover:bg-slate-700 transition-colors">
                Send message
              </button>
            </form>
          </div>
        </section>

      </template>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-100 py-8 text-center text-xs text-slate-400">
      {{ settings.site_name || owner.name }}
    </footer>
  </div>
</template>

<script setup>
import { usePage, Head } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  page:       { type: Object, default: () => ({}) },
  owner:      { type: Object, default: () => ({}) },
  settings:   { type: Object, default: () => ({}) },
  workspaces: { type: Object, default: () => ({}) },
  auth:       { type: Object, default: () => ({}) },
})

const { props: pageProps } = usePage()

const isAdmin = computed(() =>
  pageProps.auth?.roles?.includes('admin') ?? false
)

const pageTitle = computed(() => {
  const site = props.settings.site_name || props.owner.name
  return props.page.is_home ? site : `${props.page.name} — ${site}`
})

const pageDescription = computed(() => {
  const heroBlock = (props.page.blocks ?? []).find(b => b.type === 'hero')
  return heroBlock?.data?.subheading || `${props.owner.name}'s portfolio`
})
</script>

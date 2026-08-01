<template>
  <component
    v-if="activeTemplateComponent"
    :is="activeTemplateComponent"
    :organization="organization" :owner="owner" :members="members"
    :achievements="achievements" :settings="settings"
  />

  <template v-else>
  <Head><title>{{ organization.name }} · {{ settings.site_name }}</title></Head>
  <div class="min-h-screen bg-white font-sans">
    <nav class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
      <a href="/" class="text-sm font-bold text-slate-800 hover:text-orange-500 transition-colors">
        {{ organization.white_label && organization.custom_brand_name ? organization.custom_brand_name : settings.site_name }}
      </a>
    </nav>

    <main class="max-w-4xl mx-auto px-6 py-16">
      <div class="mb-12">
        <h1 class="text-4xl font-bold text-slate-900 mb-2">{{ organization.name }}</h1>
        <p v-if="organization.description" class="text-lg text-slate-500">{{ organization.description }}</p>
        <p class="text-sm text-slate-400 mt-2">
          Owned by
          <a :href="`/portfolio/${owner.username}`" class="text-orange-500 hover:underline">{{ owner.name }}</a>
        </p>
      </div>

      <section class="mb-12">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Team Members</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <a v-for="m in members" :key="m.id" :href="`/portfolio/${m.username}`"
            class="bg-slate-50 hover:bg-orange-50 border border-slate-100 hover:border-orange-200 rounded-xl p-4 transition-colors">
            <p class="font-semibold text-slate-800 text-sm">{{ m.name }}</p>
            <p class="text-xs text-slate-400 mt-0.5">@{{ m.username }} · {{ m.role }}</p>
          </a>
        </div>
        <p v-if="!members.length" class="text-slate-400 text-sm">No members listed.</p>
      </section>

      <section v-if="achievements.length">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Achievements</h2>
        <div class="space-y-3">
          <div v-for="a in achievements" :key="a.id"
            class="flex items-start gap-4 bg-slate-50 border border-slate-100 rounded-xl px-5 py-4">
            <span v-if="a.icon" class="text-3xl flex-shrink-0">{{ a.icon }}</span>
            <div>
              <p class="font-semibold text-slate-800">{{ a.title }}</p>
              <p class="text-xs text-slate-400 mt-0.5">
                <span v-if="a.user">{{ a.user.name }} · </span>
                {{ a.achieved_at ?? '' }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <footer v-if="!organization.white_label" class="mt-16 pt-8 border-t border-slate-100 text-center text-xs text-slate-400">
        Powered by <a href="/" class="hover:text-orange-500">{{ settings.site_name }}</a>
      </footer>
    </main>
  </div>
  </template>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useActiveTemplate } from '@/composables/useActiveTemplate'
import { resolvePublicPage } from '@/templateRegistry'

defineProps({
  organization: Object,
  owner:        Object,
  members:      Array,
  achievements: Array,
  settings:     Object,
})

// ── Template resolution ──────────────────────────────────────────────────────
const { publicTemplate } = useActiveTemplate()
const activeTemplateComponent = computed(() => resolvePublicPage(publicTemplate.value, 'OrgPage'))
</script>

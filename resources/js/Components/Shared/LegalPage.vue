<template>
  <div class="min-h-screen bg-white font-sans">
    <nav class="border-b border-slate-100 px-6 py-4">
      <BackLink href="/" :label="siteName" />
    </nav>
    <main class="max-w-3xl mx-auto px-6 py-16">
      <h1 class="text-4xl font-bold text-slate-900 mb-2">{{ title }}</h1>
      <p class="text-sm text-slate-400 mb-10">Last updated: {{ lastUpdated }}</p>
      <div class="prose prose-slate max-w-none space-y-8 text-slate-700 leading-relaxed">
        <slot />
      </div>
      <!-- Optional cross-link row below the content, e.g. Terms <-> Privacy links (see Privacy.vue/Terms.vue) -->
      <slot name="footer" />
    </main>
  </div>
</template>

<script setup>
import BackLink from '@/Components/Shared/BackLink.vue'

defineProps({
  siteName: { type: String, required: true },
  title: { type: String, required: true },
  lastUpdated: { type: String, required: true },
})
</script>

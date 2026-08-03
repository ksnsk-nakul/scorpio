<template>
  <Head><title>{{ topic.title }} · {{ course.title }}</title></Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a :href="`/courses/${course.slug}`" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← {{ course.title }}</a>
        <span class="text-sm font-semibold text-slate-800 truncate max-w-xs">{{ topic.title }}</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-3xl mx-auto px-6">
      <h1 class="text-xl font-bold text-slate-800 mb-6">{{ topic.title }}</h1>

      <div class="flex gap-1 border-b border-slate-100 mb-6">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="active = tab.key"
          class="px-3 py-2 text-sm border-b-2 transition-colors"
          :class="active === tab.key ? 'border-orange-500 text-slate-800 font-medium' : 'border-transparent text-slate-400 hover:text-slate-600'"
        >
          {{ tab.label }}
        </button>
      </div>

      <div v-if="activeMaterial?.status === 'ready'">
        <a
          v-if="activeMaterial.downloadable"
          :href="`/courses/${course.slug}/topics/${topic.slug}/materials/${activeMaterial.id}/download`"
          class="inline-block mb-4 text-xs text-orange-500 hover:underline"
        >
          Download
        </a>
        <div class="prose prose-sm max-w-none whitespace-pre-wrap">{{ activeMaterial.content }}</div>
      </div>
      <div v-else-if="activeMaterial?.status === 'not_generated'" class="text-sm text-slate-400 py-12 text-center">
        {{ active === 'video' ? 'Video coming soon.' : 'Not available yet.' }}
      </div>
      <div v-else-if="activeMaterial?.status === 'generating'" class="text-sm text-slate-400 py-12 text-center">
        Generating…
      </div>
      <div v-else class="text-sm text-slate-400 py-12 text-center">
        Not available for this topic.
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({ course: { type: Object, required: true }, topic: { type: Object, required: true } })

const tabs = [
  { key: 'notes', label: 'Notes' },
  { key: 'task', label: 'Task' },
  { key: 'demo_problem', label: 'Demo (Problem)' },
  { key: 'demo_try_it', label: 'Demo (Try It)' },
  { key: 'demo_solution', label: 'Demo (Solution)' },
  { key: 'slides', label: 'Slides' },
  { key: 'video', label: 'Video' },
]

const active = ref('notes')
const activeMaterial = computed(() => props.topic.materials[active.value] ?? null)
</script>

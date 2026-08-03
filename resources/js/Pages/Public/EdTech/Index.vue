<template>
  <Head><title>Courses</title></Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="/" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← Home</a>
        <span class="text-sm font-semibold text-slate-800">Courses</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-6xl mx-auto px-6">
      <h1 class="text-3xl font-bold text-slate-800 mb-8">Courses</h1>

      <div v-if="courses.data.length === 0" class="text-sm text-slate-400 py-12 text-center">
        No courses available yet.
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <Link v-for="course in courses.data" :key="course.slug" :href="`/courses/${course.slug}`"
          class="block p-5 rounded-xl border border-slate-100 hover:border-orange-200 hover:shadow-sm transition">
          <h2 class="text-base font-semibold text-slate-800">{{ course.title }}</h2>
          <p v-if="course.subtitle" class="text-sm text-slate-500 mt-1">{{ course.subtitle }}</p>
        </Link>
      </div>

      <div v-if="courses.last_page > 1" class="flex flex-wrap gap-1 mt-10 justify-center">
        <Link
          v-for="(link, i) in courses.links"
          :key="i"
          :href="link.url ?? '#'"
          v-html="link.label"
          class="px-3 py-1.5 text-sm rounded-lg"
          :class="link.active ? 'bg-orange-500 text-white' : link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 pointer-events-none'"
        />
      </div>
    </main>
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({ courses: { type: Object, required: true } })
</script>

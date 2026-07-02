<template>
  <Head>
    <title>{{ project.name }} · {{ settings.site_name ?? owner.name }}</title>
    <meta name="description" :content="project.description ?? project.name" />
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <!-- Nav -->
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a :href="`/portfolio/${owner.username}`" class="flex items-center gap-2 text-sm text-slate-500 hover:text-orange-500 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Back to Portfolio
        </a>
        <span class="text-sm font-semibold text-slate-800">{{ owner.name }}</span>
      </div>
    </nav>

    <main class="pt-14">
      <!-- Hero -->
      <section class="bg-slate-950 text-white py-20 px-6">
        <div class="max-w-4xl mx-auto">
          <div class="flex flex-wrap gap-2 mb-5">
            <span v-for="tag in (project.tags ?? [])" :key="tag"
              class="text-xs px-3 py-1 rounded-full bg-white/10 text-slate-300 font-medium">
              {{ tag }}
            </span>
          </div>
          <h1 class="text-4xl md:text-5xl font-bold mb-5 leading-tight">{{ project.name }}</h1>
          <p v-if="project.description" class="text-lg text-slate-300 leading-relaxed max-w-2xl mb-8">
            {{ project.description }}
          </p>
          <div class="flex flex-wrap gap-3">
            <a v-if="project.github_repo"
              :href="`https://github.com/${project.github_repo}`"
              target="_blank" rel="noopener"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-white/10 hover:bg-white/20 border border-white/20 rounded-xl text-sm font-medium transition-all">
              <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/>
              </svg>
              View on GitHub
            </a>
            <a v-if="project.demo_url"
              :href="project.demo_url"
              target="_blank" rel="noopener"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-orange-500 hover:bg-orange-400 rounded-xl text-sm font-medium transition-all">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
              </svg>
              Live Demo
            </a>
          </div>
        </div>
      </section>

      <!-- Gallery -->
      <section v-if="media.length" class="py-16 px-6 bg-slate-50">
        <div class="max-w-6xl mx-auto">
          <h2 class="text-xl font-bold text-slate-800 mb-6">Gallery</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="(item, i) in media" :key="item.id"
              class="group relative aspect-video rounded-2xl overflow-hidden bg-slate-200 cursor-pointer shadow-sm hover:shadow-lg transition-shadow"
              @click="openLightbox(i)">
              <img v-if="item.is_image" :src="item.url" :alt="item.alt_text || item.filename"
                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
              <div v-else class="w-full h-full flex items-center justify-center bg-slate-900">
                <svg class="w-10 h-10 text-white/60" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z"/>
                </svg>
              </div>
              <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-200 flex items-center justify-center">
                <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                </svg>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Details / About the project -->
      <section v-if="project.details" class="py-16 px-6">
        <div class="max-w-3xl mx-auto">
          <h2 class="text-2xl font-bold text-slate-800 mb-6">About this project</h2>
          <div class="prose prose-slate max-w-none text-slate-600 leading-relaxed whitespace-pre-line">{{ project.details }}</div>
        </div>
      </section>

      <!-- Footer back link -->
      <div class="py-12 px-6 border-t border-slate-100 text-center">
        <a :href="`/portfolio/${owner.username}`"
          class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-orange-500 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Back to {{ owner.name }}'s Portfolio
        </a>
      </div>
    </main>

    <!-- Lightbox -->
    <Teleport to="body">
      <div v-if="lightboxIndex !== null"
        class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center"
        @click.self="closeLightbox" @keydown.escape="closeLightbox">
        <button @click="closeLightbox"
          class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white text-xl flex items-center justify-center transition-colors">✕</button>
        <button v-if="media.length > 1" @click="prevItem"
          class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div class="max-w-5xl max-h-[85vh] w-full px-16 flex items-center justify-center">
          <img v-if="currentItem?.is_image" :src="currentItem.url" :alt="currentItem.alt_text"
            class="max-w-full max-h-[85vh] object-contain rounded-xl shadow-2xl" />
          <video v-else-if="currentItem?.is_video" :src="currentItem.url" controls
            class="max-w-full max-h-[85vh] rounded-xl shadow-2xl" />
        </div>
        <button v-if="media.length > 1" @click="nextItem"
          class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
        <div v-if="media.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5">
          <button v-for="(_, i) in media" :key="i" @click="lightboxIndex = i"
            class="w-2 h-2 rounded-full transition-colors"
            :class="i === lightboxIndex ? 'bg-white' : 'bg-white/30'" />
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  project:  { type: Object, required: true },
  media:    { type: Array,  default: () => [] },
  owner:    { type: Object, required: true },
  settings: { type: Object, default: () => ({}) },
})

// ── Lightbox ──────────────────────────────────────────────────────────────────
const lightboxIndex = ref(null)
const currentItem   = computed(() => lightboxIndex.value !== null ? props.media[lightboxIndex.value] : null)

const openLightbox  = (i) => { lightboxIndex.value = i }
const closeLightbox = ()  => { lightboxIndex.value = null }
const prevItem      = ()  => { lightboxIndex.value = (lightboxIndex.value - 1 + props.media.length) % props.media.length }
const nextItem      = ()  => { lightboxIndex.value = (lightboxIndex.value + 1) % props.media.length }

const onKey = (e) => {
  if (lightboxIndex.value === null) return
  if (e.key === 'ArrowLeft')  prevItem()
  if (e.key === 'ArrowRight') nextItem()
  if (e.key === 'Escape')     closeLightbox()
}

onMounted(()   => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

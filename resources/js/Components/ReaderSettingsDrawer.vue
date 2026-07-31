<template>
  <div v-if="open" class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30" @click="$emit('close')"></div>
    <div class="absolute top-0 right-0 h-full w-72 max-w-[85vw] shadow-xl overflow-y-auto" :class="themeClass">
      <div class="flex items-center justify-between px-5 py-4 border-b border-current/10">
        <h2 class="font-semibold text-sm">Reading Settings</h2>
        <button data-testid="drawer-close" @click="$emit('close')" class="opacity-70 hover:opacity-100 text-lg leading-none">✕</button>
      </div>

      <div class="px-5 py-4 space-y-6 text-sm">
        <section>
          <h3 class="text-xs uppercase tracking-wide opacity-60 mb-2">Theme</h3>
          <div class="flex flex-wrap gap-3">
            <button @click="setTheme('white')" class="opacity-70 hover:opacity-100">White</button>
            <button @click="setTheme('sepia')" class="opacity-70 hover:opacity-100">Sepia</button>
            <button @click="setTheme('sepia-dark')" class="opacity-70 hover:opacity-100">Dark Sepia</button>
            <button @click="setTheme('black')" class="opacity-70 hover:opacity-100">Black</button>
          </div>
        </section>

        <section>
          <h3 class="text-xs uppercase tracking-wide opacity-60 mb-2">Font size</h3>
          <div class="flex gap-3">
            <button @click="decreaseFontSize" class="opacity-70 hover:opacity-100">A−</button>
            <button @click="increaseFontSize" class="opacity-70 hover:opacity-100">A+</button>
          </div>
        </section>

        <section>
          <h3 class="text-xs uppercase tracking-wide opacity-60 mb-2">Reading Mode</h3>
          <div class="flex flex-wrap gap-3">
            <button data-testid="mode-scroll" @click="setMode('scroll')" class="opacity-70 hover:opacity-100" :class="mode === 'scroll' ? 'font-bold opacity-100' : ''">Scroll</button>
            <button data-testid="mode-h-page" @click="setMode('h-page')" class="opacity-70 hover:opacity-100" :class="mode === 'h-page' ? 'font-bold opacity-100' : ''">H-Page</button>
            <button data-testid="mode-v-page" @click="setMode('v-page')" class="opacity-70 hover:opacity-100" :class="mode === 'v-page' ? 'font-bold opacity-100' : ''">V-Page</button>
            <button data-testid="mode-autoscroll" @click="setMode('autoscroll')" class="opacity-70 hover:opacity-100" :class="mode === 'autoscroll' ? 'font-bold opacity-100' : ''">Autoscroll</button>
          </div>
        </section>

        <section v-if="mode === 'autoscroll'">
          <h3 class="text-xs uppercase tracking-wide opacity-60 mb-2">Autoscroll Speed</h3>
          <div class="flex flex-wrap gap-3 mb-4">
            <button @click="setAutoscrollSpeed('slow')" class="opacity-70 hover:opacity-100" :class="autoscrollSpeed === 'slow' ? 'font-bold opacity-100' : ''">Slow</button>
            <button @click="setAutoscrollSpeed('medium')" class="opacity-70 hover:opacity-100" :class="autoscrollSpeed === 'medium' ? 'font-bold opacity-100' : ''">Medium</button>
            <button @click="setAutoscrollSpeed('fast')" class="opacity-70 hover:opacity-100" :class="autoscrollSpeed === 'fast' ? 'font-bold opacity-100' : ''">Fast</button>
          </div>
          <button @click="togglePlay" class="px-3 py-1.5 rounded-lg border border-current/20 hover:bg-current/10">
            {{ isPlaying ? 'Pause' : 'Play' }}
          </button>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useReaderTheme } from '@/composables/useReaderTheme'
import { useReaderMode } from '@/composables/useReaderMode'

defineProps({ open: { type: Boolean, default: false } })
defineEmits(['close'])

const { themeClass, setTheme, increaseFontSize, decreaseFontSize } = useReaderTheme()
const { mode, autoscrollSpeed, isPlaying, setMode, setAutoscrollSpeed, togglePlay } = useReaderMode()
</script>

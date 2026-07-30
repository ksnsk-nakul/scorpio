<template>
  <div class="w-full h-full min-h-[220px] overflow-auto p-4 text-sm" :class="themeClass" :style="fontStyle">
    <pre v-if="variant === 'text'" class="whitespace-pre-wrap font-mono">{{ content }}</pre>
    <div v-else-if="variant === 'markdown'" class="markdown-body" v-html="renderedMarkdown"></div>
    <table v-else-if="variant === 'csv'" class="min-w-full border-collapse">
      <thead>
        <tr>
          <th v-for="(col, i) in headerRow" :key="i" class="border border-slate-200 px-2 py-1 text-left font-medium">
            {{ col }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, r) in bodyRows" :key="r">
          <td v-for="(cell, c) in row" :key="c" class="border border-slate-200 px-2 py-1">{{ cell }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { marked } from 'marked'
import Papa from 'papaparse'
import { resolveRenderer } from '../rendererMap'
import { useReaderTheme } from '@/composables/useReaderTheme'

const props = defineProps({ media: { type: Object, required: true } })

const variant = computed(() => resolveRenderer(props.media))
const content = ref('')
const csvRows = ref([])
const { themeClass, fontStyle } = useReaderTheme()

onMounted(async () => {
  const response = await fetch(props.media.url)
  const text = await response.text()

  if (variant.value === 'csv') {
    csvRows.value = Papa.parse(text.trim()).data
  } else {
    content.value = text
  }
})

const renderedMarkdown = computed(() => marked.parse(content.value))
const headerRow = computed(() => csvRows.value[0] ?? [])
const bodyRows = computed(() => csvRows.value.slice(1))
</script>

<style scoped>
/*
 * Tailwind's preflight resets default heading/list styling to look like
 * plain text, so markdown injected via v-html needs its own minimal
 * typography rules. `@tailwindcss/typography` isn't a project dependency,
 * so these are hand-rolled instead of pulling in `prose`. Headings/lists
 * use `color: inherit` (not a fixed color) so they keep working across all
 * four reader themes (white/black/sepia/sepia-dark) applied via themeClass
 * on the outer container.
 */
.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3),
.markdown-body :deep(h4),
.markdown-body :deep(h5),
.markdown-body :deep(h6) {
  color: inherit;
  font-weight: 700;
  line-height: 1.25;
  margin-top: 1em;
  margin-bottom: 0.5em;
}

.markdown-body :deep(h1) { font-size: 1.75em; }
.markdown-body :deep(h2) { font-size: 1.5em; }
.markdown-body :deep(h3) { font-size: 1.25em; }
.markdown-body :deep(h4) { font-size: 1.1em; }
.markdown-body :deep(h5) { font-size: 1em; }
.markdown-body :deep(h6) { font-size: 0.875em; }

.markdown-body :deep(p),
.markdown-body :deep(blockquote) {
  margin-top: 0.5em;
  margin-bottom: 0.5em;
}

.markdown-body :deep(ul),
.markdown-body :deep(ol) {
  margin-top: 0.5em;
  margin-bottom: 0.5em;
  padding-left: 1.5em;
}

.markdown-body :deep(ul) { list-style: disc; }
.markdown-body :deep(ol) { list-style: decimal; }
.markdown-body :deep(li) { margin-top: 0.25em; }

.markdown-body :deep(blockquote) {
  border-left: 3px solid currentColor;
  padding-left: 0.75em;
  opacity: 0.85;
}

.markdown-body :deep(code) {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.875em;
  background-color: currentColor;
  background-color: color-mix(in srgb, currentColor 12%, transparent);
  padding: 0.125em 0.35em;
  border-radius: 0.25em;
}

.markdown-body :deep(pre) {
  margin-top: 0.5em;
  margin-bottom: 0.5em;
  padding: 0.75em;
  overflow-x: auto;
  border-radius: 0.375em;
  background-color: color-mix(in srgb, currentColor 8%, transparent);
}

.markdown-body :deep(pre code) {
  background-color: transparent;
  padding: 0;
}
</style>

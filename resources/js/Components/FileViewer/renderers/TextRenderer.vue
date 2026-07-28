<template>
  <div class="w-full h-full min-h-[220px] overflow-auto p-4 text-sm" :class="themeClass" :style="fontStyle">
    <pre v-if="variant === 'text'" class="whitespace-pre-wrap font-mono">{{ content }}</pre>
    <div v-else-if="variant === 'markdown'" v-html="renderedMarkdown"></div>
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
import { useReaderTheme } from '@/Composables/useReaderTheme'

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

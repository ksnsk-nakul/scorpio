<template>
  <PdfRenderer :media="pdfMedia" />
</template>

<script setup>
import { computed } from 'vue'
import PdfRenderer from './PdfRenderer.vue'

const props = defineProps({ media: { type: Object, required: true } })

const pdfMedia = computed(() => ({ ...props.media, url: props.media.converted_pdf_url }))
</script>

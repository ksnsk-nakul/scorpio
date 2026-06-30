<template>
  <div>
    <!-- Image preview -->
    <div v-if="modelValue" class="relative mb-2 group">
      <img :src="modelValue" class="w-full max-h-48 object-cover rounded-lg border border-slate-200" />
      <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 rounded-lg transition" />
      <button @click="clear"
        class="absolute top-2 right-2 bg-white/90 border border-slate-200 rounded-full w-7 h-7 text-slate-500 hover:text-red-500 hidden group-hover:flex items-center justify-center shadow-sm text-sm">
        ✕
      </button>
      <button @click="fileInput.click()"
        class="absolute bottom-2 right-2 bg-white/90 border border-slate-200 rounded-lg px-2 py-1 text-xs text-slate-600 hover:text-blue-600 hidden group-hover:flex items-center gap-1 shadow-sm">
        ↑ Replace
      </button>
    </div>

    <!-- Upload zone -->
    <div v-else
      class="border-2 border-dashed rounded-lg p-5 text-center cursor-pointer transition select-none"
      :class="dragging ? 'border-blue-400 bg-blue-50' : 'border-slate-200 hover:border-blue-300 hover:bg-slate-50'"
      @click="fileInput.click()"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop">
      <p class="text-sm text-slate-400">
        <span v-if="uploading" class="text-blue-500">Uploading…</span>
        <span v-else>Drop image here or <span class="text-blue-600 font-medium">browse</span></span>
      </p>
      <p class="text-xs text-slate-300 mt-1">PNG, JPG, WEBP · max 5 MB</p>
    </div>

    <input ref="fileInput" type="file" class="hidden" accept="image/*" @change="onFileChange" />
    <p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({ modelValue: String })
const emit = defineEmits(['update:modelValue'])

const dragging  = ref(false)
const uploading = ref(false)
const error     = ref(null)
const fileInput = ref(null)

const upload = async (file) => {
  if (!file?.type.startsWith('image/')) { error.value = 'Please select an image file.'; return }
  uploading.value = true
  error.value = null
  const fd = new FormData()
  fd.append('file', file)
  try {
    const { data } = await axios.post('/admin/media', fd)
    emit('update:modelValue', data.url)
  } catch {
    error.value = 'Upload failed — check file size and try again.'
  } finally {
    uploading.value = false
    dragging.value  = false
  }
}

const onFileChange = (e) => { upload(e.target.files[0]); e.target.value = '' }
const onDrop       = (e) => upload(e.dataTransfer.files[0])
const clear        = () => emit('update:modelValue', '')
</script>

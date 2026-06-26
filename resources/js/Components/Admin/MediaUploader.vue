<template>
  <div>
    <div class="flex flex-wrap gap-3 mb-3">
      <div
        v-for="item in uploaded"
        :key="item.id"
        class="relative w-24 h-24 rounded-lg overflow-hidden border border-slate-200 bg-slate-50 group"
      >
        <img v-if="item.is_image" :src="item.url" class="w-full h-full object-cover" :alt="item.filename" />
        <div v-else class="w-full h-full flex items-center justify-center text-xs text-slate-500 text-center p-1">
          🎬 {{ item.filename }}
        </div>
        <button
          @click="remove(item)"
          class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs hidden group-hover:flex items-center justify-center"
        >✕</button>
      </div>
    </div>

    <label class="flex items-center gap-2 cursor-pointer text-sm text-blue-600 hover:text-blue-800">
      <input type="file" class="hidden" multiple :accept="acceptedTypes" @change="handleFiles" ref="fileInput" />
      📎 Attach files (images or videos)
    </label>

    <p v-if="uploading" class="text-xs text-slate-400 mt-1">Uploading...</p>
    <p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({ modelValue: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])

const uploaded = ref([])
const error = ref(null)
const uploading = ref(false)
const fileInput = ref(null)
const acceptedTypes = 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime'

const handleFiles = async (event) => {
  error.value = null
  uploading.value = true
  const files = Array.from(event.target.files)
  for (const file of files) {
    try {
      const fd = new FormData()
      fd.append('file', file)
      const { data } = await axios.post('/admin/media', fd)
      uploaded.value.push(data)
      emit('update:modelValue', uploaded.value.map(u => u.id))
    } catch (e) {
      error.value = e.response?.data?.errors?.file?.[0] ?? 'Upload failed'
    }
  }
  uploading.value = false
  if (fileInput.value) fileInput.value.value = ''
}

const remove = async (item) => {
  try {
    await axios.delete(`/admin/media/${item.id}`)
    uploaded.value = uploaded.value.filter(u => u.id !== item.id)
    emit('update:modelValue', uploaded.value.map(u => u.id))
  } catch (e) {
    error.value = 'Failed to remove file'
  }
}
</script>

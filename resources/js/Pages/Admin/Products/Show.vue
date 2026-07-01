<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs text-slate-400">{{ project.workspace?.name }}</p>
          <h1 class="text-2xl font-bold text-slate-800">{{ project.name }}</h1>
          <p v-if="project.github_repo" class="text-xs text-slate-500 mt-1">
            🐙 <a :href="`https://github.com/${project.github_repo}`" target="_blank"
              class="hover:underline">{{ project.github_repo }}</a>
          </p>
        </div>
        <Link href="/admin/products" class="text-xs text-slate-400 hover:text-slate-700">← Products</Link>
      </div>

      <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="font-semibold text-slate-800 text-sm mb-4">Media Library</h2>
        <MediaUploader v-model="newMediaIds" />
        <div class="flex flex-wrap gap-3 mt-4">
          <div v-for="m in media" :key="m.id" class="w-24 h-24 rounded-lg overflow-hidden border border-slate-200 bg-slate-50">
            <img v-if="m.is_image" :src="m.url" class="w-full h-full object-cover" :alt="m.filename" />
            <div v-else class="flex items-center justify-center h-full text-xs text-slate-400 text-center p-1">
              🎬 {{ m.filename }}
            </div>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MediaUploader from '@/Components/Admin/MediaUploader.vue'

const props = defineProps({ project: Object, media: Array })
const newMediaIds = ref([])
</script>

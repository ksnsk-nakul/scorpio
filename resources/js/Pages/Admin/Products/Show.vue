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

      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 text-sm">Tasks ({{ tasks.length }})</h2>
          <button @click="showTaskCreate = true"
            class="text-xs bg-blue-600 text-white rounded px-3 py-1.5 hover:bg-blue-700">+ Task</button>
        </div>
        <ul class="divide-y divide-slate-100">
          <li v-for="task in tasks" :key="task.id" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
            <span :class="statusBadge(task.status)" class="text-xs px-2 py-0.5 rounded-full font-medium">{{ task.status }}</span>
            <Link :href="`/admin/tasks/${task.id}`" class="flex-1 text-sm text-slate-700 hover:underline">
              {{ task.title }}
            </Link>
            <span v-if="task.assignee" class="text-xs text-slate-400">{{ task.assignee.name }}</span>
          </li>
          <li v-if="tasks.length === 0" class="px-5 py-4 text-sm text-slate-400">No tasks yet.</li>
        </ul>
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

      <!-- Quick task create modal -->
      <Teleport to="body">
        <div v-if="showTaskCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div class="bg-white rounded-xl p-6 w-96 shadow-xl">
            <h2 class="font-semibold mb-4 text-slate-800">New Task</h2>
            <input v-model="taskForm.title" placeholder="Title"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none" />
            <div class="grid grid-cols-2 gap-3 mb-4">
              <select v-model="taskForm.status" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none">
                <option>open</option><option>in_progress</option><option>done</option><option>closed</option>
              </select>
              <select v-model="taskForm.priority" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none">
                <option>low</option><option>medium</option><option>high</option>
              </select>
            </div>
            <div class="flex gap-2 justify-end">
              <button @click="showTaskCreate = false" class="px-4 py-2 text-sm text-slate-500">Cancel</button>
              <button @click="taskForm.post('/admin/tasks', { onSuccess: () => showTaskCreate = false })"
                :disabled="taskForm.processing"
                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg">Create</button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MediaUploader from '@/Components/Admin/MediaUploader.vue'

const props = defineProps({ project: Object, tasks: Array, media: Array })
const newMediaIds = ref([])
const showTaskCreate = ref(false)

const taskForm = useForm({
  project_id: props.project.id,
  title: '',
  status: 'open',
  priority: 'medium',
})

const statusBadge = s => ({
  open: 'bg-amber-100 text-amber-700',
  in_progress: 'bg-blue-100 text-blue-700',
  done: 'bg-green-100 text-green-700',
  closed: 'bg-slate-100 text-slate-500',
}[s] ?? 'bg-slate-100 text-slate-500')
</script>

<template>
  <AdminLayout>
    <div class="max-w-3xl mx-auto space-y-6">

      <!-- Task card -->
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-start justify-between mb-4">
          <div class="flex-1">
            <p v-if="task.parent" class="text-xs text-slate-400 mb-1">
              Subtask of
              <Link :href="`/admin/tasks/${task.parent.id}`" class="hover:underline text-blue-600">{{ task.parent.title }}</Link>
            </p>
            <h1 v-if="!editing" @click="editing = true"
              class="text-xl font-bold text-slate-800 cursor-pointer hover:text-blue-700">{{ form.title }}</h1>
            <input v-else v-model="form.title" @blur="editing = false" @keydown.enter="editing = false"
              class="text-xl font-bold text-slate-800 w-full outline-none border-b border-blue-500 pb-1" autofocus />
          </div>
          <div class="flex gap-2 ml-4 flex-shrink-0">
            <select v-model="form.status" class="text-xs border border-slate-200 rounded px-2 py-1 outline-none">
              <option v-for="s in ['open','in_progress','done','closed']" :key="s" :value="s">{{ s }}</option>
            </select>
            <select v-model="form.priority" class="text-xs border border-slate-200 rounded px-2 py-1 outline-none">
              <option v-for="p in ['low','medium','high']" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
        </div>

        <textarea v-model="form.body" placeholder="Description..."
          class="w-full text-sm text-slate-600 bg-transparent outline-none resize-none min-h-24 mb-4" />

        <div class="mb-4">
          <p class="text-xs font-medium text-slate-500 mb-2">Attachments</p>
          <MediaUploader v-model="form.media_ids" />
        </div>

        <div class="flex gap-2">
          <button @click="form.patch(`/admin/tasks/${task.id}`)" :disabled="form.processing"
            class="text-xs bg-blue-600 text-white rounded px-3 py-1.5 hover:bg-blue-700 disabled:opacity-50">Save</button>
          <button @click="router.delete(`/admin/tasks/${task.id}`)"
            class="text-xs text-red-500 border border-red-200 rounded px-3 py-1.5 hover:bg-red-50">Delete</button>
          <Link href="/admin/tasks" class="text-xs text-slate-400 hover:text-slate-700 px-2 py-1.5">← Tasks</Link>
        </div>
      </div>

      <!-- Subtasks -->
      <div class="bg-white border border-slate-200 rounded-xl p-5">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-sm font-semibold text-slate-700">Subtasks ({{ task.subtasks?.length ?? 0 }})</h2>
          <button @click="showSubtask = !showSubtask"
            class="text-xs bg-slate-100 text-slate-600 rounded px-3 py-1.5 hover:bg-slate-200">
            {{ showSubtask ? 'Cancel' : '+ Subtask' }}
          </button>
        </div>
        <div v-if="task.subtasks?.length" class="flex items-center gap-2 mb-3">
          <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-green-400 transition-all" :style="{ width: subtaskProgressPct + '%' }" />
          </div>
          <span class="text-xs text-slate-400 flex-shrink-0">{{ doneSubtasks }}/{{ task.subtasks.length }} done</span>
        </div>
        <ul class="space-y-2 mb-3">
          <li v-for="sub in task.subtasks" :key="sub.id" class="flex items-center gap-3 text-sm">
            <span :class="statusBadge(sub.status)" class="text-xs px-2 py-0.5 rounded-full">{{ sub.status }}</span>
            <Link :href="`/admin/tasks/${sub.id}`" class="flex-1 hover:underline text-slate-700">{{ sub.title }}</Link>
          </li>
          <li v-if="!task.subtasks?.length" class="text-xs text-slate-400">No subtasks.</li>
        </ul>
        <div v-if="showSubtask" class="flex gap-2 pt-3 border-t border-slate-100">
          <input v-model="subtaskForm.title" placeholder="Subtask title"
            class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
          <button @click="createSubtask" :disabled="subtaskForm.processing"
            class="text-xs bg-blue-600 text-white rounded px-3 py-2 hover:bg-blue-700">Add</button>
        </div>
      </div>

      <!-- Comments -->
      <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Comments ({{ task.comments?.length ?? 0 }})</h2>

        <div v-for="comment in task.comments" :key="comment.id" class="mb-4 flex items-start gap-3">
          <img
            :src="comment.user?.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(comment.user?.name ?? 'U')}&size=28`"
            class="w-7 h-7 rounded-full flex-shrink-0 bg-slate-200" :alt="comment.user?.name" />
          <div class="flex-1 bg-slate-50 rounded-xl px-4 py-3">
            <p class="text-xs font-medium text-slate-600 mb-1">{{ comment.user?.name }}</p>
            <p class="text-sm text-slate-700">{{ comment.body }}</p>
          </div>
          <Link :href="`/admin/comments/${comment.id}`" method="delete" as="button"
            class="text-xs text-slate-300 hover:text-red-400 mt-1">✕</Link>
        </div>

        <div class="border-t border-slate-100 pt-4 mt-2">
          <textarea v-model="commentForm.body" placeholder="Add a comment..."
            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none resize-none min-h-16 mb-2" />
          <MediaUploader v-model="commentForm.media_ids" />
          <button @click="commentForm.post(`/admin/tasks/${task.id}/comments`)" :disabled="commentForm.processing"
            class="mt-2 text-xs bg-blue-600 text-white rounded px-4 py-2 hover:bg-blue-700 disabled:opacity-50">
            Post Comment
          </button>
        </div>
      </div>

      <!-- Activity log -->
      <div v-if="task.activities?.length" class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Activity</h2>
        <ul class="space-y-2.5">
          <li v-for="activity in task.activities" :key="activity.id" class="text-xs text-slate-500 flex items-start gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-300 mt-1.5 flex-shrink-0" />
            <span>
              <span class="font-medium text-slate-700">{{ activity.user?.name ?? 'System' }}</span>
              changed <span class="font-medium">{{ activity.field.replace('_', ' ') }}</span>
              <template v-if="activity.from"> from <span class="font-mono">{{ activity.from }}</span></template>
              to <span class="font-mono">{{ activity.to }}</span>
              <span class="text-slate-300">· {{ formatDate(activity.created_at) }}</span>
            </span>
          </li>
        </ul>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MediaUploader from '@/Components/Admin/MediaUploader.vue'

const props = defineProps({ task: Object, users: Array })

const doneSubtasks = computed(() => (props.task.subtasks ?? []).filter(s => s.status === 'done').length)
const subtaskProgressPct = computed(() => {
  const total = props.task.subtasks?.length ?? 0
  return total === 0 ? 0 : Math.round((doneSubtasks.value / total) * 100)
})

const formatDate = (d) => new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })

const editing = ref(false)
const showSubtask = ref(false)

const form = useForm({
  title:     props.task.title,
  body:      props.task.body ?? '',
  status:    props.task.status,
  priority:  props.task.priority,
  media_ids: [],
})

const commentForm = useForm({ body: '', media_ids: [] })

const subtaskForm = useForm({
  project_id: props.task.project_id,
  parent_id:  props.task.id,
  title:      '',
  status:     'open',
  priority:   'medium',
})

const createSubtask = () => {
  subtaskForm.post('/admin/tasks', {
    onSuccess: () => { showSubtask.value = false; subtaskForm.reset('title') }
  })
}

const statusBadge = s => ({
  open:        'bg-amber-100 text-amber-700',
  in_progress: 'bg-blue-100 text-blue-700',
  done:        'bg-green-100 text-green-700',
  closed:      'bg-slate-100 text-slate-500',
}[s] ?? 'bg-slate-100 text-slate-500')
</script>

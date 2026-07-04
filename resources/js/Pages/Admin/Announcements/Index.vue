<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto px-6 py-10">
      <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Announcements</h1>
        <button @click="showForm = !showForm" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-lg transition-colors">
          + New
        </button>
      </div>

      <!-- Create form -->
      <form v-if="showForm" @submit.prevent="submit" class="bg-slate-50 border border-slate-200 rounded-xl p-6 mb-8 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Title</label>
            <input v-model="form.title" type="text" required maxlength="150" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
              <select v-model="form.type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="success">Success</option>
                <option value="ad">Ad</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Display</label>
              <select v-model="form.display" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="banner">Banner</option>
                <option value="modal">Modal</option>
              </select>
            </div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Body</label>
          <textarea v-model="form.body" required maxlength="1000" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">CTA Label</label>
            <input v-model="form.cta_label" type="text" maxlength="50" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">CTA URL</label>
            <input v-model="form.cta_url" type="url" maxlength="255" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Starts At</label>
            <input v-model="form.starts_at" type="datetime-local" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Ends At</label>
            <input v-model="form.ends_at" type="datetime-local" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>
        <div class="flex items-center gap-2">
          <input v-model="form.active" type="checkbox" id="active" class="w-4 h-4 accent-orange-500" />
          <label for="active" class="text-sm text-slate-700">Active</label>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="showForm = false" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-lg disabled:opacity-50">
            Create
          </button>
        </div>
      </form>

      <!-- List -->
      <div class="space-y-3">
        <div v-for="ann in announcements" :key="ann.id"
          class="flex items-start justify-between bg-white border border-slate-200 rounded-xl px-5 py-4">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span :class="typeBadge(ann.type)" class="text-xs px-2 py-0.5 rounded-full font-medium">{{ ann.type }}</span>
              <span class="text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ ann.display }}</span>
              <span v-if="ann.active" class="text-xs text-green-700 bg-green-100 px-2 py-0.5 rounded-full">active</span>
              <span v-else class="text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">inactive</span>
            </div>
            <p class="font-semibold text-slate-800 text-sm">{{ ann.title }}</p>
            <p class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ ann.body }}</p>
          </div>
          <div class="flex items-center gap-2 ml-4 flex-shrink-0">
            <button @click="toggle(ann)" class="text-xs text-slate-500 hover:text-slate-800 underline">
              {{ ann.active ? 'Deactivate' : 'Activate' }}
            </button>
            <button @click="remove(ann.id)" class="text-xs text-red-500 hover:text-red-700 underline">Delete</button>
          </div>
        </div>
        <p v-if="!announcements.length" class="text-slate-400 text-sm text-center py-12">No announcements yet.</p>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ announcements: Array })

const showForm = ref(false)
const form = useForm({
  title: '', body: '', type: 'info', display: 'banner',
  cta_label: '', cta_url: '', active: true, starts_at: '', ends_at: '',
})

function submit() {
  form.post('/admin/announcements', {
    onSuccess: () => { showForm.value = false; form.reset() },
  })
}

function toggle(ann) {
  useForm({ active: !ann.active }).patch(`/admin/announcements/${ann.id}`)
}

function remove(id) {
  if (!confirm('Delete this announcement?')) return
  useForm({}).delete(`/admin/announcements/${id}`)
}

const typeBadge = (type) => ({
  info:    'bg-blue-100 text-blue-700',
  warning: 'bg-yellow-100 text-yellow-700',
  success: 'bg-green-100 text-green-700',
  ad:      'bg-orange-100 text-orange-700',
}[type] ?? 'bg-slate-100 text-slate-700')
</script>

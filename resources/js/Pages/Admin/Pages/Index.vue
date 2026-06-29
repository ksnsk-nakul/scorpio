<template>
  <AdminLayout>
    <div class="flex gap-6">
      <!-- Sidebar -->
      <div class="w-52 flex-shrink-0 space-y-4">
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="bg-slate-50 px-4 py-3 flex items-center justify-between border-b border-slate-100">
            <span class="text-xs font-semibold text-slate-500 uppercase">Pages</span>
            <button @click="showCreate = true"
              class="text-xs bg-blue-600 text-white rounded px-2 py-0.5 hover:bg-blue-700">+</button>
          </div>
          <nav class="p-2 space-y-1">
            <div v-for="p in pages" :key="p.id" class="flex items-center gap-1">
              <Link
                :href="`/admin/pages/${p.id}/edit`"
                class="flex-1 block px-3 py-2 rounded-lg text-sm transition text-slate-700 hover:bg-slate-50 truncate"
              >
                {{ p.name }}
                <span v-if="p.status === 'draft'" class="text-xs text-amber-500 ml-1">draft</span>
              </Link>
              <button
                @click="previewPage = p"
                class="text-xs text-slate-400 hover:text-blue-600 px-1 py-1 rounded flex-shrink-0"
                title="Preview"
              >👁</button>
            </div>
            <p v-if="pages.length === 0" class="px-3 py-2 text-xs text-slate-400">No pages yet.</p>
          </nav>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="bg-slate-50 px-4 py-3 border-b border-slate-100">
            <span class="text-xs font-semibold text-slate-500 uppercase">Templates</span>
          </div>
          <div class="p-2 space-y-1">
            <button
              v-for="t in templates"
              :key="t"
              @click="createWithTemplate(t)"
              class="block w-full text-left px-3 py-2 text-xs text-slate-600 border border-slate-100 rounded-lg hover:bg-slate-50"
            >{{ templateLabel(t) }}</button>
          </div>
        </div>
      </div>

      <!-- Main -->
      <div class="flex-1 bg-white border border-slate-200 rounded-xl flex items-center justify-center min-h-64 text-slate-400 text-sm">
        Select a page to edit, or create a new one.
      </div>
    </div>

    <!-- Preview modal -->
    <PagePreview v-if="previewPage" :page="previewPage" @close="previewPage = null" />

    <!-- Create modal -->
    <Teleport to="body">
      <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-80 shadow-xl">
          <h2 class="font-semibold mb-4 text-slate-800">New Page</h2>
          <input
            v-model="form.name"
            placeholder="Page name"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none focus:ring-2 focus:ring-blue-500"
          />
          <select
            v-model="form.template"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-4 outline-none"
          >
            <option v-for="t in templates" :key="t" :value="t">{{ templateLabel(t) }}</option>
          </select>
          <div class="flex gap-2 justify-end">
            <button @click="showCreate = false" class="px-4 py-2 text-sm text-slate-500">Cancel</button>
            <button @click="submit" :disabled="form.processing"
              class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg disabled:opacity-50">Create</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PagePreview from '@/Components/PagePreview.vue'

const props = defineProps({ pages: Array, templates: Array })

const showCreate = ref(false)
const previewPage = ref(null)
const form = useForm({ name: '', template: 'blank' })

const templateLabel = (t) => ({
  blank:        'Blank',
  hero_cards:   'Hero + Cards',
  text_image:   'Text + Image',
  project_grid: 'Project Grid',
}[t] ?? t)

const submit = () => form.post('/admin/pages', {
  onSuccess: () => { showCreate.value = false },
})

const createWithTemplate = (t) => {
  form.template = t
  showCreate.value = true
}
</script>

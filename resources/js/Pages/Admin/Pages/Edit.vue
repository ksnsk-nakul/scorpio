<template>
  <AdminLayout>
    <div class="flex gap-6">
      <!-- Sidebar -->
      <div class="w-52 flex-shrink-0">
        <div class="bg-white border border-slate-200 rounded-xl p-4 space-y-3 sticky top-6">
          <div>
            <label class="text-xs text-slate-500 block mb-1">Page name</label>
            <input v-model="form.name"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="text-xs text-slate-500 block mb-1">Status</label>
            <p class="text-sm font-medium" :class="form.status === 'published' ? 'text-green-600' : 'text-amber-500'">
              {{ form.status }}
            </p>
          </div>
          <p v-if="form.hasErrors" class="text-xs text-red-500">{{ Object.values(form.errors)[0] }}</p>
          <button @click="save" :disabled="form.processing"
            class="w-full bg-slate-800 text-white text-sm rounded-lg py-2 hover:bg-slate-900 disabled:opacity-50">
            Save Draft
          </button>
          <button @click="publish" :disabled="form.processing"
            class="w-full bg-green-600 text-white text-sm rounded-lg py-2 hover:bg-green-700 disabled:opacity-50">
            Publish
          </button>
          <a :href="`/preview/pages/${page.id}`" target="_blank"
            class="block text-center text-sm text-blue-600 hover:underline">Preview ↗</a>
          <Link href="/admin/pages" class="block text-center text-xs text-slate-400 hover:text-slate-600">
            ← All pages
          </Link>
        </div>
      </div>

      <!-- Block canvas -->
      <div class="flex-1">
        <BlockEditor v-model="form.blocks" :block-types="blockTypes" />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import BlockEditor from '@/Components/Admin/BlockEditor.vue'

const props = defineProps({ page: Object, blockTypes: Array })

const form = useForm({
  name:   props.page.name,
  blocks: props.page.blocks ?? [],
  status: props.page.status,
})

const save    = () => form.patch(`/admin/pages/${props.page.id}`, { preserveScroll: true })
const publish = () => form.patch(`/admin/pages/${props.page.id}/publish`)
</script>

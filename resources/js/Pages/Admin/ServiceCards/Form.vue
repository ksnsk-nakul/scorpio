<template>
  <AdminLayout>
    <div class="max-w-xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">{{ card ? 'Edit' : 'New' }} Service Card</h1>

      <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
        <div>
          <label class="block text-sm text-slate-600 mb-1">Title *</label>
          <input v-model="form.title" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" required />
          <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">Description</label>
          <textarea v-model="form.description" class="field min-h-20 resize-none" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-slate-600 mb-1">Icon (emoji or class)</label>
            <input v-model="form.icon" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="🚀" />
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1">Image URL</label>
            <input v-model="form.image" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="/storage/..." />
          </div>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">Tags (comma separated)</label>
          <input v-model="tagsInput" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="Laravel, Vue, API" />
        </div>
        <label class="flex items-center gap-3">
          <input v-model="form.featured" type="checkbox" class="w-4 h-4 accent-blue-600" />
          <span class="text-sm text-slate-600">Featured card</span>
        </label>
        <div>
          <label class="block text-sm text-slate-600 mb-1">Link to Page</label>
          <select v-model="form.page_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500">
            <option :value="null">— none —</option>
            <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">External URL</label>
          <input v-model="form.external_url" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="https://..." />
          <p v-if="form.errors.external_url" class="text-xs text-red-500 mt-1">{{ form.errors.external_url }}</p>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="submit" :disabled="form.processing"
            class="bg-blue-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ card ? 'Update' : 'Create' }}
          </button>
          <Link href="/admin/service-cards" class="text-sm text-slate-500 px-4 py-2 hover:text-slate-800">
            Cancel
          </Link>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ card: Object, pages: Array })

const tagsInput = ref((props.card?.tags ?? []).join(', '))

const form = useForm({
  title:        props.card?.title ?? '',
  description:  props.card?.description ?? '',
  icon:         props.card?.icon ?? '',
  image:        props.card?.image ?? '',
  tags:         props.card?.tags ?? [],
  featured:     props.card?.featured ?? false,
  page_id:      props.card?.page_id ?? null,
  external_url: props.card?.external_url ?? '',
})

const submit = () => {
  form.tags = tagsInput.value.split(',').map(t => t.trim()).filter(Boolean)
  if (props.card) {
    form.patch(`/admin/service-cards/${props.card.id}`)
  } else {
    form.post('/admin/service-cards')
  }
}
</script>


<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto px-6 py-10">
      <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Organizations</h1>
        <Link v-if="isOrgPlan" href="#" @click.prevent="showForm = !showForm"
          class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-lg transition-colors">
          + New Organization
        </Link>
        <Link v-else href="/admin/org-upgrade"
          class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-lg transition-colors">
          Create Organization
        </Link>
      </div>

      <!-- Create form -->
      <form v-if="showForm" @submit.prevent="submit" class="bg-slate-50 border border-slate-200 rounded-xl p-6 mb-8 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
            <input v-model="form.name" type="text" required maxlength="100"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
            <input v-model="form.slug" type="text" required maxlength="100" pattern="[a-z0-9\-]+"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
            <p v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</p>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
          <textarea v-model="form.description" maxlength="500" rows="2"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="showForm = false" class="px-4 py-2 text-sm text-slate-600">Cancel</button>
          <button type="submit" :disabled="form.processing"
            class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-lg disabled:opacity-50">
            Create
          </button>
        </div>
      </form>

      <!-- List -->
      <div class="space-y-3">
        <Link v-for="org in organizations" :key="org.id" :href="`/admin/organizations/${org.id}`"
          class="flex items-center justify-between bg-white border border-slate-200 rounded-xl px-5 py-4 hover:border-orange-300 transition-colors block">
          <div>
            <p class="font-semibold text-slate-800">{{ org.name }}</p>
            <p class="text-xs text-slate-400 mt-0.5">/org/{{ org.slug }} · {{ org.plan }} plan</p>
            <p v-if="org.owner" class="text-xs text-slate-400">Owner: {{ org.owner.name }}</p>
          </div>
          <span class="text-slate-300 text-lg">›</span>
        </Link>
        <p v-if="!organizations.length" class="text-slate-400 text-sm text-center py-12">No organizations yet.</p>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { usePlan } from '@/composables/usePlan'

const props = defineProps({ organizations: Array })

const { currentPlan } = usePlan()
const isOrgPlan = computed(() => ['team', 'business', 'enterprise'].includes(currentPlan.value))

const showForm = ref(false)
const form = useForm({ name: '', slug: '', description: '' })

function submit() {
  form.post('/admin/organizations', {
    onSuccess: () => { showForm.value = false; form.reset() },
  })
}
</script>

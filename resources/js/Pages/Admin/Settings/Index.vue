<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">Settings</h1>

      <div class="flex gap-2 mb-6">
        <button v-for="g in groups" :key="g"
          @click="activeGroup = g"
          class="px-4 py-2 text-sm rounded-lg transition capitalize"
          :class="activeGroup === g ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'">
          {{ g }}
        </button>
      </div>

      <form @submit.prevent="save" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
        <div v-for="(value, key) in currentGroup" :key="key">
          <label class="block text-sm text-slate-600 mb-1">{{ formatKey(String(key)) }}</label>
          <input v-model="form[key]"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <div class="pt-2 flex items-center gap-3">
          <button type="submit"
            class="bg-blue-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-blue-700">
            Save Settings
          </button>
          <span v-if="form.wasSuccessful" class="text-green-600 text-sm">Saved!</span>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ settings: Object, groups: Array })
const activeGroup = ref(props.groups[0])

const allSettings = Object.values(props.settings).reduce((acc, g) => ({ ...acc, ...g }), {})
const form = useForm(allSettings)

const currentGroup = computed(() => props.settings[activeGroup.value] ?? {})
const save = () => form.patch('/admin/settings')
const formatKey = k => k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
</script>

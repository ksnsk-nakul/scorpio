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

      <div v-if="isDemo" class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl px-4 py-3">
        Settings are read-only in demo mode.
      </div>

      <IntegrationsPanel v-if="activeGroup === 'integrations'" :payment="payment" :mail="mail" :sms="sms" />

      <form v-else @submit.prevent="!isDemo && save()" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4"
        :class="isDemo ? 'opacity-60 pointer-events-none select-none' : ''">
        <div v-for="(value, key) in currentGroup" :key="key">
          <template v-if="isBoolKey(String(key))">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" :checked="form[key] === '1' || form[key] === true"
                @change="form[key] = $event.target.checked ? '1' : '0'"
                class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
              <span class="text-sm text-slate-600">{{ formatKey(String(key)) }}</span>
            </label>
          </template>
          <template v-else-if="isImageKey(String(key))">
            <label class="block text-sm text-slate-600 mb-1">{{ formatKey(String(key)) }}</label>
            <p class="text-xs text-slate-400 mb-2">This image appears in link previews when sharing your site on social media (Twitter/X, LinkedIn, iMessage, etc.).</p>
            <ImagePicker v-model="form[key]" context="branding" />
          </template>
          <template v-else-if="isSelectKey(String(key))">
            <label class="block text-sm text-slate-600 mb-1">{{ formatKey(String(key)) }}</label>
            <select v-model="form[key]"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500">
              <option v-for="opt in selectOptions(String(key))" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </template>
          <template v-else>
            <label class="block text-sm text-slate-600 mb-1">{{ formatKey(String(key)) }}</label>
            <input v-model="form[key]"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
          </template>
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
import { useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ImagePicker from '@/Components/Admin/ImagePicker.vue'
import IntegrationsPanel from '@/Components/Admin/IntegrationsPanel.vue'

const props = defineProps({
  settings: Object,
  groups: Array,
  payment: { type: Object, default: () => ({}) },
  mail: { type: Object, default: () => ({}) },
  sms: { type: Object, default: () => ({}) },
})

// Supports deep-linking straight to a tab, e.g. /admin/settings?group=integrations
// (the old /admin/integrations route redirects here with that query param).
const requestedGroup = new URLSearchParams(window.location.search).get('group')
const activeGroup = ref(props.groups.includes(requestedGroup) ? requestedGroup : props.groups[0])
const isDemo = usePage().props.demo

const allSettings = Object.values(props.settings).reduce((acc, g) => ({ ...acc, ...g }), {})
const form = useForm(allSettings)

const currentGroup = computed(() => props.settings[activeGroup.value] ?? {})
const save = () => form.patch('/admin/settings')
const formatKey = k => k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
const boolKeys  = ['show_donate_banner']
const imageKeys = ['og_image']
const selectKeys = {
  layout_template_public: [
    { value: 'minimalist', label: 'Minimalist' },
    { value: 'animejs', label: 'Anime.js' },
  ],
}
const isBoolKey  = k => boolKeys.includes(k)
const isImageKey = k => imageKeys.includes(k)
const isSelectKey = k => Object.prototype.hasOwnProperty.call(selectKeys, k)
const selectOptions = k => selectKeys[k] ?? []
</script>

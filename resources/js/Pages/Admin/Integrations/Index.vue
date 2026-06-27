<template>
  <AdminLayout>
    <div class="max-w-3xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Integrations</h1>
        <button @click="showAdd = true"
          class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">+ Add</button>
      </div>

      <div class="flex gap-2 mb-6 flex-wrap">
        <button v-for="g in groups" :key="g"
          @click="activeGroup = g"
          class="px-4 py-2 text-sm rounded-lg transition capitalize"
          :class="activeGroup === g ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'">
          {{ g }}
        </button>
      </div>

      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <ul class="divide-y divide-slate-100">
          <li v-for="item in filtered" :key="item.id" class="flex items-center gap-4 px-5 py-4">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-800">{{ item.provider }} / {{ item.key }}</p>
              <p class="text-xs text-slate-400 truncate">{{ maskValue(item.value) }}</p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full"
              :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
              {{ item.is_active ? 'Active' : 'Inactive' }}
            </span>
            <button @click="toggleActive(item)"
              class="text-xs text-slate-500 border border-slate-200 rounded px-2 py-1 hover:bg-slate-50">
              {{ item.is_active ? 'Disable' : 'Enable' }}
            </button>
            <button @click="deleteItem(item.id)"
              class="text-xs text-red-500 border border-red-100 rounded px-2 py-1 hover:bg-red-50">Delete</button>
          </li>
          <li v-if="filtered.length === 0" class="px-5 py-8 text-center text-sm text-slate-400">
            No {{ activeGroup }} integrations yet.
          </li>
        </ul>
      </div>

      <Teleport to="body">
        <div v-if="showAdd" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div class="bg-white rounded-xl p-6 w-96 shadow-xl space-y-3">
            <h2 class="font-semibold text-slate-800">Add Integration</h2>
            <select v-model="form.group"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none">
              <option v-for="g in groups" :key="g" :value="g">{{ g }}</option>
            </select>
            <input v-model="form.provider" placeholder="Provider (e.g. github)"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
            <input v-model="form.key" placeholder="Key (e.g. token)"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
            <input v-model="form.value" placeholder="Value" type="password"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
            <div class="flex items-center gap-2">
              <input v-model="form.is_active" type="checkbox" id="ia" />
              <label for="ia" class="text-sm text-slate-600">Active</label>
            </div>
            <div class="flex gap-2 justify-end">
              <button @click="showAdd = false" class="px-4 py-2 text-sm text-slate-500">Cancel</button>
              <button @click="form.post('/admin/integrations', { onSuccess: () => showAdd = false })"
                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg">Save</button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ integrations: Array, groups: Array })
const activeGroup = ref(props.groups[0])
const showAdd = ref(false)
const form = useForm({ provider: '', key: '', value: '', group: 'github', is_active: true })

const filtered = computed(() => props.integrations.filter(i => i.group === activeGroup.value))
const maskValue = v => v ? '•'.repeat(Math.min(v.length, 8)) + v.slice(-4) : '—'

const toggleActive = item => router.patch(`/admin/integrations/${item.id}`, { ...item, is_active: !item.is_active })
const deleteItem = id => { if (confirm('Remove this integration?')) router.delete(`/admin/integrations/${id}`) }
</script>

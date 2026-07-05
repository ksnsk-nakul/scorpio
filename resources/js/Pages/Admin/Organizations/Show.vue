<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto px-6 py-10 space-y-8">

      <!-- Header -->
      <div class="flex items-start justify-between">
        <div>
          <Link href="/admin/organizations" class="text-sm text-slate-400 hover:text-slate-600 mb-2 inline-block">← Organizations</Link>
          <h1 class="text-2xl font-bold text-slate-900">{{ organization.name }}</h1>
          <p class="text-sm text-slate-500 mt-1">{{ organization.description }}</p>
          <a :href="`/org/${organization.slug}`" target="_blank" class="text-xs text-orange-500 hover:underline mt-1 inline-block">
            /org/{{ organization.slug }} ↗
          </a>
        </div>
        <span class="text-xs bg-slate-100 text-slate-600 px-3 py-1 rounded-full">{{ organization.plan }} plan</span>
      </div>

      <!-- Members -->
      <section class="bg-white border border-slate-200 rounded-xl p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Members</h2>

        <!-- Add member form -->
        <form @submit.prevent="addMember" class="mb-4">
          <div class="flex gap-2">
            <input v-model="memberForm.email" type="email" required placeholder="Email address (invite if not registered)"
              class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
            <select v-model="memberForm.role" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
              <option value="viewer">Viewer</option>
              <option value="editor">Editor</option>
            </select>
            <button type="submit" :disabled="memberForm.processing"
              class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-lg disabled:opacity-50">
              Add
            </button>
          </div>
          <p class="text-xs text-slate-400 mt-1">Non-registered emails will receive an invitation link.</p>
        </form>
        <p v-if="memberForm.errors.email" class="text-red-500 text-xs mb-2">{{ memberForm.errors.email }}</p>
        <p v-if="memberForm.errors.members" class="text-red-500 text-xs mb-2">{{ memberForm.errors.members }}</p>

        <div class="divide-y divide-slate-100">
          <div v-for="m in organization.members" :key="m.id" class="flex items-center justify-between py-3">
            <div>
              <p class="text-sm font-medium text-slate-800">{{ m.user.name }}</p>
              <p class="text-xs text-slate-400">{{ m.user.email }} · {{ m.role }}</p>
            </div>
            <button @click="removeMember(m.id)" class="text-xs text-red-500 hover:text-red-700 underline">Remove</button>
          </div>
          <p v-if="!organization.members.length" class="py-3 text-sm text-slate-400">No members yet.</p>
        </div>
      </section>

      <!-- Achievements -->
      <section class="bg-white border border-slate-200 rounded-xl p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Member Achievements</h2>

        <form @submit.prevent="addAchievement" class="grid grid-cols-2 gap-3 mb-4">
          <div>
            <label class="block text-xs text-slate-500 mb-1">Member</label>
            <select v-model="achieveForm.user_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
              <option v-for="m in organization.members" :key="m.user.id" :value="m.user.id">{{ m.user.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Title</label>
            <input v-model="achieveForm.title" type="text" required maxlength="150"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Icon (emoji or class)</label>
            <input v-model="achieveForm.icon" type="text" maxlength="50"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Achieved At</label>
            <input v-model="achieveForm.achieved_at" type="date"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div class="col-span-2 flex justify-end">
            <button type="submit" :disabled="achieveForm.processing"
              class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-lg disabled:opacity-50">
              Add Achievement
            </button>
          </div>
        </form>

        <div class="divide-y divide-slate-100">
          <div v-for="a in organization.achievements" :key="a.id" class="flex items-center justify-between py-3">
            <div class="flex items-center gap-3">
              <span v-if="a.icon" class="text-2xl">{{ a.icon }}</span>
              <div>
                <p class="text-sm font-medium text-slate-800">{{ a.title }}</p>
                <p class="text-xs text-slate-400">{{ a.user?.name }} · {{ a.achieved_at ?? '—' }}</p>
              </div>
            </div>
            <button @click="removeAchievement(a.id)" class="text-xs text-red-500 hover:text-red-700 underline">Remove</button>
          </div>
          <p v-if="!organization.achievements.length" class="py-3 text-sm text-slate-400">No achievements yet.</p>
        </div>
      </section>

    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({ organization: Object })

const memberForm = useForm({ email: '', role: 'viewer' })
const achieveForm = useForm({ user_id: '', title: '', icon: '', achieved_at: '' })

function addMember() {
  memberForm.post(`/admin/organizations/${props.organization.id}/members`, {
    onSuccess: () => memberForm.reset(),
  })
}

function removeMember(memberId) {
  if (!confirm('Remove this member?')) return
  useForm({}).delete(`/admin/organizations/${props.organization.id}/members/${memberId}`)
}

function addAchievement() {
  achieveForm.post(`/admin/organizations/${props.organization.id}/achievements`, {
    onSuccess: () => achieveForm.reset(),
  })
}

function removeAchievement(achievementId) {
  if (!confirm('Remove this achievement?')) return
  useForm({}).delete(`/admin/organizations/${props.organization.id}/achievements/${achievementId}`)
}
</script>

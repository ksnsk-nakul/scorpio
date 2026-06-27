<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">Users</h1>

      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">User</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Email</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Role</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Joined</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50">
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <img :src="user.avatar ?? `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&size=32`"
                    class="w-8 h-8 rounded-full" :alt="user.name" />
                  <span class="font-medium text-slate-800">{{ user.name }}</span>
                </div>
              </td>
              <td class="px-5 py-4 text-slate-500">{{ user.email }}</td>
              <td class="px-5 py-4">
                <select
                  :value="user.roles[0]?.name ?? 'viewer'"
                  @change="changeRole(user, $event.target.value)"
                  class="border border-slate-200 rounded px-2 py-1 text-xs outline-none focus:ring-1 focus:ring-blue-500">
                  <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                </select>
              </td>
              <td class="px-5 py-4 text-slate-400 text-xs">{{ formatDate(user.created_at) }}</td>
              <td class="px-5 py-4">
                <button v-if="user.id !== page.props.auth.user?.id"
                  @click="deleteUser(user.id)"
                  class="text-xs text-red-500 hover:text-red-700">Remove</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ users: Array, roles: Array })

const page = usePage()
const changeRole = (user, role) => router.patch(`/admin/users/${user.id}/role`, { role })
const deleteUser = id => { if (confirm('Remove this user?')) router.delete(`/admin/users/${id}`) }
const formatDate = d => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
</script>

<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">Users</h1>

      <div v-if="$page.props.errors?.amount"
        class="mb-4 bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.errors.amount }}
      </div>

      <div v-if="users.length === 0"
        class="bg-white border border-slate-200 rounded-xl px-6 py-12 text-center text-sm text-slate-400">
        No non-admin users yet.
      </div>

      <div v-else class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">User</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Email</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Role</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Plan</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Joined</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Wallet</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-for="user in users" :key="user.id">
              <tr class="hover:bg-slate-50">
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
                <td class="px-5 py-4">
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                    :class="planBadge(user.plan)">
                    {{ user.plan ?? 'free' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-400 text-xs">{{ formatDate(user.created_at) }}</td>
                <td class="px-5 py-4">
                  <span class="text-xs font-semibold text-slate-700">₹{{ ((user.wallet_balance_paise ?? 0) / 100).toFixed(2) }}</span>
                </td>
                <td class="px-5 py-4 flex items-center gap-3">
                  <button @click="toggleWallet(user.id)"
                    class="text-xs text-blue-500 hover:text-blue-700">Adjust</button>
                  <button @click="deleteUser(user.id)"
                    class="text-xs text-red-500 hover:text-red-700">Remove</button>
                </td>
              </tr>
              <!-- Wallet adjustment row -->
              <tr v-if="expandedWallet === user.id">
                <td colspan="7" class="px-5 py-4 bg-slate-50 border-t border-slate-100">
                  <form @submit.prevent="submitAdjust(user)" class="flex gap-2 flex-wrap items-end">
                    <div class="flex rounded-lg border border-slate-200 overflow-hidden text-xs">
                      <button type="button"
                        @click="adjustForms[user.id].type = 'credit'"
                        class="px-2.5 py-1.5 transition"
                        :class="adjustForms[user.id]?.type === 'credit' ? 'bg-green-500 text-white' : 'bg-white text-slate-600'">
                        + Credit
                      </button>
                      <button type="button"
                        @click="adjustForms[user.id].type = 'debit'"
                        class="px-2.5 py-1.5 transition"
                        :class="adjustForms[user.id]?.type === 'debit' ? 'bg-red-500 text-white' : 'bg-white text-slate-600'">
                        − Debit
                      </button>
                    </div>
                    <input v-model.number="adjustForms[user.id].amount" type="number" min="1" max="100000" step="0.01" required
                      placeholder="₹ Amount"
                      class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs w-28 focus:outline-none focus:ring-1 focus:ring-blue-400" />
                    <input v-model="adjustForms[user.id].note" type="text" required maxlength="255"
                      placeholder="Reason / note"
                      class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs flex-1 min-w-32 focus:outline-none focus:ring-1 focus:ring-blue-400" />
                    <button type="submit"
                      class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white text-xs rounded-lg transition">
                      Apply
                    </button>
                    <button type="button" @click="expandedWallet = null"
                      class="px-3 py-1.5 text-xs text-slate-500 hover:text-slate-700">
                      Cancel
                    </button>
                  </form>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <p class="mt-3 text-xs text-slate-400">
        Admin accounts are not listed here and cannot be modified through this panel.
      </p>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ users: Array, roles: Array })

const expandedWallet = ref(null)

const adjustForms = reactive(
  Object.fromEntries(
    props.users.map(u => [u.id, { type: 'credit', amount: null, note: '' }])
  )
)

const toggleWallet = (id) => {
  expandedWallet.value = expandedWallet.value === id ? null : id
}

const submitAdjust = (user) => {
  const form = adjustForms[user.id]
  router.post(`/admin/users/${user.id}/wallet-adjust`, {
    type:   form.type,
    amount: form.amount,
    note:   form.note,
  }, {
    onSuccess: () => {
      form.amount = null
      form.note   = ''
      expandedWallet.value = null
    },
  })
}

const changeRole = (user, role) => router.patch(`/admin/users/${user.id}/role`, { role })
const deleteUser = id => { if (confirm('Remove this user? This cannot be undone.')) router.delete(`/admin/users/${id}`) }
const formatDate = d => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
const planBadge  = p => ({ free: 'bg-slate-100 text-slate-600', pro: 'bg-blue-100 text-blue-700', business: 'bg-purple-100 text-purple-700' }[p] ?? 'bg-slate-100 text-slate-600')
</script>

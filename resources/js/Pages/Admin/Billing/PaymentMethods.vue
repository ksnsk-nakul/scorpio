<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto">
      <div class="flex items-center gap-3 mb-6">
        <Link href="/admin/billing" class="text-slate-400 hover:text-slate-600 text-sm">← Billing</Link>
        <h1 class="text-2xl font-bold text-slate-800">Payment Methods</h1>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-4 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.flash.success }}
      </div>

      <!-- Saved methods -->
      <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-6">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Saved Methods ({{ methods.length }}/5)</h2>
        <div v-if="methods.length === 0" class="text-slate-400 text-sm text-center py-8">
          No saved payment methods yet.
        </div>
        <div v-else class="space-y-3">
          <div v-for="m in methods" :key="m.id"
            class="flex items-center justify-between border border-slate-100 rounded-xl px-4 py-3 hover:bg-slate-50">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl"
                :class="m.type === 'upi' ? 'bg-purple-50' : 'bg-blue-50'">
                {{ m.type === 'upi' ? '💸' : '💳' }}
              </div>
              <div>
                <p class="text-sm font-medium text-slate-800">{{ m.label }}</p>
                <p class="text-xs text-slate-400 capitalize">
                  {{ m.type }}
                  <span v-if="m.is_default" class="ml-2 text-green-600 font-semibold">· Default</span>
                </p>
              </div>
            </div>
            <div class="flex gap-2">
              <button v-if="!m.is_default"
                @click="setDefault(m.id)"
                class="text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded-lg px-3 py-1.5 transition">
                Set default
              </button>
              <button @click="remove(m.id)"
                class="text-xs text-red-500 hover:text-red-700 border border-red-200 rounded-lg px-3 py-1.5 transition">
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add UPI -->
      <div v-if="methods.length < 5" class="bg-white border border-slate-200 rounded-2xl p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-1">Add UPI ID</h2>
        <p class="text-xs text-slate-400 mb-4">Your UPI ID is encrypted before storage — never visible in plain text.</p>
        <form @submit.prevent="submitUpi" class="space-y-3">
          <div>
            <input v-model="upiForm.upi_id" type="text"
              placeholder="yourname@upi"
              maxlength="100"
              class="w-full border rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="pageErrors.upi_id ? 'border-red-400' : 'border-slate-300'" />
            <p v-if="pageErrors.upi_id" class="text-xs text-red-500 mt-1">{{ pageErrors.upi_id }}</p>
            <p class="text-xs text-slate-400 mt-1">Format: <code>username@bankname</code></p>
          </div>
          <div>
            <input v-model="upiForm.label" type="text"
              placeholder="Label (optional — e.g. Personal UPI)"
              maxlength="50"
              class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
          </div>
          <button type="submit" :disabled="upiForm.processing"
            class="w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl disabled:opacity-50 transition">
            Save UPI ID
          </button>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useForm, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ methods: Array })

const page = usePage()
const pageErrors = computed(() => {
  const errs = page.props.errors ?? {}
  return Object.fromEntries(
    Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
  )
})

const upiForm = useForm({ upi_id: '', label: '' })

const submitUpi = () => {
  upiForm.post('/admin/payment-methods/upi', { onSuccess: () => upiForm.reset() })
}

const setDefault = (id) => router.patch(`/admin/payment-methods/${id}/default`)

const remove = (id) => {
  if (confirm('Remove this payment method?')) {
    router.delete(`/admin/payment-methods/${id}`)
  }
}
</script>

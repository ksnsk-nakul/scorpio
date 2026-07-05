<template>
  <AdminLayout>
    <div class="max-w-3xl mx-auto">
      <div class="flex items-center gap-3 mb-6">
        <Link href="/admin/billing" class="text-slate-400 hover:text-slate-600 text-sm">← Billing</Link>
        <h1 class="text-2xl font-bold text-slate-800">My Wallet</h1>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-4 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.flash.success }}
      </div>

      <!-- Balance card -->
      <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 text-white mb-6 shadow-lg">
        <p class="text-sm text-blue-200 mb-1">Available Balance</p>
        <p class="text-4xl font-bold mb-4">₹{{ (balance / 100).toFixed(2) }}</p>
        <div class="flex gap-3 flex-wrap">
          <a :href="payLink" target="_blank"
            class="inline-flex items-center gap-2 bg-white text-blue-700 text-xs font-semibold px-4 py-2 rounded-lg hover:bg-blue-50 transition">
            🔗 Share top-up link
          </a>
          <button @click="copyLink"
            class="inline-flex items-center gap-2 bg-blue-500 bg-opacity-40 hover:bg-opacity-60 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
            {{ copied ? '✅ Copied!' : '📋 Copy link' }}
          </button>
        </div>
        <p class="text-xs text-blue-200 mt-3">Share your top-up link with anyone — no account needed to send money.</p>
      </div>

      <!-- Transaction history -->
      <div class="bg-white border border-slate-200 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-slate-800">Transaction History</h2>
          <span class="text-xs text-slate-400">{{ transactions.total }} total</span>
        </div>

        <!-- Filters -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
          <input v-model="localFilters.search" @input="applyFilters" type="text"
            placeholder="Search TXN ref, payer, note…"
            class="col-span-2 border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-blue-400" />
          <select v-model="localFilters.category" @change="applyFilters"
            class="border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none">
            <option value="">All categories</option>
            <option value="topup">Top-up</option>
            <option value="subscription">Subscription</option>
            <option value="refund">Refund</option>
            <option value="adjustment">Adjustment</option>
          </select>
          <select v-model="localFilters.type" @change="applyFilters"
            class="border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none">
            <option value="">Credit & Debit</option>
            <option value="credit">Credits only</option>
            <option value="debit">Debits only</option>
          </select>
          <input v-model="localFilters.from" @change="applyFilters" type="date"
            class="border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none" />
          <input v-model="localFilters.to" @change="applyFilters" type="date"
            class="border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none" />
          <button v-if="hasFilters" @click="clearFilters"
            class="col-span-2 text-xs text-slate-500 hover:text-slate-700 border border-slate-200 rounded-lg px-3 py-2">
            Clear filters
          </button>
        </div>

        <!-- Rows -->
        <div v-if="transactions.data.length === 0" class="text-slate-400 text-sm text-center py-12">
          No transactions match your filters.
        </div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="tx in transactions.data" :key="tx.id"
            class="py-3.5 flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-xs text-slate-500">{{ tx.txn_ref }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="categoryColor(tx.category)">
                  {{ tx.category }}
                </span>
              </div>
              <p class="text-sm text-slate-800 mt-0.5 truncate">{{ tx.note || '—' }}</p>
              <p v-if="tx.payer_name" class="text-xs text-slate-400">From: {{ tx.payer_name }}</p>
              <p class="text-xs text-slate-400">{{ formatDate(tx.created_at) }}</p>
              <p v-if="tx.razorpay_payment_id" class="text-xs text-slate-300 font-mono truncate">
                {{ tx.razorpay_payment_id }}
              </p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-sm font-bold" :class="tx.type === 'credit' ? 'text-green-600' : 'text-red-500'">
                {{ tx.type === 'credit' ? '+' : '-' }}₹{{ (tx.amount_paise / 100).toFixed(2) }}
              </p>
              <p class="text-xs text-slate-400">Bal: ₹{{ (tx.balance_after_paise / 100).toFixed(2) }}</p>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="transactions.last_page > 1" class="flex gap-1.5 mt-4 justify-center flex-wrap">
          <Link v-for="link in transactions.links" :key="link.label"
            :href="link.url ?? '#'"
            class="px-3 py-1.5 text-xs rounded-lg border transition"
            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 text-slate-500 hover:border-blue-300'"
            v-html="link.label" />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  balance:      Number,
  transactions: Object,
  filters:      Object,
  payLink:      String,
})

const copied       = ref(false)
const localFilters = reactive({ ...props.filters })

const hasFilters = computed(() =>
  Object.values(localFilters).some(v => v && v !== '')
)

const copyLink = async () => {
  await navigator.clipboard.writeText(props.payLink)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

const applyFilters = () => {
  router.get('/admin/wallet', localFilters, { preserveState: true, replace: true })
}

const clearFilters = () => {
  Object.keys(localFilters).forEach(k => { localFilters[k] = '' })
  applyFilters()
}

const formatDate = (ts) =>
  new Date(ts).toLocaleString('en-IN', {
    day: 'numeric', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })

const categoryColor = (cat) => ({
  topup:        'bg-blue-100 text-blue-700',
  subscription: 'bg-purple-100 text-purple-700',
  refund:       'bg-green-100 text-green-700',
  adjustment:   'bg-orange-100 text-orange-700',
}[cat] ?? 'bg-slate-100 text-slate-600')
</script>

<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Billing & Plans</h1>
        <span class="text-xs px-3 py-1 rounded-full font-medium" :class="planBadge(currentPlan)">
          Current: {{ planLabel(currentPlan) }}
        </span>
      </div>

      <!-- Active subscription -->
      <div v-if="subscription?.status === 'active'" class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-blue-800">{{ planLabel(subscription.plan) }} plan active</p>
          <p v-if="subscription.current_period_end" class="text-xs text-blue-600 mt-0.5">Renews {{ formatDate(subscription.current_period_end) }}</p>
        </div>
        <button @click="cancelPlan" class="text-xs text-red-500 hover:text-red-700 border border-red-200 rounded px-3 py-1.5">Cancel plan</button>
      </div>

      <!-- Wallet balance widget -->
      <div class="mb-6 flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-5 py-3.5">
        <div class="flex items-center gap-3">
          <span class="text-2xl">💰</span>
          <div>
            <p class="text-xs text-slate-500 font-medium">Wallet Balance</p>
            <p class="text-lg font-bold text-slate-800">₹{{ (walletBalance / 100).toFixed(2) }}</p>
          </div>
        </div>
        <div class="flex gap-2">
          <Link href="/admin/wallet"
            class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100 transition">
            View Wallet
          </Link>
          <Link href="/admin/payment-methods"
            class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100 transition">
            Payment Methods
          </Link>
        </div>
      </div>

      <!-- Tab switcher -->
      <div class="flex gap-1 bg-slate-100 rounded-xl p-1 w-fit mb-6">
        <button v-for="tab in ['solo','org']" :key="tab" @click="activeTab = tab"
          class="px-5 py-2 rounded-lg text-sm font-medium transition"
          :class="activeTab === tab ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-700'">
          {{ tab === 'solo' ? 'Solo Plans' : 'Organization Plans' }}
        </button>
      </div>

      <!-- Solo plans -->
      <div v-if="activeTab === 'solo'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="(plan, key) in soloPlans" :key="key"
          class="bg-white border rounded-2xl p-6 flex flex-col relative"
          :class="key === 'pro' ? 'border-blue-400 shadow-lg shadow-blue-50' : 'border-slate-200'">
          <div v-if="plan.badge" class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-semibold px-3 py-0.5 rounded-full">{{ plan.badge }}</div>
          <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-800">{{ plan.name }}</h2>
            <div class="mt-2 flex items-end gap-1">
              <span class="text-3xl font-bold text-slate-900">{{ plan.price === 0 ? 'Free' : '₹' + (plan.price / 100) }}</span>
              <span v-if="plan.price > 0" class="text-sm text-slate-400 mb-1">/month</span>
            </div>
          </div>
          <ul class="space-y-2.5 flex-1 mb-6">
            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm text-slate-600">
              <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              {{ feature }}
            </li>
          </ul>
          <button v-if="key === 'free'" disabled class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">
            {{ currentPlan === 'free' ? 'Current plan' : 'Free tier' }}
          </button>
          <button v-else-if="currentPlan === key" disabled class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">Current plan</button>
          <template v-else>
            <button v-if="plan.price > 0 && walletBalance >= plan.price"
              @click="payWithWallet(key)"
              class="w-full text-sm py-2.5 rounded-xl font-semibold bg-green-600 hover:bg-green-700 text-white transition mb-2">
              💰 Pay with Wallet (₹{{ (plan.price / 100).toFixed(0) }})
            </button>
            <button @click="subscribe(key)" :disabled="subscribing === key"
              class="w-full text-sm py-2.5 rounded-xl font-semibold transition"
              :class="key === 'pro' ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-slate-800 hover:bg-slate-900 text-white'">
              {{ subscribing === key ? 'Processing…' : 'Subscribe' }}
            </button>
          </template>
        </div>
      </div>

      <!-- Org plans -->
      <div v-if="activeTab === 'org'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="(plan, key) in orgPlans" :key="key"
          class="bg-white border rounded-2xl p-6 flex flex-col relative"
          :class="key === 'business' ? 'border-blue-400 shadow-lg shadow-blue-50' : 'border-slate-200'">
          <div v-if="key === 'business'" class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-semibold px-3 py-0.5 rounded-full">Most Popular</div>
          <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-800">{{ plan.name }}</h2>
            <div class="mt-2 flex items-end gap-1">
              <span v-if="plan.price === null" class="text-xl font-bold text-slate-900">₹4,999 base</span>
              <span v-else class="text-3xl font-bold text-slate-900">₹{{ plan.price / 100 }}</span>
              <span class="text-sm text-slate-400 mb-1">/month</span>
            </div>
            <p v-if="plan.price === null" class="text-xs text-slate-400 mt-1">+ usage based on members/workspaces/pages</p>
          </div>
          <ul class="space-y-2.5 flex-1 mb-6">
            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm text-slate-600">
              <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              {{ feature }}
            </li>
          </ul>
          <a v-if="key === 'enterprise'" href="mailto:ksnsk2001@gmail.com?subject=Enterprise%20Plan%20Enquiry"
            class="w-full text-sm py-2.5 rounded-xl font-semibold bg-slate-800 hover:bg-slate-900 text-white text-center block">
            Contact us
          </a>
          <button v-else-if="currentPlan === key" disabled class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">Current plan</button>
          <button v-else @click="subscribe(key)" :disabled="subscribing === key"
            class="w-full text-sm py-2.5 rounded-xl font-semibold bg-blue-600 hover:bg-blue-700 text-white transition">
            {{ subscribing === key ? 'Processing…' : 'Subscribe' }}
          </button>
        </div>
      </div>

      <p class="text-center text-xs text-slate-400 mt-8">All plans include SSL, 99.9% uptime, and data export. Payments secured by Razorpay.</p>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  currentPlan:   String,
  subscription:  Object,
  soloPlans:     Object,
  orgPlans:      Object,
  walletBalance: Number,
})

const activeTab   = ref('solo')
const subscribing = ref(null)

const planLabel = (key) => {
  const all = { ...props.soloPlans, ...props.orgPlans }
  return all[key]?.name ?? key
}

const planBadge = (key) => {
  const map = {
    free: 'bg-slate-100 text-slate-600',
    pro: 'bg-blue-100 text-blue-700',
    creator: 'bg-purple-100 text-purple-700',
    team: 'bg-green-100 text-green-700',
    business: 'bg-orange-100 text-orange-700',
    enterprise: 'bg-red-100 text-red-700',
  }
  return map[key] ?? 'bg-slate-100 text-slate-500'
}

const formatDate = (ts) => new Date(ts).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })

const subscribe = async (planKey) => {
  subscribing.value = planKey
  try {
    const res = await fetch('/admin/billing/order', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        'Accept': 'application/json',
      },
      body: JSON.stringify({ plan: planKey }),
    })
    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error(err.message ?? 'Payment initiation failed.')
    }
    const data = await res.json()
    const rzp = new window.Razorpay({
      key: data.key,
      subscription_id: data.subscription_id,
      name: 'KSNSK Portfolio CMS',
      description: `${planLabel(planKey)} Plan`,
      theme: { color: '#2563EB' },
      handler: (response) => {
        router.post('/admin/billing/verify', response, { onFinish: () => { subscribing.value = null } })
      },
      modal: { ondismiss: () => { subscribing.value = null } },
    })
    rzp.open()
  } catch (e) {
    subscribing.value = null
    alert(e.message ?? 'Could not initiate payment. Please try again.')
  }
}

const payWithWallet = (planKey) => {
  const plan = props.soloPlans[planKey]
  if (!confirm(`Pay ₹${(plan.price / 100).toFixed(0)} from your wallet for the ${plan.name} plan?`)) return
  router.post('/admin/billing/pay-wallet', { plan: planKey })
}

const cancelPlan = () => {
  if (confirm('Cancel your plan? It stays active until end of billing period.')) {
    router.post('/admin/billing/cancel')
  }
}
</script>

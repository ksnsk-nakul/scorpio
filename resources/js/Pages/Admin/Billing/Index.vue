<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Billing & Plans</h1>
        <span class="text-xs px-3 py-1 rounded-full font-medium"
          :class="planBadge(currentPlan)">
          Current: {{ planLabel(currentPlan) }}
        </span>
      </div>

      <!-- Flash messages -->
      <div v-if="$page.props.flash?.success"
        class="mb-6 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error"
        class="mb-6 bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.flash.error }}
      </div>

      <!-- Active subscription info -->
      <div v-if="subscription && subscription.status === 'active'"
        class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-blue-800">
            {{ planLabel(subscription.plan) }} plan active
          </p>
          <p v-if="subscription.current_period_end" class="text-xs text-blue-600 mt-0.5">
            Renews {{ formatDate(subscription.current_period_end) }}
          </p>
        </div>
        <button @click="cancelPlan"
          class="text-xs text-red-500 hover:text-red-700 border border-red-200 rounded px-3 py-1.5">
          Cancel plan
        </button>
      </div>

      <!-- Plan cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="(plan, key) in plans" :key="key"
          class="bg-white border rounded-2xl p-6 flex flex-col relative"
          :class="key === 'pro' ? 'border-blue-400 shadow-lg shadow-blue-50' : 'border-slate-200'">

          <!-- Popular badge -->
          <div v-if="key === 'pro'"
            class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-semibold px-3 py-0.5 rounded-full">
            Most Popular
          </div>

          <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-800">{{ plan.name }}</h2>
            <div class="mt-2 flex items-end gap-1">
              <span class="text-3xl font-bold text-slate-900">
                {{ plan.price === 0 ? 'Free' : '₹' + (plan.price / 100) }}
              </span>
              <span v-if="plan.price > 0" class="text-sm text-slate-400 mb-1">/month</span>
            </div>
          </div>

          <ul class="space-y-2.5 flex-1 mb-6">
            <li v-for="feature in plan.features" :key="feature"
              class="flex items-start gap-2 text-sm text-slate-600">
              <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              {{ feature }}
            </li>
          </ul>

          <button
            v-if="key === 'free'"
            disabled
            class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">
            {{ currentPlan === 'free' ? 'Current plan' : 'Free tier' }}
          </button>
          <button
            v-else-if="currentPlan === key"
            disabled
            class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">
            Current plan
          </button>
          <button
            v-else
            @click="subscribe(key)"
            :disabled="loading === key"
            class="w-full text-sm py-2.5 rounded-xl font-medium transition"
            :class="key === 'pro'
              ? 'bg-blue-600 text-white hover:bg-blue-700'
              : 'bg-slate-900 text-white hover:bg-slate-700'">
            {{ loading === key ? 'Processing...' : 'Subscribe' }}
          </button>
        </div>
      </div>

      <!-- What's included note -->
      <p class="mt-6 text-xs text-center text-slate-400">
        All plans include SSL, 99.9% uptime, and data export. Payments are secured by Razorpay.
      </p>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  plans:       Object,
  currentPlan: String,
  subscription: Object,
  razorpayKey: String,
})

const loading = ref(null)

const planLabel = (key) => ({
  free: 'Free', pro: 'Pro', business: 'Business',
}[key] ?? key)

const planBadge = (key) => ({
  free:     'bg-slate-100 text-slate-600',
  pro:      'bg-blue-100 text-blue-700',
  business: 'bg-purple-100 text-purple-700',
}[key] ?? 'bg-slate-100 text-slate-600')

const formatDate = (d) => new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })

const subscribe = async (planKey) => {
  if (!window.Razorpay) {
    alert('Razorpay SDK failed to load. Please refresh the page and try again.')
    return
  }
  if (!props.razorpayKey || props.razorpayKey.includes('your_key')) {
    alert('Razorpay is not configured yet. Please set RAZORPAY_KEY_ID in your .env file.')
    return
  }

  loading.value = planKey

  try {
    const res = await fetch('/admin/billing/order', {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Accept':       'application/json',
      },
      body: JSON.stringify({ plan: planKey }),
    })

    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error(err.message ?? 'Order creation failed')
    }
    const data = await res.json()

    const rzp = new window.Razorpay({
      key:         props.razorpayKey,
      order_id:    data.order_id,
      amount:      data.amount,
      currency:    data.currency,
      name:        'Portfolio CMS',
      description: planLabel(planKey) + ' plan — monthly',
      theme:       { color: '#2563EB' },
      handler: (response) => {
        loading.value = planKey // keep spinner while Inertia navigates
        router.post('/admin/billing/verify', {
          razorpay_order_id:   response.razorpay_order_id,
          razorpay_payment_id: response.razorpay_payment_id,
          razorpay_signature:  response.razorpay_signature,
        }, { onFinish: () => { loading.value = null } })
      },
      modal: {
        ondismiss: () => { loading.value = null },
      },
    })
    rzp.open()
  } catch (e) {
    loading.value = null
    alert(e.message ?? 'Could not initiate payment. Please try again.')
  }
}

const cancelPlan = () => {
  if (confirm('Cancel your subscription? You will revert to the Free plan.')) {
    router.post('/admin/billing/cancel')
  }
}
</script>

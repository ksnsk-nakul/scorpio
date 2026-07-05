<template>
  <Head :title="done ? 'Payment Successful' : `Top up ${recipient.site_name || recipient.name || 'Wallet'}`" />

  <div class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <!-- Success state -->
    <div v-if="done" class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full text-center">
      <div class="text-5xl mb-4">✅</div>
      <h1 class="text-xl font-bold text-slate-800 mb-2">Payment Successful!</h1>
      <p class="text-slate-500 text-sm mb-6">
        ₹{{ (done.amount_paise / 100).toFixed(2) }} has been added to
        <strong>{{ done.recipient_name }}</strong>'s wallet.
      </p>
      <div class="bg-slate-50 rounded-xl p-4 text-left text-sm space-y-2 mb-6">
        <div class="flex justify-between">
          <span class="text-slate-500">Transaction ID</span>
          <span class="font-mono font-semibold text-slate-800">{{ done.txn_ref }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Paid by</span>
          <span class="text-slate-800">{{ done.payer_name }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Amount</span>
          <span class="font-semibold text-green-700">₹{{ (done.amount_paise / 100).toFixed(2) }}</span>
        </div>
      </div>
      <p class="text-xs text-slate-400">Save your Transaction ID for future reference.</p>
    </div>

    <!-- Top-up form -->
    <div v-else class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full">
      <div class="text-center mb-6">
        <div class="text-4xl mb-3">💰</div>
        <h1 class="text-xl font-bold text-slate-800">Add to Wallet</h1>
        <p class="text-sm text-slate-500 mt-1">
          Top up <strong>{{ recipient.site_name || recipient.name }}</strong>'s wallet
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Your Name *</label>
          <input v-model="form.payer_name" type="text" required maxlength="100"
            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
            :class="errors.payer_name ? 'border-red-400' : ''"
            placeholder="John Doe" />
          <p v-if="errors.payer_name" class="text-xs text-red-500 mt-1">{{ errors.payer_name[0] }}</p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Your Email *</label>
          <input v-model="form.payer_email" type="email" required maxlength="255"
            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
            :class="errors.payer_email ? 'border-red-400' : ''"
            placeholder="john@example.com" />
          <p v-if="errors.payer_email" class="text-xs text-red-500 mt-1">{{ errors.payer_email[0] }}</p>
          <p class="text-xs text-slate-400 mt-1">Stored securely and encrypted.</p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Amount (₹) *</label>
          <div class="flex gap-2 flex-wrap mb-2">
            <button v-for="a in [100, 200, 500, 1000, 2000]" :key="a" type="button"
              @click="form.amount = a"
              class="px-3 py-1.5 rounded-lg text-xs border transition"
              :class="form.amount === a ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 text-slate-600 hover:border-blue-300'">
              ₹{{ a }}
            </button>
          </div>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₹</span>
            <input v-model.number="form.amount" type="number" min="100" max="10000" step="1" required
              class="w-full pl-8 pr-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="errors.amount_paise ? 'border-red-400' : ''" />
          </div>
          <p v-if="errors.amount_paise" class="text-xs text-red-500 mt-1">{{ errors.amount_paise[0] }}</p>
          <p class="text-xs text-slate-400 mt-1">Min ₹100 · Max ₹10,000</p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Note / Purpose</label>
          <input v-model="form.note" type="text" maxlength="255"
            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
            placeholder="e.g. Project payment, Gift, etc." />
        </div>

        <div v-if="generalError" class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
          {{ generalError }}
        </div>

        <button type="submit" :disabled="paying"
          class="w-full py-3 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold rounded-xl transition text-sm">
          {{ paying ? 'Opening payment…' : `Pay ₹${form.amount || 0} via Razorpay` }}
        </button>
      </form>

      <p class="text-xs text-slate-400 text-center mt-4">
        Payments secured by Razorpay · Your email is encrypted and stored securely.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({
  recipient:   { type: Object, required: true },
  razorpayKey: { type: String, default: '' },
  done:        { type: Object, default: null },
})

const form = reactive({
  payer_name:  '',
  payer_email: '',
  amount:      500,
  note:        '',
})

const errors       = ref({})
const generalError = ref(null)
const paying       = ref(false)

const username = props.recipient.username

onMounted(() => {
  if (document.querySelector('script[src*="checkout.razorpay.com"]')) return
  const s = document.createElement('script')
  s.src = 'https://checkout.razorpay.com/v1/checkout.js'
  document.head.appendChild(s)
})

const submit = async () => {
  errors.value       = {}
  generalError.value = null
  paying.value       = true

  try {
    const res = await fetch(`/pay/${username}/wallet/order`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Accept':       'application/json',
      },
      body: JSON.stringify({
        amount_paise: form.amount * 100,
        payer_name:   form.payer_name,
        payer_email:  form.payer_email,
        note:         form.note || null,
      }),
    })

    if (res.status === 422) {
      const data = await res.json()
      errors.value = data.errors ?? {}
      paying.value = false
      return
    }

    if (!res.ok) throw new Error('Could not initiate payment.')

    const data = await res.json()

    const rzp = new window.Razorpay({
      key:         data.key,
      order_id:    data.order_id,
      amount:      data.amount,
      currency:    'INR',
      name:        props.recipient.site_name || props.recipient.name || 'Wallet Top-up',
      description: form.note || 'Wallet Top-up',
      prefill: {
        name:  data.payer_name,
        email: data.payer_email,
      },
      theme: { color: '#2563EB' },
      handler: (response) => {
        router.post(
          `/pay/${username}/wallet/verify`,
          response,
          { onFinish: () => { paying.value = false } }
        )
      },
      modal: { ondismiss: () => { paying.value = false } },
    })

    rzp.open()
  } catch (e) {
    generalError.value = e.message ?? 'Something went wrong. Please try again.'
    paying.value = false
  }
}
</script>

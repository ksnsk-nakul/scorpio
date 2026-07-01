<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 flex items-center justify-center px-4 py-16">
    <div class="w-full max-w-md">

      <!-- Header -->
      <div class="text-center mb-8">
        <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-800">Support my work</h1>
        <p class="text-slate-500 mt-2 text-sm leading-relaxed">
          If you've found my work helpful, consider buying me a coffee ☕<br>
          Every bit keeps the projects going!
        </p>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">

        <!-- Thank you state -->
        <div v-if="paid" class="text-center py-6">
          <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h2 class="text-lg font-semibold text-slate-800 mb-1">Thank you so much! 🎉</h2>
          <p class="text-slate-500 text-sm">Your support means the world. You're awesome.</p>
          <button @click="paid = false" class="mt-5 text-xs text-blue-600 hover:underline">Support again?</button>
        </div>

        <template v-else>
          <!-- Preset amounts -->
          <p class="text-xs font-medium text-slate-500 mb-3">Choose an amount</p>
          <div class="grid grid-cols-3 gap-2 mb-4">
            <button
              v-for="preset in presets"
              :key="preset"
              @click="selectPreset(preset)"
              class="py-2.5 rounded-xl text-sm font-semibold border transition"
              :class="amount === preset && !customMode
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-slate-50 text-slate-700 border-slate-200 hover:border-blue-300'">
              ₹{{ preset }}
            </button>
          </div>

          <!-- Custom amount -->
          <div class="mb-4">
            <label class="text-xs font-medium text-slate-500 block mb-1.5">Or enter custom amount</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-medium text-sm">₹</span>
              <input
                v-model="customAmount"
                @input="customMode = true"
                type="number"
                min="1"
                max="5000"
                placeholder="Any amount"
                class="w-full border border-slate-200 rounded-xl pl-7 pr-4 py-2.5 text-sm outline-none focus:border-blue-400 transition" />
            </div>
            <p v-if="amountError" class="text-xs text-red-500 mt-1">{{ amountError }}</p>
          </div>

          <!-- Donor info (optional) -->
          <div class="space-y-3 mb-5">
            <input v-model="name" type="text" placeholder="Your name (optional)"
              class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400 transition" />
            <input v-model="note" type="text" placeholder="A note for me (optional)"
              class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400 transition" />
          </div>

          <!-- Pay button -->
          <button
            @click="pay"
            :disabled="loading || !effectiveAmount"
            class="w-full bg-blue-600 text-white font-semibold py-3 rounded-xl hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed text-sm">
            {{ loading ? 'Opening checkout…' : `Donate ₹${effectiveAmount || '—'}` }}
          </button>

          <!-- Divider -->
          <div class="flex items-center gap-3 my-4">
            <div class="flex-1 border-t border-slate-100" />
            <span class="text-xs text-slate-300">or</span>
            <div class="flex-1 border-t border-slate-100" />
          </div>

          <!-- Razorpay.me link -->
          <a
            :href="meUrl"
            target="_blank"
            rel="noopener"
            class="flex items-center justify-center gap-2 w-full border border-slate-200 text-slate-600 text-sm font-medium py-2.5 rounded-xl hover:bg-slate-50 transition">
            <img src="https://razorpay.com/favicon.ico" class="w-4 h-4" alt="" />
            Pay via Razorpay.me
          </a>
        </template>
      </div>

      <p class="text-center text-xs text-slate-400 mt-5">
        Secured by <span class="font-medium">Razorpay</span> · UPI, Cards, Netbanking & Wallets accepted
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  razorpayKey: String,
  meUrl:       String,
})

const presets     = [50, 100, 200]
const amount      = ref(100)
const customMode  = ref(false)
const customAmount = ref('')
const name        = ref('')
const note        = ref('')
const loading     = ref(false)
const paid        = ref(false)
const amountError = ref('')

const effectiveAmount = computed(() => {
  if (customMode.value && customAmount.value) return parseInt(customAmount.value)
  return amount.value
})

const selectPreset = (val) => {
  amount.value = val
  customMode.value = false
  customAmount.value = ''
  amountError.value = ''
}

const pay = async () => {
  amountError.value = ''
  const rupees = effectiveAmount.value
  if (!rupees || rupees < 1) { amountError.value = 'Minimum donation is ₹1.'; return }
  if (rupees > 5000)         { amountError.value = 'Maximum donation is ₹5000.'; return }

  loading.value = true

  try {
    const res = await fetch('/donate/order', {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Accept':       'application/json',
      },
      body: JSON.stringify({
        amount: rupees * 100, // convert to paise
        name:   name.value || null,
        note:   note.value || null,
      }),
    })

    if (!res.ok) throw new Error('Order creation failed')
    const data = await res.json()

    const rzp = new window.Razorpay({
      key:         props.razorpayKey,
      order_id:    data.order_id,
      amount:      data.amount,
      currency:    data.currency,
      name:        'Support Nakul',
      description: note.value || 'Thank you for your support!',
      image:       'https://ui-avatars.com/api/?name=Nakul+Sri+Kuber&background=2563EB&color=fff&size=80',
      prefill:     { name: name.value || '' },
      theme:       { color: '#2563EB' },
      handler: async (response) => {
        // Verify on backend
        const vRes = await fetch('/donate/verify', {
          method:  'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            'Accept':       'application/json',
          },
          body: JSON.stringify({
            razorpay_order_id:   response.razorpay_order_id,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_signature:  response.razorpay_signature,
          }),
        })
        const vData = await vRes.json()
        paid.value = vData.success
        loading.value = false
      },
      modal: {
        ondismiss: () => { loading.value = false }
      },
    })
    rzp.open()
  } catch (e) {
    loading.value = false
    alert('Could not initiate payment. Please try again or use the Razorpay.me link below.')
  }
}
</script>

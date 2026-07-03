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
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 text-center">

        <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center mx-auto mb-4">
          <img src="https://razorpay.com/favicon.ico" class="w-7 h-7" alt="Razorpay" />
        </div>

        <h2 class="text-base font-semibold text-slate-700 mb-1">Support me via Razorpay</h2>
        <p class="text-sm text-slate-400 mb-6">UPI · Cards · Netbanking · Wallets accepted</p>

        <a
          :href="meUrl"
          target="_blank"
          rel="noopener"
          class="flex items-center justify-center gap-2 w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition text-sm">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
          Support on Razorpay.me
        </a>

        <p class="text-xs text-slate-400 mt-4">
          You'll be taken to Razorpay's secure payment page.
        </p>
      </div>

      <p class="text-center text-xs text-slate-400 mt-5">
        Secured by <span class="font-medium">Razorpay</span> · UPI, Cards, Netbanking & Wallets accepted
      </p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  meUrl: String,
})
</script>

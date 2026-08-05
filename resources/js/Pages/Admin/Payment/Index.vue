<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Payment Gateway</h1>
        <span class="text-xs px-3 py-1 rounded-full font-medium"
          :class="configured
            ? (environment === 'live' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700')
            : 'bg-red-100 text-red-600'">
          {{ configured ? (environment === 'live' ? 'Live mode' : 'Test mode') : 'Not configured' }}
        </span>
      </div>

      <!-- Demo lock -->
      <div v-if="isDemo" class="mb-5 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl px-4 py-3">
        Payment settings are read-only in demo mode.
      </div>

      <form @submit.prevent="!isDemo && save()"
        :class="isDemo ? 'opacity-60 pointer-events-none select-none' : ''">

        <!-- Provider card -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-5">
          <div class="flex items-center gap-3 mb-5">
            <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-xs">R</div>
            <div>
              <p class="text-sm font-semibold text-slate-800">Razorpay</p>
              <p class="text-xs text-slate-400">Payment processing for India</p>
            </div>
          </div>

          <!-- Environment toggle -->
          <div class="mb-6">
            <label class="block text-xs font-medium text-slate-500 mb-2 uppercase tracking-wide">Environment</label>
            <div class="flex rounded-xl border border-slate-200 overflow-hidden w-fit">
              <button type="button"
                @click="form.environment = 'test'"
                class="px-6 py-2.5 text-sm font-medium transition"
                :class="form.environment === 'test'
                  ? 'bg-amber-500 text-white'
                  : 'bg-white text-slate-500 hover:bg-slate-50'">
                Test
              </button>
              <button type="button"
                @click="form.environment = 'live'"
                class="px-6 py-2.5 text-sm font-medium transition border-l border-slate-200"
                :class="form.environment === 'live'
                  ? 'bg-green-600 text-white'
                  : 'bg-white text-slate-500 hover:bg-slate-50'">
                Live
              </button>
            </div>
            <p class="text-xs text-slate-400 mt-2">
              {{ form.environment === 'test'
                ? 'Test mode — payments are simulated. Keys start with rzp_test_'
                : 'Live mode — real money is charged. Keys start with rzp_live_' }}
            </p>
          </div>

          <!-- Keys -->
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1.5">Key ID</label>
              <div class="relative">
                <input
                  v-model="form.RAZORPAY_KEY_ID"
                  type="text"
                  :placeholder="form.environment === 'test' ? 'rzp_test_...' : 'rzp_live_...'"
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono outline-none focus:ring-2 focus:ring-blue-500"
                  :class="keyIdError ? 'border-red-300' : ''" />
                <span v-if="keyIdMismatch"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-amber-500">
                  ⚠ wrong environment
                </span>
              </div>
              <p v-if="form.errors.RAZORPAY_KEY_ID" class="text-xs text-red-500 mt-1">{{ form.errors.RAZORPAY_KEY_ID }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1.5">Key Secret</label>
              <div class="relative">
                <input
                  v-model="form.RAZORPAY_KEY_SECRET"
                  :type="showSecret ? 'text' : 'password'"
                  placeholder="••••••••••••••••••••"
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 pr-20 text-sm font-mono outline-none focus:ring-2 focus:ring-blue-500" />
                <button type="button"
                  @click="showSecret = !showSecret"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-600">
                  {{ showSecret ? 'Hide' : 'Show' }}
                </button>
              </div>
              <p v-if="form.errors.RAZORPAY_KEY_SECRET" class="text-xs text-red-500 mt-1">{{ form.errors.RAZORPAY_KEY_SECRET }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1.5">
                Razorpay.me handle
                <span class="text-slate-300 font-normal ml-1">(optional)</span>
              </label>
              <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-blue-500">
                <span class="px-3 py-3 text-sm text-slate-400 bg-slate-50 border-r border-slate-200 whitespace-nowrap">razorpay.me/@</span>
                <input
                  v-model="form.RAZORPAY_ME_HANDLE"
                  type="text"
                  placeholder="yourhandle"
                  class="flex-1 px-3 py-3 text-sm font-mono outline-none bg-white" />
              </div>
              <p class="text-xs text-slate-400 mt-1">Used as fallback on the donation page when Razorpay checkout can't load.</p>
            </div>
          </div>
        </div>

        <!-- Where these values go -->
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 mb-5 text-xs text-slate-500 space-y-1.5">
          <p class="font-medium text-slate-600 mb-2">These values are written to your <code class="font-mono bg-slate-200 px-1 rounded">.env</code> file:</p>
          <div class="font-mono space-y-1">
            <p>RAZORPAY_KEY_ID=<span class="text-slate-800">{{ maskedKeyId }}</span></p>
            <p>RAZORPAY_KEY_SECRET=<span class="text-slate-800">{{ maskedSecret }}</span></p>
            <p v-if="form.RAZORPAY_ME_HANDLE">RAZORPAY_ME_HANDLE=<span class="text-slate-800">{{ form.RAZORPAY_ME_HANDLE }}</span></p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button type="submit"
            :disabled="form.processing || isDemo"
            class="bg-blue-600 text-white text-sm px-6 py-2.5 rounded-xl hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
            {{ form.processing ? 'Saving…' : 'Save configuration' }}
          </button>
          <a href="https://dashboard.razorpay.com/app/keys" target="_blank" rel="noopener"
            class="text-xs text-slate-400 hover:text-slate-600 underline">
            Get keys from Razorpay dashboard ↗
          </a>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  values:      Object,
  environment: String,
  configured:  Boolean,
})

const isDemo     = usePage().props.demo
const showSecret = ref(false)

const form = useForm({
  environment:         props.environment,
  RAZORPAY_KEY_ID:     props.values.RAZORPAY_KEY_ID     ?? '',
  RAZORPAY_KEY_SECRET: props.values.RAZORPAY_KEY_SECRET ?? '',
  RAZORPAY_ME_HANDLE:  props.values.RAZORPAY_ME_HANDLE  ?? '',
})

const save = () => form.post('/admin/payment')

const keyIdMismatch = computed(() => {
  const id  = form.RAZORPAY_KEY_ID
  const env = form.environment
  if (!id) return false
  return env === 'test' ? !id.startsWith('rzp_test_') : !id.startsWith('rzp_live_')
})

const keyIdError = computed(() => keyIdMismatch.value || !!form.errors.RAZORPAY_KEY_ID)

const maskedKeyId = computed(() => {
  const v = form.RAZORPAY_KEY_ID
  return v ? v : '(not set)'
})

const maskedSecret = computed(() => {
  const v = form.RAZORPAY_KEY_SECRET
  if (!v) return '(not set)'
  return v.slice(0, 4) + '•'.repeat(Math.max(0, v.length - 4))
})
</script>

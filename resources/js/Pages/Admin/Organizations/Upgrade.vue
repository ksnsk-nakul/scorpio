<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto px-6 py-10">
      <div class="mb-8">
        <Link href="/admin/organizations" class="text-sm text-slate-400 hover:text-slate-600">← Organizations</Link>
        <h1 class="text-2xl font-bold text-slate-900 mt-2">Create an Organization</h1>
        <p class="text-slate-500 text-sm mt-1">Upgrade to an org plan and create your first organization.</p>
      </div>

      <!-- Step 1: Plan picker -->
      <div v-if="step === 1" class="space-y-4">
        <h2 class="text-base font-semibold text-slate-700 mb-4">Choose a plan</h2>
        <div v-for="plan in orgPlans" :key="plan.key"
          @click="selectPlan(plan)"
          :class="[
            'border-2 rounded-xl p-5 cursor-pointer transition-all',
            selected?.key === plan.key ? 'border-orange-500 bg-orange-50' : 'border-slate-200 hover:border-orange-300 bg-white'
          ]">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-semibold text-slate-900">{{ plan.name }}</p>
              <p class="text-xs text-slate-500 mt-0.5">Up to {{ plan.member_limit ?? 'unlimited' }} members</p>
              <p class="text-sm text-slate-600 mt-1">{{ plan.description }}</p>
            </div>
            <div class="text-right flex-shrink-0 ml-4">
              <p v-if="plan.price" class="text-lg font-bold text-slate-900">
                ₹{{ (plan.price / 100).toLocaleString('en-IN') }}<span class="text-xs font-normal text-slate-400">/mo</span>
              </p>
              <p v-else class="text-sm font-semibold text-orange-600">Contact us</p>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-4">
          <button @click="step = 2" :disabled="!selected"
            class="px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg disabled:opacity-40 transition-colors">
            Continue →
          </button>
        </div>
      </div>

      <!-- Step 2: Org details + pay -->
      <div v-if="step === 2" class="space-y-5">
        <div class="flex items-center gap-3 mb-2">
          <button @click="step = 1" class="text-sm text-slate-400 hover:text-slate-600">← Back</button>
          <span class="text-sm font-medium text-orange-600">{{ selected?.name }} plan selected</span>
        </div>

        <!-- Enterprise: contact only -->
        <div v-if="selected?.price === null" class="bg-orange-50 border border-orange-200 rounded-xl p-6 text-center">
          <p class="font-semibold text-slate-800 mb-2">Enterprise Plan</p>
          <p class="text-sm text-slate-600 mb-4">Enterprise pricing is custom. Get in touch and we'll set you up.</p>
          <a href="mailto:ksnsk2001@gmail.com?subject=Enterprise Plan Enquiry"
            class="inline-flex items-center px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg transition-colors">
            Contact Us
          </a>
        </div>

        <!-- Team / Business: form + pay -->
        <template v-else>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Organization Name</label>
            <input v-model="form.org_name" type="text" required maxlength="100"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
            <p v-if="errors.org_name" class="text-red-500 text-xs mt-1">{{ errors.org_name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Slug <span class="text-slate-400 font-normal">(letters, numbers, hyphens)</span></label>
            <div class="flex items-center border border-slate-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-orange-400">
              <span class="px-3 py-2 bg-slate-50 text-slate-400 text-sm border-r border-slate-300">/org/</span>
              <input v-model="form.org_slug" type="text" required maxlength="100" pattern="[a-z0-9\-]+"
                class="flex-1 px-3 py-2 text-sm focus:outline-none" />
            </div>
            <p v-if="errors.org_slug" class="text-red-500 text-xs mt-1">{{ errors.org_slug }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description <span class="text-slate-400 font-normal">(optional)</span></label>
            <textarea v-model="form.org_description" maxlength="500" rows="2"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" />
          </div>

          <p v-if="errors.payment" class="text-red-600 text-sm bg-red-50 border border-red-200 rounded-lg px-4 py-3">{{ errors.payment }}</p>

          <div class="flex justify-end pt-2">
            <button @click="pay" :disabled="paying || !form.org_name || !form.org_slug"
              class="px-6 py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg disabled:opacity-40 transition-colors">
              {{ paying ? 'Processing…' : `Pay ₹${(selected.price / 100).toLocaleString('en-IN')} & Create` }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, reactive } from 'vue'

const props = defineProps({ orgPlans: Array })

const step = ref(1)
const selected = ref(null)
const paying = ref(false)
const errors = reactive({})

const form = reactive({ org_name: '', org_slug: '', org_description: '' })

function selectPlan(plan) {
  selected.value = plan
}

async function pay() {
  paying.value = true
  errors.payment = null
  errors.org_name = null
  errors.org_slug = null

  try {
    const res = await fetch('/admin/org-upgrade/order', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
      },
      body: JSON.stringify({ plan: selected.value.key, ...form }),
    })

    const data = await res.json()

    if (!res.ok) {
      const errs = data.errors ?? {}
      Object.assign(errors, errs)
      if (data.message && !Object.keys(errs).length) errors.payment = data.message
      return
    }

    if (data.contact) {
      errors.payment = 'Please contact us for enterprise pricing.'
      return
    }

    const rzp = new window.Razorpay({
      key: data.key,
      amount: data.amount,
      currency: 'INR',
      order_id: data.order_id,
      name: form.org_name,
      description: `${selected.value.name} Plan`,
      handler(response) {
        router.post('/admin/org-upgrade/verify', {
          razorpay_order_id:   response.razorpay_order_id,
          razorpay_payment_id: response.razorpay_payment_id,
          razorpay_signature:  response.razorpay_signature,
        })
      },
      modal: { ondismiss: () => { paying.value = false } },
    })
    rzp.open()
  } catch (err) {
    errors.payment = 'Something went wrong. Please try again.'
  } finally {
    paying.value = false
  }
}
</script>

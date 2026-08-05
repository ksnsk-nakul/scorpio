<template>
  <div>
    <!-- Tab switcher -->
    <div class="flex gap-1 bg-slate-100 rounded-xl p-1 mb-6">
      <button v-for="tab in tabs" :key="tab.id" type="button"
        @click="activeTab = tab.id"
        class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-medium transition"
        :class="activeTab === tab.id
          ? 'bg-white text-slate-800 shadow-sm'
          : 'text-slate-500 hover:text-slate-700'">
        <span>{{ tab.icon }}</span>
        <span>{{ tab.label }}</span>
        <span class="w-2 h-2 rounded-full ml-1"
          :class="tab.configured ? 'bg-green-400' : 'bg-slate-300'" />
      </button>
    </div>

    <!-- Payment tab -->
    <div v-show="activeTab === 'payment'">
      <div class="bg-white border border-slate-200 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold text-sm">R</div>
          <div>
            <p class="text-sm font-semibold text-slate-800">Razorpay</p>
            <p class="text-xs text-slate-400">Payment processing for India</p>
          </div>
          <span class="ml-auto text-xs px-2.5 py-1 rounded-full font-medium"
            :class="payment.configured
              ? (payForm.environment === 'live' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700')
              : 'bg-red-100 text-red-500'">
            {{ payment.configured ? (payForm.environment === 'live' ? 'Live' : 'Test') : 'Not set' }}
          </span>
        </div>

        <form @submit.prevent="!isDemo && savePayment()" :class="isDemo ? 'opacity-60 pointer-events-none' : ''">
          <div class="mb-5">
            <label class="block text-xs font-medium text-slate-500 mb-2 uppercase tracking-wide">Environment</label>
            <div class="flex rounded-xl border border-slate-200 overflow-hidden w-fit">
              <button type="button" @click="payForm.environment = 'test'"
                class="px-5 py-2 text-sm font-medium transition"
                :class="payForm.environment === 'test' ? 'bg-amber-500 text-white' : 'bg-white text-slate-500 hover:bg-slate-50'">
                Test
              </button>
              <button type="button" @click="payForm.environment = 'live'"
                class="px-5 py-2 text-sm font-medium border-l border-slate-200 transition"
                :class="payForm.environment === 'live' ? 'bg-green-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50'">
                Live
              </button>
            </div>
            <p class="text-xs text-slate-400 mt-1.5">
              {{ payForm.environment === 'test' ? 'Keys start with rzp_test_' : 'Keys start with rzp_live_' }}
            </p>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1.5">Key ID</label>
              <input v-model="payForm.RAZORPAY_KEY_ID" type="text"
                :placeholder="payForm.environment === 'test' ? 'rzp_test_...' : 'rzp_live_...'"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-400"
                :class="pageErrors.RAZORPAY_KEY_ID ? 'border-red-400' : ''" />
              <p v-if="pageErrors.RAZORPAY_KEY_ID" class="text-xs text-red-500 mt-1">{{ pageErrors.RAZORPAY_KEY_ID }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1.5">Key Secret</label>
              <input v-model="payForm.RAZORPAY_KEY_SECRET" type="password"
                placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1.5">Payment Page Handle <span class="text-slate-300">(optional)</span></label>
              <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-blue-400">
                <span class="px-3 py-2.5 text-xs text-slate-400 bg-slate-50 border-r border-slate-200">razorpay.me/</span>
                <input v-model="payForm.RAZORPAY_ME_HANDLE" type="text"
                  placeholder="your-handle"
                  class="flex-1 px-3 py-2.5 text-sm focus:outline-none" />
              </div>
            </div>
          </div>

          <button type="submit" class="mt-5 w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl transition">
            Save Payment Settings
          </button>
        </form>
      </div>
    </div>

    <!-- Mail tab -->
    <div v-show="activeTab === 'mail'">
      <div class="bg-white border border-slate-200 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-9 h-9 rounded-xl bg-indigo-500 flex items-center justify-center text-white text-sm">M</div>
          <div>
            <p class="text-sm font-semibold text-slate-800">Mail (SMTP)</p>
            <p class="text-xs text-slate-400">Transactional email delivery</p>
          </div>
          <span class="ml-auto text-xs px-2.5 py-1 rounded-full font-medium"
            :class="mail.configured ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-500'">
            {{ mail.configured ? 'Configured' : 'Not set' }}
          </span>
        </div>

        <form @submit.prevent="!isDemo && saveMail()" :class="isDemo ? 'opacity-60 pointer-events-none' : ''">
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Mailer</label>
                <select v-model="mailForm.MAIL_MAILER"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                  <option value="smtp">SMTP</option>
                  <option value="sendmail">Sendmail</option>
                  <option value="mailgun">Mailgun</option>
                  <option value="ses">Amazon SES</option>
                  <option value="postmark">Postmark</option>
                  <option value="log">Log (dev)</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Encryption</label>
                <select v-model="mailForm.MAIL_ENCRYPTION"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                  <option value="starttls">STARTTLS</option>
                  <option value="">None</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-500 mb-1.5">SMTP Host</label>
                <input v-model="mailForm.MAIL_HOST" type="text" placeholder="smtp.example.com"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                  :class="pageErrors.MAIL_HOST ? 'border-red-400' : ''" />
                <p v-if="pageErrors.MAIL_HOST" class="text-xs text-red-500 mt-1">{{ pageErrors.MAIL_HOST }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Port</label>
                <input v-model.number="mailForm.MAIL_PORT" type="number" placeholder="587"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Username</label>
                <input v-model="mailForm.MAIL_USERNAME" type="text" placeholder="user@example.com"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Password</label>
                <input v-model="mailForm.MAIL_PASSWORD" type="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">From Address</label>
                <input v-model="mailForm.MAIL_FROM_ADDRESS" type="email" placeholder="hello@example.com"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                  :class="pageErrors.MAIL_FROM_ADDRESS ? 'border-red-400' : ''" />
                <p v-if="pageErrors.MAIL_FROM_ADDRESS" class="text-xs text-red-500 mt-1">{{ pageErrors.MAIL_FROM_ADDRESS }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">From Name</label>
                <input v-model="mailForm.MAIL_FROM_NAME" type="text" placeholder="My App"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
            </div>
          </div>

          <!-- Test mail section -->
          <div class="mt-5 border border-slate-200 rounded-xl p-4 bg-slate-50">
            <p class="text-xs font-semibold text-slate-600 mb-3">Test configuration before saving</p>
            <div class="flex gap-2">
              <input v-model="testMailTo" type="email" placeholder="Send test to…"
                class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white" />
              <button type="button" @click="sendTestMail"
                :disabled="testMailLoading || !testMailTo"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition flex-shrink-0"
                :class="testMailLoading || !testMailTo
                  ? 'bg-slate-200 text-slate-400 cursor-not-allowed'
                  : 'bg-indigo-600 hover:bg-indigo-700 text-white'">
                {{ testMailLoading ? 'Sending…' : 'Send test' }}
              </button>
            </div>
            <div v-if="testMailResult" class="mt-2.5 text-xs px-3 py-2 rounded-lg"
              :class="testMailResult.success ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'">
              {{ testMailResult.message }}
            </div>
          </div>

          <button type="submit" class="mt-4 w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl transition">
            Save Mail Settings
          </button>
        </form>
      </div>
    </div>

    <!-- SMS tab -->
    <div v-show="activeTab === 'sms'">
      <div class="bg-white border border-slate-200 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-9 h-9 rounded-xl bg-red-500 flex items-center justify-center text-white text-sm font-bold">T</div>
          <div>
            <p class="text-sm font-semibold text-slate-800">Twilio SMS</p>
            <p class="text-xs text-slate-400">Send SMS notifications via Twilio</p>
          </div>
          <span class="ml-auto text-xs px-2.5 py-1 rounded-full font-medium"
            :class="sms.configured ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-500'">
            {{ sms.configured ? 'Configured' : 'Not set' }}
          </span>
        </div>

        <form @submit.prevent="!isDemo && saveSms()" :class="isDemo ? 'opacity-60 pointer-events-none' : ''">
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1.5">Account SID</label>
              <input v-model="smsForm.TWILIO_SID" type="text" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-400"
                :class="pageErrors.TWILIO_SID ? 'border-red-400' : ''" />
              <p v-if="pageErrors.TWILIO_SID" class="text-xs text-red-500 mt-1">{{ pageErrors.TWILIO_SID }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1.5">Auth Token</label>
              <input v-model="smsForm.TWILIO_AUTH_TOKEN" type="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1.5">From Number</label>
              <input v-model="smsForm.TWILIO_FROM" type="text" placeholder="+1234567890"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-400"
                :class="pageErrors.TWILIO_FROM ? 'border-red-400' : ''" />
              <p class="text-xs text-slate-400 mt-1">E.164 format, e.g. +14155552671</p>
              <p v-if="pageErrors.TWILIO_FROM" class="text-xs text-red-500 mt-1">{{ pageErrors.TWILIO_FROM }}</p>
            </div>
          </div>

          <div class="mt-4 p-4 bg-slate-50 rounded-xl text-xs text-slate-500 space-y-1">
            <p class="font-medium text-slate-600">Setup instructions</p>
            <p>1. Create a free account at twilio.com</p>
            <p>2. Get your Account SID + Auth Token from the Console Dashboard</p>
            <p>3. Buy or verify a phone number to use as your sender</p>
          </div>

          <button type="submit" class="mt-5 w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl transition">
            Save SMS Settings
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  payment: Object,
  mail:    Object,
  sms:     Object,
})

const page   = usePage()
const isDemo = computed(() => !!page.props.demo?.enabled)

const pageErrors = computed(() => {
  const errs = page.props.errors ?? {}
  return Object.fromEntries(
    Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
  )
})

const activeTab = ref('payment')

const tabs = computed(() => [
  { id: 'payment', label: 'Payment', icon: 'P', configured: props.payment.configured },
  { id: 'mail',    label: 'Mail',    icon: 'M', configured: props.mail.configured },
  { id: 'sms',     label: 'SMS',     icon: 'S', configured: props.sms.configured },
])

const payForm = reactive({
  environment:         props.payment.environment,
  RAZORPAY_KEY_ID:     props.payment.RAZORPAY_KEY_ID,
  RAZORPAY_KEY_SECRET: props.payment.RAZORPAY_KEY_SECRET,
  RAZORPAY_ME_HANDLE:  props.payment.RAZORPAY_ME_HANDLE,
})

const mailForm = reactive({
  MAIL_MAILER:       props.mail.MAIL_MAILER,
  MAIL_HOST:         props.mail.MAIL_HOST,
  MAIL_PORT:         props.mail.MAIL_PORT,
  MAIL_USERNAME:     props.mail.MAIL_USERNAME,
  MAIL_PASSWORD:     props.mail.MAIL_PASSWORD,
  MAIL_ENCRYPTION:   props.mail.MAIL_ENCRYPTION,
  MAIL_FROM_ADDRESS: props.mail.MAIL_FROM_ADDRESS,
  MAIL_FROM_NAME:    props.mail.MAIL_FROM_NAME,
})

const smsForm = reactive({
  TWILIO_SID:        props.sms.TWILIO_SID,
  TWILIO_AUTH_TOKEN: props.sms.TWILIO_AUTH_TOKEN,
  TWILIO_FROM:       props.sms.TWILIO_FROM,
})

const testMailTo      = ref('')
const testMailLoading = ref(false)
const testMailResult  = ref(null)

const sendTestMail = async () => {
  if (!testMailTo.value || testMailLoading.value) return
  testMailLoading.value = true
  testMailResult.value  = null

  try {
    const res = await fetch('/admin/integrations/mail/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ ...mailForm, test_to: testMailTo.value }),
    })
    const data = await res.json()
    testMailResult.value = { success: data.success, message: data.message }
  } catch {
    testMailResult.value = { success: false, message: 'Network error — could not reach the server.' }
  } finally {
    testMailLoading.value = false
  }
}

const savePayment = () => router.post('/admin/integrations/payment', payForm)
const saveMail    = () => router.post('/admin/integrations/mail',    mailForm)
const saveSms     = () => router.post('/admin/integrations/sms',     smsForm)
</script>

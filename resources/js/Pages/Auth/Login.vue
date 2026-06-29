<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">

      <!-- Demo banner -->
      <div v-if="demo" class="mx-6 mt-6 rounded-xl bg-amber-50 border border-amber-200 p-4">
        <p class="text-xs font-semibold text-amber-800 mb-2">Demo Mode — try the CMS</p>
        <div class="space-y-1 mb-3">
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs text-amber-700 font-mono truncate">{{ demo.email }}</span>
            <button @click="copyToClipboard(demo.email)" class="text-xs text-amber-600 hover:text-amber-800 flex-shrink-0">
              {{ copied === 'email' ? '✓ Copied' : 'Copy' }}
            </button>
          </div>
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs text-amber-700 font-mono">{{ demo.password }}</span>
            <button @click="copyToClipboard(demo.password, 'pass')" class="text-xs text-amber-600 hover:text-amber-800 flex-shrink-0">
              {{ copied === 'pass' ? '✓ Copied' : 'Copy' }}
            </button>
          </div>
        </div>
        <button
          @click="useDemoCredentials"
          class="w-full bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium py-2 rounded-lg transition"
        >
          Use demo credentials
        </button>
      </div>

      <!-- Header -->
      <div class="px-8 pt-6 pb-6 text-center">
        <h1 class="text-2xl font-bold text-slate-800 mb-1">Portfolio CMS</h1>
        <p class="text-sm text-slate-400">Sign in to your account</p>
      </div>

      <!-- OAuth buttons -->
      <div class="px-8 space-y-3">
        <a href="/auth/google"
          class="flex items-center justify-center gap-3 w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 font-medium hover:bg-slate-50 transition">
          <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
          </svg>
          Continue with Google
        </a>

        <a href="/auth/github"
          class="flex items-center justify-center gap-3 w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 font-medium hover:bg-slate-50 transition">
          <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
          </svg>
          Continue with GitHub
        </a>
      </div>

      <!-- Divider -->
      <div class="flex items-center gap-3 px-8 my-5">
        <div class="flex-1 h-px bg-slate-200"></div>
        <span class="text-xs text-slate-400">or</span>
        <div class="flex-1 h-px bg-slate-200"></div>
      </div>

      <!-- Tab switcher -->
      <div class="flex gap-1 mx-8 mb-5 bg-slate-100 rounded-xl p-1">
        <button @click="tab = 'password'"
          class="flex-1 text-xs font-medium py-2 rounded-lg transition"
          :class="tab === 'password' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-400 hover:text-slate-600'">
          Password
        </button>
        <button @click="tab = 'otp'"
          class="flex-1 text-xs font-medium py-2 rounded-lg transition"
          :class="tab === 'otp' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-400 hover:text-slate-600'">
          Email Code
        </button>
      </div>

      <!-- Password login -->
      <div v-if="tab === 'password'" class="px-8 pb-6">
        <form @submit.prevent="passForm.post('/login/password')">
          <div class="space-y-3 mb-4">
            <input v-model="passForm.email" type="email" placeholder="Email"
              class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
            <input v-model="passForm.password" type="password" placeholder="Password"
              class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <p v-if="passForm.errors.email" class="text-red-500 text-xs mb-3">{{ passForm.errors.email }}</p>
          <button type="submit" :disabled="passForm.processing"
            class="w-full bg-blue-600 text-white rounded-xl py-3 text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition">
            Sign In
          </button>
          <div class="flex items-center justify-between mt-3 text-xs">
            <Link href="/register" class="text-blue-600 hover:underline">Create account</Link>
            <Link href="/forgot-password" class="text-slate-400 hover:underline">Forgot password?</Link>
          </div>
        </form>
      </div>

      <!-- OTP login -->
      <div v-if="tab === 'otp'" class="px-8 pb-6">
        <div v-if="!otpSent">
          <form @submit.prevent="otpForm.post('/login/otp/send', { onSuccess: () => otpSent = true })">
            <input v-model="otpForm.email" type="email" placeholder="Your email address"
              class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500 mb-4" />
            <button type="submit" :disabled="otpForm.processing"
              class="w-full bg-blue-600 text-white rounded-xl py-3 text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition">
              Send Code
            </button>
          </form>
        </div>
        <div v-else>
          <p class="text-xs text-slate-500 mb-4 text-center">
            We sent a 6-digit code to <strong>{{ otpForm.email }}</strong>
          </p>
          <form @submit.prevent="verifyForm.post('/login/otp/verify')">
            <input v-model="verifyForm.otp" type="text" maxlength="6" placeholder="000000"
              class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500 mb-4 text-center tracking-widest text-lg font-mono" />
            <p v-if="verifyForm.errors.otp" class="text-red-500 text-xs mb-3">{{ verifyForm.errors.otp }}</p>
            <input type="hidden" v-model="verifyForm.email" />
            <button type="submit" :disabled="verifyForm.processing"
              class="w-full bg-blue-600 text-white rounded-xl py-3 text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition">
              Verify Code
            </button>
            <button type="button" @click="otpSent = false"
              class="w-full text-slate-400 text-xs mt-2 hover:text-slate-600">
              Try a different email
            </button>
          </form>
        </div>
      </div>

      <!-- OAuth error -->
      <p v-if="$page.props.errors?.oauth" class="text-red-500 text-xs text-center pb-4 px-8">
        {{ $page.props.errors.oauth }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  demo: { type: Object, default: null },
})

const tab    = ref('password')
const otpSent = ref(false)
const copied = ref(null)

const passForm   = useForm({ email: '', password: '', remember: false })
const otpForm    = useForm({ email: '' })
const verifyForm = useForm({ email: '', otp: '' })

watch(() => otpForm.email, (val) => { verifyForm.email = val })

function useDemoCredentials() {
  tab.value = 'password'
  passForm.email    = props.demo.email
  passForm.password = props.demo.password
}

function copyToClipboard(text, key = 'email') {
  navigator.clipboard.writeText(text)
  copied.value = key
  setTimeout(() => { copied.value = null }, 2000)
}
</script>

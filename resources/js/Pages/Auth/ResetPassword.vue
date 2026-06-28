<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-8">
      <h1 class="text-2xl font-bold text-slate-800 mb-1 text-center">New Password</h1>
      <p class="text-sm text-slate-400 text-center mb-6">Choose a strong password</p>

      <form @submit.prevent="form.post('/reset-password')" class="space-y-3">
        <input v-model="form.email" type="email" placeholder="Email address"
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        <input v-model="form.password" type="password" placeholder="New password"
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        <input v-model="form.password_confirmation" type="password" placeholder="Confirm password"
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        <p v-if="form.errors.email" class="text-red-500 text-xs">{{ form.errors.email }}</p>
        <p v-if="form.errors.password" class="text-red-500 text-xs">{{ form.errors.password }}</p>
        <button type="submit" :disabled="form.processing"
          class="w-full bg-blue-600 text-white rounded-xl py-3 text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition">
          Reset Password
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
const props = defineProps({ token: String })
const form = useForm({ token: props.token, email: '', password: '', password_confirmation: '' })
</script>

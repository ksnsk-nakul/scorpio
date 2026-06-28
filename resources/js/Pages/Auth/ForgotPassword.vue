<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-8">
      <h1 class="text-2xl font-bold text-slate-800 mb-1 text-center">Reset Password</h1>
      <p class="text-sm text-slate-400 text-center mb-6">We'll send you a reset link</p>

      <div v-if="$page.props.flash?.status" class="bg-green-50 text-green-700 text-sm rounded-xl px-4 py-3 mb-4">
        {{ $page.props.flash.status }}
      </div>

      <form @submit.prevent="form.post('/forgot-password')" class="space-y-4">
        <input v-model="form.email" type="email" placeholder="Email address"
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        <p v-if="form.errors.email" class="text-red-500 text-xs">{{ form.errors.email }}</p>
        <button type="submit" :disabled="form.processing"
          class="w-full bg-blue-600 text-white rounded-xl py-3 text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition">
          Send Reset Link
        </button>
      </form>

      <p class="text-center text-xs text-slate-400 mt-4">
        <Link href="/login" class="text-blue-600 hover:underline">Back to sign in</Link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
const form = useForm({ email: '' })
</script>

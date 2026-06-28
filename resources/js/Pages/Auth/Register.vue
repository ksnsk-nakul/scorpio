<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-8">
      <h1 class="text-2xl font-bold text-slate-800 mb-1 text-center">Create Account</h1>
      <p class="text-sm text-slate-400 text-center mb-6">Join Portfolio CMS</p>

      <form @submit.prevent="form.post('/register')" class="space-y-3">
        <input v-model="form.name" type="text" placeholder="Full name"
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        <p v-if="form.errors.name" class="text-red-500 text-xs">{{ form.errors.name }}</p>

        <input v-model="form.email" type="email" placeholder="Email"
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        <p v-if="form.errors.email" class="text-red-500 text-xs">{{ form.errors.email }}</p>

        <input v-model="form.password" type="password" placeholder="Password (min 8 characters)"
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500" />

        <input v-model="form.password_confirmation" type="password" placeholder="Confirm password"
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        <p v-if="form.errors.password" class="text-red-500 text-xs">{{ form.errors.password }}</p>

        <button type="submit" :disabled="form.processing"
          class="w-full bg-blue-600 text-white rounded-xl py-3 text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition mt-2">
          Create Account
        </button>
      </form>

      <p class="text-center text-xs text-slate-400 mt-4">
        Already have an account?
        <Link href="/login" class="text-blue-600 hover:underline ml-1">Sign in</Link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })
</script>

<template>
  <AdminLayout>
    <div class="max-w-lg space-y-8">
      <h1 class="text-2xl font-bold text-slate-800">Profile</h1>

      <!-- Profile details -->
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Account Details</h2>
        <form @submit.prevent="profileForm.patch('/admin/profile')">
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Name</label>
              <input v-model="profileForm.name" type="text"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
              <p v-if="profileForm.errors.name" class="text-xs text-red-500 mt-1">{{ profileForm.errors.name }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Username</label>
              <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden">
                <span class="px-3 py-2 text-sm text-slate-400 bg-slate-50 border-r border-slate-200">scorpio.app/</span>
                <input v-model="profileForm.username" type="text"
                  class="flex-1 px-3 py-2 text-sm outline-none" />
              </div>
              <p v-if="profileForm.errors.username" class="text-xs text-red-500 mt-1">{{ profileForm.errors.username }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Email</label>
              <input v-model="profileForm.email" type="email"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
              <p v-if="profileForm.errors.email" class="text-xs text-red-500 mt-1">{{ profileForm.errors.email }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3 mt-5">
            <button type="submit" :disabled="profileForm.processing"
              class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">Save</button>
            <span v-if="$page.props.flash?.profile_success" class="text-xs text-green-600">Saved!</span>
          </div>
        </form>
      </div>

      <!-- Public site branding -->
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h2 class="font-semibold text-slate-800 mb-1">Site Branding</h2>
        <p class="text-xs text-slate-400 mb-4">Shown on your public portfolio at scorpio.app/{{ profileForm.username }}</p>
        <form @submit.prevent="profileForm.patch('/admin/profile')">
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Site name</label>
              <input v-model="profileForm.site_name" type="text" placeholder="Your name or brand"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
              <p v-if="profileForm.errors.site_name" class="text-xs text-red-500 mt-1">{{ profileForm.errors.site_name }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">OG / share image</label>
              <ImagePicker v-model="profileForm.og_image" context="branding" />
            </div>
          </div>
          <div class="flex items-center gap-3 mt-5">
            <button type="submit" :disabled="profileForm.processing"
              class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">Save</button>
            <span v-if="$page.props.flash?.profile_success" class="text-xs text-green-600">Saved!</span>
          </div>
        </form>
      </div>

      <!-- Password -->
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Change Password</h2>
        <form @submit.prevent="pwForm.patch('/admin/profile/password', { onSuccess: () => pwForm.reset() })">
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Current Password</label>
              <input v-model="pwForm.current_password" type="password"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
              <p v-if="pwForm.errors.current_password" class="text-xs text-red-500 mt-1">{{ pwForm.errors.current_password }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">New Password</label>
              <input v-model="pwForm.password" type="password"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
              <p v-if="pwForm.errors.password" class="text-xs text-red-500 mt-1">{{ pwForm.errors.password }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Confirm New Password</label>
              <input v-model="pwForm.password_confirmation" type="password"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
            </div>
          </div>
          <div class="flex items-center gap-3 mt-5">
            <button type="submit" :disabled="pwForm.processing"
              class="bg-slate-800 text-white text-sm px-4 py-2 rounded-lg hover:bg-slate-700">Update Password</button>
            <span v-if="$page.props.flash?.password_success" class="text-xs text-green-600">Password updated!</span>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ImagePicker from '@/Components/Admin/ImagePicker.vue'

const props = defineProps({ user: Object })

const profileForm = useForm({
  name:      props.user.name,
  username:  props.user.username,
  email:     props.user.email,
  site_name: props.user.site_name ?? '',
  og_image:  props.user.og_image ?? '',
})

const pwForm = useForm({
  current_password:      '',
  password:              '',
  password_confirmation: '',
})
</script>

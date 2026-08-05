<template>
  <Head>
    <title>{{ course.title }}</title>
    <meta name="description" :content="course.description ?? course.title" />
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="/courses" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← Courses</a>
        <span class="text-sm font-semibold text-slate-800 truncate max-w-xs">{{ course.title }}</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-4xl mx-auto px-6">
      <h1 class="text-2xl font-bold text-slate-800 mb-2">{{ course.title }}</h1>
      <p v-if="course.subtitle" class="text-sm text-slate-500">{{ course.subtitle }}</p>
      <p v-if="course.description" class="text-sm text-slate-600 leading-relaxed mt-4">{{ course.description }}</p>

      <section v-if="course.pricingTiers.length" class="mt-8 grid gap-4 sm:grid-cols-2">
        <div v-for="tier in course.pricingTiers" :key="tier.id" class="border border-slate-100 rounded-xl p-4">
          <h3 class="text-sm font-semibold text-slate-800">{{ tier.name }}</h3>
          <p class="text-xs text-slate-500 mt-1">{{ tier.description }}</p>
          <p class="text-lg font-bold text-slate-800 mt-2">₹{{ (tier.price_inr_paise / 100).toLocaleString('en-IN') }}</p>
          <button
            @click="enroll(tier.id)"
            :disabled="enrollingTierId === tier.id"
            class="mt-3 w-full text-sm bg-orange-500 text-white rounded-lg py-2 hover:bg-orange-600 disabled:opacity-50 transition-colors"
          >
            {{ enrollingTierId === tier.id ? 'Enrolling…' : 'Enroll' }}
          </button>
          <p v-if="enrollErrors[tier.id]" class="text-xs text-red-500 mt-2">{{ enrollErrors[tier.id] }}</p>
        </div>
      </section>

      <h2 class="text-lg font-semibold text-slate-800 mt-10 mb-3">Course content</h2>
      <div v-for="module in course.modules" :key="module.title" class="mb-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-2">{{ module.title }}</h3>
        <ol class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
          <li v-for="topic in module.topics" :key="topic.slug">
            <Link
              :href="`/courses/${course.slug}/topics/${topic.slug}`"
              class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50 transition-colors"
            >
              <span>{{ topic.title }}</span>
              <span class="text-slate-300">›</span>
            </Link>
          </li>
        </ol>
      </div>
    </main>
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({ course: { type: Object, required: true } })

const enrollingTierId = ref(null)
const enrollErrors = ref({})

const enroll = async (tierId) => {
  enrollingTierId.value = tierId
  enrollErrors.value = { ...enrollErrors.value, [tierId]: null }
  try {
    await axios.post(`/courses/${props.course.slug}/enroll`, { pricing_tier_id: tierId })
    window.location.reload()
  } catch (err) {
    enrollErrors.value = { ...enrollErrors.value, [tierId]: err.response?.data?.message ?? 'Enrollment failed.' }
  } finally {
    enrollingTierId.value = null
  }
}
</script>

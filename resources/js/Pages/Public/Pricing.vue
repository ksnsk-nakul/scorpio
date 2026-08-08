<template>
  <Head><title>Pricing · {{ site_name }}</title></Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="border-b border-slate-100 px-6 py-4">
      <BackLink href="/" :label="site_name" />
    </nav>

    <main class="max-w-6xl mx-auto px-6 py-16">
      <div class="text-center max-w-2xl mx-auto mb-14">
        <h1 class="text-4xl font-bold text-slate-900 mb-3">Pricing</h1>
        <p class="text-slate-500">Plans for individuals building a portfolio, and for teams running an organization page. Every paid plan includes access to the e-library, and higher tiers add AI-powered search across it.</p>
      </div>

      <section class="mb-16">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-5">For individuals</h2>
        <div class="grid md:grid-cols-3 gap-6">
          <PlanCard v-for="plan in soloPlans" :key="plan.key" :plan="plan" />
        </div>
      </section>

      <section>
        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-5">For organizations</h2>
        <div class="grid md:grid-cols-3 gap-6">
          <PlanCard v-for="plan in orgPlans" :key="plan.key" :plan="plan" />
        </div>
      </section>

      <p class="text-center text-sm text-slate-400 mt-16">
        Questions about a plan? <a href="mailto:ksnsk2001@gmail.com" class="text-orange-600 hover:underline">Get in touch</a>.
      </p>
    </main>
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import BackLink from '@/Components/Shared/BackLink.vue'
import PlanCard from '@/Components/Public/PlanCard.vue'

const props = defineProps({
  site_name: { type: String, default: 'KSNSK' },
  plans: { type: Array, default: () => [] },
})

const soloPlans = computed(() => props.plans.filter((p) => p.type === 'solo'))
const orgPlans = computed(() => props.plans.filter((p) => p.type === 'org'))
</script>

<template>
  <div :class="[
    'relative flex flex-col rounded-2xl border p-6',
    plan.badge ? 'border-orange-500 shadow-lg shadow-orange-100' : 'border-slate-200'
  ]">
    <span v-if="plan.badge" class="absolute -top-3 left-6 px-3 py-1 rounded-full bg-orange-500 text-white text-xs font-semibold">
      {{ plan.badge }}
    </span>

    <p class="font-semibold text-slate-900">{{ plan.name }}</p>
    <p v-if="plan.member_limit" class="text-xs text-slate-400 mt-0.5">Up to {{ plan.member_limit }} members</p>
    <p v-else-if="plan.member_limit === null && plan.type === 'org'" class="text-xs text-slate-400 mt-0.5">Unlimited members</p>

    <div class="mt-4 mb-6">
      <p v-if="plan.price !== null" class="text-3xl font-bold text-slate-900">
        ₹{{ (plan.price / 100).toLocaleString('en-IN') }}
        <span class="text-sm font-normal text-slate-400">/{{ plan.interval }}</span>
      </p>
      <template v-else>
        <p class="text-3xl font-bold text-slate-900">
          ₹{{ (plan.base_price / 100).toLocaleString('en-IN') }}
          <span class="text-sm font-normal text-slate-400">/{{ plan.interval }}</span>
        </p>
        <p class="text-xs text-slate-400 mt-1">Includes {{ plan.included_members }} members, then ₹{{ (plan.extra_member_price / 100).toLocaleString('en-IN') }}/member</p>
      </template>
    </div>

    <ul class="space-y-2.5 flex-1 mb-6">
      <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm text-slate-600">
        <svg v-if="isLibraryFeature(feature)" class="w-4 h-4 mt-0.5 flex-shrink-0 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
        </svg>
        <svg v-else class="w-4 h-4 mt-0.5 flex-shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6L9 17l-5-5" />
        </svg>
        <span :class="isLibraryFeature(feature) ? 'text-slate-800 font-medium' : ''">{{ feature }}</span>
      </li>
    </ul>

    <a v-if="plan.price === 0" href="/register"
      class="text-center px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-semibold hover:bg-slate-50 transition-colors">
      Get Started
    </a>
    <a v-else-if="plan.price === null" href="mailto:ksnsk2001@gmail.com?subject=Enterprise Plan Enquiry"
      class="text-center px-4 py-2.5 rounded-lg bg-slate-900 text-white font-semibold hover:bg-slate-700 transition-colors">
      Contact Us
    </a>
    <a v-else :href="plan.type === 'org' ? '/register' : '/register'"
      class="text-center px-4 py-2.5 rounded-lg bg-orange-500 text-white font-semibold hover:bg-orange-600 transition-colors">
      Choose {{ plan.name }}
    </a>
  </div>
</template>

<script setup>
defineProps({ plan: { type: Object, required: true } })

// Surfaces the e-library / RAG-search line items with a distinct icon and
// weight so they read as a highlighted capability rather than blending into
// the rest of the feature list — these are this app's newest, most
// differentiated features (Library CMS + AI search across it).
function isLibraryFeature(feature) {
  return /library|rag/i.test(feature)
}
</script>

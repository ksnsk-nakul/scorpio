<template>
  <Head><title>Refund & Cancellation Policy · {{ site_name }}</title></Head>
  <div class="min-h-screen bg-white font-sans">
    <nav class="border-b border-slate-100 px-6 py-4">
      <a href="/" class="text-sm font-bold text-slate-800 hover:text-orange-500 transition-colors">← {{ site_name }}</a>
    </nav>
    <main class="max-w-3xl mx-auto px-6 py-16">
      <h1 class="text-4xl font-bold text-slate-900 mb-2">Refund & Cancellation Policy</h1>
      <p class="text-sm text-slate-400 mb-10">Last updated: July 3, 2026</p>

      <div class="prose prose-slate max-w-none space-y-8 text-slate-700 leading-relaxed">

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">1. Subscription Plans</h2>
          <p>{{ site_name }} offers monthly subscription plans (Pro and Business). The following terms apply:</p>
          <ul class="list-disc pl-5 space-y-2 mt-3">
            <li>Subscriptions are billed monthly and renew automatically unless cancelled.</li>
            <li>You may cancel your subscription at any time from your billing settings.</li>
            <li>Upon cancellation, your plan remains active until the end of the current billing period. No further charges will be made after that.</li>
            <li>We do not offer prorated refunds for unused days within a billing period.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">2. Refund Eligibility</h2>
          <p>Refunds may be considered in the following cases:</p>
          <ul class="list-disc pl-5 space-y-2 mt-3">
            <li><strong>Duplicate payment:</strong> If you were charged more than once for the same billing cycle, the duplicate charge will be refunded in full.</li>
            <li><strong>Technical failure:</strong> If a payment was deducted but your plan was not activated due to a technical error on our end, you are entitled to a full refund or plan activation at your choice.</li>
            <li><strong>First-time subscribers:</strong> If you are unsatisfied within 7 days of your first payment, you may request a refund by contacting us at <a href="mailto:ksnsk2001@gmail.com" class="text-blue-600 hover:underline">ksnsk2001@gmail.com</a>.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">3. Non-Refundable Cases</h2>
          <ul class="list-disc pl-5 space-y-2">
            <li>Subscription fees after the 7-day first-time window.</li>
            <li>Voluntary support/tip payments made via the Support page — these are non-refundable by nature.</li>
            <li>Freelance service fees once work has commenced, unless agreed otherwise in writing before the project starts.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">4. Freelance Services</h2>
          <p>For custom development work and freelance engagements:</p>
          <ul class="list-disc pl-5 space-y-2 mt-3">
            <li>Cancellations before work begins are eligible for a full refund of any advance payment.</li>
            <li>Cancellations after work has begun are subject to a partial refund based on work completed, at our discretion.</li>
            <li>Refund terms for larger projects may be defined separately in a written agreement or invoice.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">5. How to Request a Refund</h2>
          <p>To request a refund, email us at <a href="mailto:ksnsk2001@gmail.com" class="text-blue-600 hover:underline">ksnsk2001@gmail.com</a> with:</p>
          <ul class="list-disc pl-5 space-y-2 mt-3">
            <li>Your registered email address</li>
            <li>Payment ID or transaction reference</li>
            <li>Reason for the refund request</li>
          </ul>
          <p class="mt-3">We will respond within 3 business days. Approved refunds are processed within 5–7 business days depending on your payment method.</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">6. Contact</h2>
          <p>
            For any questions about this policy, contact us at
            <a href="mailto:ksnsk2001@gmail.com" class="text-blue-600 hover:underline">ksnsk2001@gmail.com</a>.
          </p>
        </section>

      </div>
    </main>
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
defineProps({ site_name: String })
</script>

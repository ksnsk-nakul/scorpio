<template>
  <Head><title>Terms of Service · {{ site_name }}</title></Head>
  <LegalPage :site-name="site_name" title="Terms of Service" last-updated="July 2, 2026">

    <section>
      <h2 class="text-xl font-bold text-slate-800 mb-3">1. Acceptance of Terms</h2>
      <p>By accessing or using <strong>{{ site_name }}</strong> ("the Service"), you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use the Service.</p>
    </section>

    <section>
      <h2 class="text-xl font-bold text-slate-800 mb-3">2. Description of Service</h2>
      <p>{{ site_name }} is a personal portfolio and CMS platform that allows users to create and manage portfolio pages, showcase projects, and receive contact messages. The Service may include integrations with third-party services such as GitHub and Google.</p>
    </section>

    <section>
      <h2 class="text-xl font-bold text-slate-800 mb-3">3. User Accounts</h2>
      <ul class="list-disc pl-5 space-y-2">
        <li>You are responsible for maintaining the confidentiality of your account credentials.</li>
        <li>You are responsible for all activity that occurs under your account.</li>
        <li>You must provide accurate and complete information when creating an account.</li>
        <li>You may not use another user's account without permission.</li>
        <li>We reserve the right to suspend or terminate accounts that violate these terms.</li>
      </ul>
    </section>

    <section>
      <h2 class="text-xl font-bold text-slate-800 mb-3">4. Acceptable Use</h2>
      <p>You agree not to use the Service to:</p>
      <ul class="list-disc pl-5 space-y-2 mt-2">
        <li>Upload, post, or transmit any content that is unlawful, harmful, or offensive.</li>
        <li>Impersonate any person or entity or misrepresent your affiliation with any person or entity.</li>
        <li>Interfere with or disrupt the integrity or performance of the Service.</li>
        <li>Attempt to gain unauthorised access to any part of the Service.</li>
        <li>Use automated tools to scrape, crawl, or harvest data from the Service without permission.</li>
      </ul>
    </section>

    <section>
      <h2 class="text-xl font-bold text-slate-800 mb-3">5. Third-Party Integrations</h2>
      <p>The Service integrates with third-party platforms including <strong>GitHub</strong> and <strong>Google</strong>. When you connect these services, you are subject to their respective terms of service and privacy policies. We are not responsible for the practices of these third parties.</p>
      <ul class="list-disc pl-5 space-y-2 mt-2">
        <li><a href="https://docs.github.com/en/site-policy/github-terms/github-terms-of-service" target="_blank" rel="noopener" class="text-orange-600 hover:underline">GitHub Terms of Service</a></li>
        <li><a href="https://policies.google.com/terms" target="_blank" rel="noopener" class="text-orange-600 hover:underline">Google Terms of Service</a></li>
      </ul>
    </section>

    <section>
      <h2 class="text-xl font-bold text-slate-800 mb-3">6. Intellectual Property</h2>
      <p>All content you upload or create through the Service remains your property. By using the Service, you grant us a limited, non-exclusive licence to store and display your content solely for the purpose of providing the Service.</p>
      <p class="mt-2">The Service itself, including its design, code, and branding, is the intellectual property of Nakul Sri Kuber and may not be reproduced or distributed without permission.</p>
    </section>

    <section>
      <h2 class="text-xl font-bold text-slate-800 mb-3">7. Payments and Subscriptions</h2>
      <p>Paid plans are available and processed through <strong>Razorpay</strong>. By subscribing to a paid plan, you agree to Razorpay's <a href="https://razorpay.com/terms/" target="_blank" rel="noopener" class="text-orange-600 hover:underline">Terms of Service</a>. Subscriptions auto-renew until cancelled. Refunds are handled on a case-by-case basis — contact us within 7 days of a charge for assistance.</p>
    </section>

    <section>
      <h2 class="text-xl font-bold text-slate-800 mb-3">8. Disclaimers</h2>
      <p>The Service is provided "as is" without warranties of any kind. We do not guarantee that the Service will be uninterrupted, error-free, or secure. We are not liable for any indirect, incidental, or consequential damages arising from your use of the Service.</p>
    </section>

    <section>
      <h2 class="text-xl font-bold text-slate-800 mb-3">9. Changes to Terms</h2>
      <p>We reserve the right to modify these terms at any time. Continued use of the Service after changes constitutes acceptance of the new terms. We will notify users of significant changes via email or a notice on the Service.</p>
    </section>

    <section>
      <h2 class="text-xl font-bold text-slate-800 mb-3">10. Contact</h2>
      <p>For questions about these Terms, contact us at <a href="mailto:ksnsk2001@gmail.com" class="text-orange-600 hover:underline">ksnsk2001@gmail.com</a>.</p>
    </section>

    <template #footer>
      <div class="mt-12 pt-8 border-t border-slate-100 flex gap-6 text-sm text-slate-400">
        <a href="/privacy" class="hover:text-slate-700 transition-colors">Privacy Policy</a>
        <a href="/" class="hover:text-slate-700 transition-colors">Home</a>
      </div>
    </template>
  </LegalPage>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import LegalPage from '@/Components/Shared/LegalPage.vue'

defineProps({ site_name: { type: String, default: 'KSNSK' } })
</script>

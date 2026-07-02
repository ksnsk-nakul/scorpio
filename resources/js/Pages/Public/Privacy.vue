<template>
  <Head><title>Privacy Policy · {{ site_name }}</title></Head>
  <div class="min-h-screen bg-white font-sans">
    <nav class="border-b border-slate-100 px-6 py-4">
      <a href="/" class="text-sm font-bold text-slate-800 hover:text-orange-500 transition-colors">← {{ site_name }}</a>
    </nav>
    <main class="max-w-3xl mx-auto px-6 py-16">
      <h1 class="text-4xl font-bold text-slate-900 mb-2">Privacy Policy</h1>
      <p class="text-sm text-slate-400 mb-10">Last updated: July 2, 2026</p>

      <div class="prose prose-slate max-w-none space-y-8 text-slate-700 leading-relaxed">

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">1. Introduction</h2>
          <p>This Privacy Policy explains how <strong>{{ site_name }}</strong> ("we", "us", or "our"), operated by Nakul Sri Kuber, collects, uses, and protects your personal information when you use our Service at <strong>nakulsrikuber.com</strong>.</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">2. Information We Collect</h2>
          <h3 class="font-semibold text-slate-700 mb-2">Information you provide directly:</h3>
          <ul class="list-disc pl-5 space-y-1 mb-4">
            <li>Name and email address when registering or using the contact form</li>
            <li>Profile information (bio, skills, experience, profile photo)</li>
            <li>Content you create (pages, projects, service cards)</li>
            <li>Payment information (processed by Razorpay — we do not store card details)</li>
          </ul>
          <h3 class="font-semibold text-slate-700 mb-2">Information collected automatically:</h3>
          <ul class="list-disc pl-5 space-y-1">
            <li>Log data (IP address, browser type, pages visited)</li>
            <li>Cookies and session identifiers for authentication</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">3. Google OAuth</h2>
          <p>We offer sign-in via <strong>Google OAuth 2.0</strong>. When you sign in with Google, we receive:</p>
          <ul class="list-disc pl-5 space-y-1 mt-2">
            <li>Your Google account email address</li>
            <li>Your display name</li>
            <li>Your profile picture URL</li>
          </ul>
          <p class="mt-3">We do <strong>not</strong> receive access to your Google Drive, Gmail, contacts, or any other Google service data. We do not store your Google password. You can revoke our access at any time via your <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener" class="text-orange-600 hover:underline">Google Account permissions page</a>.</p>
          <p class="mt-2">Our use of Google user data is limited to signing you into the Service and is governed by <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" class="text-orange-600 hover:underline">Google's Privacy Policy</a>.</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">4. GitHub Integration</h2>
          <p>If you connect a GitHub account or personal access token, we access your public and (optionally) private repository list solely to display it within the Service and to link repositories to projects. We do not share your GitHub data with third parties and only store the minimum required information (repository names and IDs).</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">5. How We Use Your Information</h2>
          <ul class="list-disc pl-5 space-y-2">
            <li>To provide and improve the Service</li>
            <li>To authenticate your identity and secure your account</li>
            <li>To process payments and subscriptions</li>
            <li>To send transactional emails (e.g. password resets, billing receipts)</li>
            <li>To respond to contact form submissions you receive on your portfolio</li>
            <li>To comply with legal obligations</li>
          </ul>
          <p class="mt-3">We do <strong>not</strong> sell your personal data to third parties or use it for advertising.</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">6. Data Sharing</h2>
          <p>We share data only with the following categories of service providers, solely to operate the Service:</p>
          <ul class="list-disc pl-5 space-y-2 mt-2">
            <li><strong>Aiven / MySQL</strong> — cloud database hosting for your account data</li>
            <li><strong>Razorpay</strong> — payment processing (governed by <a href="https://razorpay.com/privacy/" target="_blank" rel="noopener" class="text-orange-600 hover:underline">Razorpay's Privacy Policy</a>)</li>
            <li><strong>Google</strong> — OAuth authentication (governed by Google's Privacy Policy)</li>
            <li><strong>GitHub</strong> — repository integration (governed by <a href="https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement" target="_blank" rel="noopener" class="text-orange-600 hover:underline">GitHub's Privacy Statement</a>)</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">7. Cookies</h2>
          <p>We use strictly necessary cookies for authentication (session cookies). We do not use tracking or advertising cookies. You can disable cookies in your browser settings, but this will prevent you from logging in.</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">8. Data Retention</h2>
          <p>We retain your data for as long as your account is active. If you delete your account, your data will be permanently removed within 30 days, except where retention is required by law.</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">9. Your Rights</h2>
          <p>You have the right to:</p>
          <ul class="list-disc pl-5 space-y-1 mt-2">
            <li>Access the personal data we hold about you</li>
            <li>Request correction of inaccurate data</li>
            <li>Request deletion of your data ("right to be forgotten")</li>
            <li>Export your data in a portable format</li>
            <li>Withdraw consent for optional processing at any time</li>
          </ul>
          <p class="mt-3">To exercise these rights, email <a href="mailto:ksnsk2001@gmail.com" class="text-orange-600 hover:underline">ksnsk2001@gmail.com</a>.</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">10. Security</h2>
          <p>We implement industry-standard security measures including HTTPS encryption, hashed passwords (bcrypt), and CSRF protection. However, no method of transmission over the Internet is 100% secure.</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">11. Children's Privacy</h2>
          <p>The Service is not directed at individuals under 13 years of age. We do not knowingly collect personal information from children under 13.</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">12. Changes to This Policy</h2>
          <p>We may update this Privacy Policy from time to time. We will notify you of significant changes by email or by posting a notice in the Service. Continued use of the Service after changes constitutes acceptance.</p>
        </section>

        <section>
          <h2 class="text-xl font-bold text-slate-800 mb-3">13. Contact</h2>
          <p>For privacy-related enquiries or to exercise your data rights, contact:</p>
          <div class="mt-2 p-4 bg-slate-50 rounded-xl text-sm">
            <p class="font-semibold text-slate-800">Nakul Sri Kuber</p>
            <p>Email: <a href="mailto:ksnsk2001@gmail.com" class="text-orange-600 hover:underline">ksnsk2001@gmail.com</a></p>
            <p>Website: <a href="https://nakulsrikuber.com" class="text-orange-600 hover:underline">nakulsrikuber.com</a></p>
          </div>
        </section>

      </div>

      <div class="mt-12 pt-8 border-t border-slate-100 flex gap-6 text-sm text-slate-400">
        <a href="/terms" class="hover:text-slate-700 transition-colors">Terms of Service</a>
        <a href="/" class="hover:text-slate-700 transition-colors">Home</a>
      </div>
    </main>
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
defineProps({ site_name: { type: String, default: 'KSNSK' } })
</script>

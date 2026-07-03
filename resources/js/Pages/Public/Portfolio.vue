<template>
  <Head>
    <title>{{ pageTitle }}</title>
    <meta name="description" :content="pageDescription" />
    <meta property="og:title" :content="pageTitle" />
    <meta property="og:description" :content="pageDescription" />
    <meta property="og:type" content="profile" />
    <meta v-if="settings.og_image" property="og:image" :content="settings.og_image" />
    <meta name="twitter:card" :content="settings.og_image ? 'summary_large_image' : 'summary'" />
    <meta name="twitter:title" :content="pageTitle" />
    <meta name="twitter:description" :content="pageDescription" />
    <meta v-if="settings.og_image" name="twitter:image" :content="settings.og_image" />
  </Head>

  <div class="min-h-screen bg-white text-slate-900 font-sans">

    <!-- ── Nav ─────────────────────────────────────────────────────────── -->
    <nav class="fixed top-0 inset-x-0 z-50 bg-white/90 backdrop-blur border-b border-slate-100 transition-shadow duration-300"
         :class="scrolled ? 'shadow-md' : ''">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">

        <!-- Logo: SVG brand mark + site name -->
        <a href="#home" class="flex items-center gap-2 group">
          <img src="/images/ksnsk-logo.png" alt="KSNSK"
               class="h-7 w-auto transition-transform duration-300 group-hover:scale-105" />
        </a>

        <!-- Anchor nav links -->
        <div class="hidden md:flex items-center gap-1">
          <a v-for="link in navLinks" :key="link.href" :href="link.href"
            class="relative px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-orange-500 transition-colors duration-200 rounded-lg hover:bg-orange-50"
            :class="activeSection === link.section ? 'text-orange-500' : ''">
            {{ link.label }}
            <span class="absolute bottom-0 left-3 right-3 h-0.5 bg-orange-500 rounded-full transition-all duration-300 origin-left"
                  :class="activeSection === link.section ? 'scale-x-100' : 'scale-x-0'"></span>
          </a>
        </div>

        <div class="flex items-center gap-3">
          <template v-if="isLoggedIn">
            <a v-if="isAdmin" href="/admin/dashboard"
              class="text-sm px-4 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-700 transition-colors duration-200">
              Dashboard →
            </a>
          </template>
          <template v-else>
            <a href="/login"
              class="text-sm px-4 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors duration-200">
              Login
            </a>
            <a href="/register"
              class="text-sm px-4 py-1.5 rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition-colors duration-200">
              Sign up
            </a>
          </template>
        </div>
      </div>
    </nav>

    <!-- Page content -->
    <main class="pt-14">

      <!-- Donate banner -->
      <div v-if="settings.show_donate_banner"
        class="bg-gradient-to-r from-pink-50 to-rose-50 border-b border-pink-100 px-6 py-3 text-center animate-fade-down">
        <p class="text-sm text-slate-700">
          Enjoying my work?
          <a href="/donate" class="font-medium text-pink-600 hover:text-pink-800 underline ml-1 transition-colors">Support me ♥</a>
        </p>
      </div>

      <template v-if="page" v-for="block in (page.blocks ?? [])" :key="block.order">

        <!-- ── Hero ──────────────────────────────────────────────────── -->
        <section v-if="block.type === 'hero'" id="home"
          class="min-h-[92vh] flex items-center px-6 max-w-6xl mx-auto py-20 overflow-hidden">

          <!-- Left text -->
          <div class="flex-1 animate-fade-up">
            <p class="text-orange-500 font-semibold text-xs uppercase tracking-[0.25em] mb-4 animate-fade-up" style="animation-delay:0.1s">Hello, I'm</p>
            <h1 class="text-5xl md:text-6xl font-extrabold leading-tight text-slate-900 mb-4 animate-fade-up" style="animation-delay:0.2s">
              {{ block.data.heading?.replace(/^Hi, I'm /, '') || owner.name }}
            </h1>
            <p class="text-lg text-slate-500 mb-3 animate-fade-up" style="animation-delay:0.3s">
              {{ block.data.subheading }}
            </p>

            <!-- Rotating text -->
            <div v-if="block.data.rotating_text?.length"
              class="text-orange-500 font-semibold mb-8 h-7 overflow-hidden animate-fade-up" style="animation-delay:0.4s">
              <Transition name="slide-up" mode="out-in">
                <span :key="rotatingIndex" class="block">{{ currentRotatingText }}</span>
              </Transition>
            </div>

            <div class="flex gap-4 animate-fade-up" style="animation-delay:0.5s">
              <a href="#projects"
                class="px-7 py-3 bg-orange-500 text-white rounded-xl font-semibold hover:bg-orange-600 active:scale-95 transition-all duration-200 shadow-lg shadow-orange-200 text-sm">
                View Projects
              </a>
              <a href="#contact"
                class="px-7 py-3 border-2 border-slate-200 text-slate-700 rounded-xl font-semibold hover:border-orange-400 hover:text-orange-500 active:scale-95 transition-all duration-200 text-sm">
                Contact
              </a>
            </div>
          </div>

          <!-- Right: hero illustration -->
          <div class="flex-1 hidden md:flex justify-center items-center animate-fade-left" style="animation-delay:0.3s">
            <div class="relative">
              <!-- Decorative ring -->
              <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-orange-100 to-blue-100 scale-110 opacity-60 animate-pulse-slow"></div>
              <img :src="block.data.image || '/images/profile.png'" :alt="owner.name"
                   class="relative w-[340px] h-[340px] object-cover drop-shadow-2xl animate-float rounded-3xl"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'" />
              <!-- Fallback -->
              <div class="relative w-[340px] h-[340px] rounded-3xl bg-gradient-to-br from-orange-50 to-blue-50 items-center justify-center text-9xl select-none hidden">
                👨‍💻
              </div>
            </div>
          </div>
        </section>

        <!-- ── About ─────────────────────────────────────────────────── -->
        <section v-else-if="block.type === 'about'" id="about"
          class="py-24 px-6 max-w-6xl mx-auto reveal">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-start">
            <div>
              <span class="text-xs font-bold text-orange-500 uppercase tracking-widest mb-2 block">Who I am</span>
              <h2 class="text-3xl font-bold text-slate-900 mb-6">About</h2>
              <div class="space-y-4 text-slate-600 leading-relaxed">
                <p v-for="(para, i) in aboutParagraphs((block.data.bio || dbAbout?.bio))" :key="i">{{ para }}</p>
              </div>
            </div>
            <div class="reveal" style="transition-delay:0.15s">
              <h3 class="text-orange-500 font-bold text-lg mb-5">Skills Overview</h3>
              <ul class="space-y-3">
                <li v-for="(item, i) in ((block.data.overview?.length ? block.data.overview : dbAbout?.overview) ?? [])" :key="item"
                  class="flex items-start gap-3 text-slate-600">
                  <span class="mt-1 w-2 h-2 rounded-full bg-orange-400 flex-shrink-0"></span>
                  {{ item }}
                </li>
              </ul>
            </div>
          </div>
        </section>

        <!-- ── Skills grid ───────────────────────────────────────────── -->
        <section v-else-if="block.type === 'skills'" id="skills"
          class="py-24 px-6 bg-slate-50">
          <div class="max-w-6xl mx-auto reveal">
            <span class="text-xs font-bold text-orange-500 uppercase tracking-widest mb-2 block">What I use</span>
            <h2 class="text-3xl font-bold text-slate-900 mb-12">{{ block.data.heading ?? 'Skills' }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
              <div v-for="(skill, i) in (block.data.skills?.length ? block.data.skills : (dbSkills ?? []))"
                :key="skill.name"
                class="skill-card bg-white rounded-2xl p-5 flex flex-col items-center gap-2 shadow-sm cursor-default"
                :style="`animation-delay:${i * 0.05}s`">
                <span class="text-3xl leading-none">{{ skill.icon }}</span>
                <span class="text-xs font-semibold text-slate-600 text-center">{{ skill.name }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Experience timeline ───────────────────────────────────── -->
        <section v-else-if="block.type === 'experience'" id="experience"
          class="py-24 px-6 max-w-6xl mx-auto reveal">
          <span class="text-xs font-bold text-orange-500 uppercase tracking-widest mb-2 block">My journey</span>
          <h2 class="text-3xl font-bold text-slate-900 mb-12">{{ block.data.heading ?? 'Experience' }}</h2>
          <div class="relative">
            <!-- vertical line -->
            <div class="absolute left-3 top-2 bottom-2 w-0.5 bg-orange-200 rounded-full"></div>
            <div class="space-y-10 pl-12">
              <div v-for="(exp, i) in (block.data.items?.length ? block.data.items : (dbExperience ?? []))"
                :key="exp.title"
                class="relative reveal"
                :style="`transition-delay:${i*0.12}s`">
                <!-- dot -->
                <div class="absolute -left-[2.7rem] top-1.5 w-3 h-3 rounded-full bg-orange-500 ring-4 ring-orange-100 shadow"></div>
                <p class="text-orange-500 font-bold text-xs uppercase tracking-wider mb-1">{{ exp.period }}</p>
                <h3 class="font-bold text-slate-900 text-lg leading-snug">{{ exp.title }}</h3>
                <p v-if="exp.company" class="text-sm text-slate-400 mt-0.5">{{ exp.company }}</p>
                <p class="text-slate-600 text-sm mt-2 leading-relaxed">{{ exp.description }}</p>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Text ──────────────────────────────────────────────────── -->
        <section v-else-if="block.type === 'text'" class="py-16 px-6 max-w-3xl mx-auto prose prose-slate reveal">
          <p class="whitespace-pre-line">{{ block.data.content }}</p>
        </section>

        <!-- ── Text + Image ──────────────────────────────────────────── -->
        <section v-else-if="block.type === 'text_image'" class="py-16 px-6 max-w-5xl mx-auto flex flex-col md:flex-row gap-12 items-center reveal">
          <div class="flex-1 prose prose-slate">
            <p class="whitespace-pre-line">{{ block.data.text }}</p>
          </div>
          <div v-if="block.data.image" class="flex-1">
            <img :src="block.data.image" :alt="block.data.alt ?? ''" class="rounded-2xl shadow-md w-full object-cover" />
          </div>
        </section>

        <!-- ── Service Cards ─────────────────────────────────────────── -->
        <section v-else-if="block.type === 'service_cards'" class="py-24 px-6 max-w-6xl mx-auto reveal">
          <div v-if="block.data.heading" class="mb-12">
            <h2 class="text-3xl font-bold text-slate-900">{{ block.data.heading }}</h2>
          </div>
          <div v-if="page.service_cards?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="(card, i) in page.service_cards" :key="card.id"
              class="group rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
              :style="`transition-delay:${i*0.08}s`">
              <div v-if="card.icon" class="text-3xl mb-4 group-hover:scale-110 transition-transform duration-300 inline-block">{{ card.icon }}</div>
              <h3 class="font-bold text-slate-900 mb-2 group-hover:text-orange-500 transition-colors duration-200">{{ card.title }}</h3>
              <p class="text-sm text-slate-500 leading-relaxed">{{ card.description }}</p>
            </div>
          </div>
        </section>

        <!-- ── Project Grid ──────────────────────────────────────────── -->
        <section v-else-if="block.type === 'project_grid'" id="projects" class="py-24 px-6 max-w-6xl mx-auto reveal">
          <div v-if="block.data.heading" class="mb-12">
            <span class="text-xs font-bold text-orange-500 uppercase tracking-widest mb-2 block">What I've built</span>
            <h2 class="text-3xl font-bold text-slate-900">{{ block.data.heading }}</h2>
          </div>

          <!-- DB workspace projects -->
          <div v-if="block.data.workspace_id && workspaces[block.data.workspace_id]?.projects?.length"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="(project, i) in workspaces[block.data.workspace_id].projects" :key="project.id"
              class="group rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col"
              :style="`transition-delay:${i*0.07}s`">
              <h3 class="font-bold text-slate-900 text-lg mb-2 group-hover:text-orange-500 transition-colors">{{ project.name }}</h3>
              <p class="text-sm text-slate-500 flex-1 mb-4 leading-relaxed">{{ project.description }}</p>
              <div v-if="project.tags?.length" class="flex flex-wrap gap-1.5 mb-4">
                <span v-for="tag in project.tags" :key="tag"
                  class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">{{ tag }}</span>
              </div>
              <div class="flex flex-wrap gap-2 mt-auto">
                <a v-if="project.github_repo" :href="`https://github.com/${project.github_repo}`" target="_blank" rel="noopener"
                  class="px-4 py-1.5 border border-slate-200 text-slate-600 rounded-lg text-xs font-medium hover:border-orange-400 hover:text-orange-500 transition-all">GitHub</a>
                <a v-if="project.demo_url" :href="project.demo_url" target="_blank" rel="noopener"
                  class="px-4 py-1.5 bg-orange-500 text-white rounded-lg text-xs font-medium hover:bg-orange-600 transition-colors shadow-sm shadow-orange-200">Live Demo →</a>
                <a :href="`/portfolio/${owner.username}/projects/${project.slug}`"
                  class="px-4 py-1.5 border border-orange-200 text-orange-600 rounded-lg text-xs font-medium hover:bg-orange-50 transition-colors">View Details</a>
              </div>
            </div>
          </div>

          <!-- Static JSON fallback -->
          <div v-else-if="block.data.projects?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="(project, i) in block.data.projects" :key="project.id ?? project.title"
              class="group rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col"
              :class="project.demo_url ? 'cursor-pointer' : ''"
              :style="`transition-delay:${i*0.07}s`"
              @click="project.demo_url ? openUrl(project.demo_url) : null">
              <h3 class="font-bold text-slate-900 text-lg mb-2 group-hover:text-orange-500 transition-colors">{{ project.title }}</h3>
              <p class="text-sm text-slate-500 flex-1 mb-4 leading-relaxed">{{ project.description }}</p>
              <div v-if="project.tags?.length" class="flex flex-wrap gap-1.5 mb-4">
                <span v-for="tag in project.tags" :key="tag"
                  class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">{{ tag }}</span>
              </div>
              <p v-else-if="project.tech" class="text-xs text-slate-400 mb-4">Tech: {{ project.tech }}</p>
              <div class="flex gap-2 mt-auto" @click.stop>
                <a v-if="project.github" :href="project.github" target="_blank" rel="noopener"
                  class="px-4 py-1.5 border border-slate-200 text-slate-600 rounded-lg text-xs font-medium hover:border-orange-400 hover:text-orange-500 transition-all">GitHub</a>
                <a v-if="project.demo_url" :href="project.demo_url" target="_blank" rel="noopener"
                  class="px-4 py-1.5 bg-orange-500 text-white rounded-lg text-xs font-medium hover:bg-orange-600 transition-colors shadow-sm shadow-orange-200">Live Demo →</a>
                <a v-else-if="project.url && project.url !== '#'" :href="project.url"
                  class="px-4 py-1.5 border border-orange-200 text-orange-600 rounded-lg text-xs font-medium hover:bg-orange-50 transition-colors">View Details</a>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Contact Form ──────────────────────────────────────────── -->
        <section v-else-if="block.type === 'contact_form'" id="contact"
          class="py-24 px-6 max-w-5xl mx-auto reveal">
          <span class="text-xs font-bold text-orange-500 uppercase tracking-widest mb-2 block">Let's talk</span>
          <h2 class="text-3xl font-bold text-slate-900 mb-10">{{ block.data.heading ?? 'Contact' }}</h2>

          <div v-if="$page.props.flash?.contact_success"
            class="mb-6 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3 animate-fade-up">
            {{ $page.props.flash.contact_success }}
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-start">
            <div class="space-y-5">
              <div v-if="block.data.email" class="flex items-center gap-4 group">
                <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500 text-lg flex-shrink-0 group-hover:bg-orange-100 transition-colors">✉️</div>
                <a :href="`mailto:${block.data.email}`" class="text-sm text-slate-600 hover:text-orange-500 transition-colors break-all">{{ block.data.email }}</a>
              </div>
              <div v-if="block.data.phone" class="flex items-center gap-4 group">
                <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500 text-lg flex-shrink-0 group-hover:bg-orange-100 transition-colors">📞</div>
                <a :href="`tel:${block.data.phone}`" class="text-sm text-slate-600 hover:text-orange-500 transition-colors">{{ block.data.phone }}</a>
              </div>
              <div v-for="link in (block.data.links ?? [])" :key="link.label" class="flex items-center gap-4 group">
                <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500 text-lg flex-shrink-0 group-hover:bg-orange-100 transition-colors">{{ link.icon ?? '🔗' }}</div>
                <a :href="link.url" target="_blank" rel="noopener" class="text-sm text-slate-600 hover:text-orange-500 transition-colors">{{ link.label }}</a>
              </div>

              <!-- Donate card -->
              <div class="mt-6 bg-gradient-to-br from-orange-50 to-amber-50 border border-orange-200 rounded-2xl p-6 hover:shadow-md transition-shadow duration-300">
                <p class="font-bold text-slate-800 mb-1">Support my work</p>
                <p class="text-sm text-slate-500 mb-4">If you find my projects helpful, consider buying me a coffee.</p>
                <a href="/donate"
                  class="inline-flex items-center gap-2 px-5 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 active:scale-95 transition-all duration-200 shadow-md shadow-orange-200">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                  Support me ♥
                </a>
              </div>
            </div>

            <form @submit.prevent="submitContact(block)" class="space-y-4">
              <div>
                <input v-model="contactForm.name" type="text" placeholder="Your name"
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition-all duration-200"
                  :class="contactForm.errors.name ? 'border-red-300 ring-1 ring-red-300' : ''" />
                <p v-if="contactForm.errors.name" class="text-xs text-red-500 mt-1">{{ contactForm.errors.name }}</p>
              </div>
              <div>
                <input v-model="contactForm.email" type="email" placeholder="Your email"
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition-all duration-200"
                  :class="contactForm.errors.email ? 'border-red-300 ring-1 ring-red-300' : ''" />
                <p v-if="contactForm.errors.email" class="text-xs text-red-500 mt-1">{{ contactForm.errors.email }}</p>
              </div>
              <div>
                <textarea v-model="contactForm.message" rows="5" placeholder="Your message"
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition-all duration-200 resize-none"
                  :class="contactForm.errors.message ? 'border-red-300 ring-1 ring-red-300' : ''" />
                <p v-if="contactForm.errors.message" class="text-xs text-red-500 mt-1">{{ contactForm.errors.message }}</p>
              </div>
              <button type="submit" :disabled="contactForm.processing"
                class="w-full bg-orange-500 text-white py-3 rounded-xl text-sm font-semibold hover:bg-orange-600 active:scale-[0.98] disabled:opacity-50 transition-all duration-200 shadow-lg shadow-orange-200">
                {{ contactForm.processing ? 'Sending…' : 'Send message →' }}
              </button>
            </form>
          </div>
        </section>

      </template>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-100 py-10 text-center">
      <img src="/images/ksnsk-logo.png" alt="KSNSK" class="h-8 mx-auto mb-4 opacity-60" />
      <p class="text-xs text-slate-400 mb-3">{{ settings.site_name || owner.name }}</p>
      <p class="text-xs text-slate-300">© {{ new Date().getFullYear() }} · Built with Laravel &amp; Vue</p>
      <div class="flex items-center justify-center gap-4 mt-3">
        <a href="/terms" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Terms</a>
        <span class="text-slate-200">·</span>
        <a href="/privacy" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Privacy</a>
      </div>
      <a href="/donate" class="inline-flex items-center gap-1.5 text-xs text-pink-400 hover:text-pink-600 transition-colors mt-3">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        Support my work
      </a>
    </footer>
  </div>
</template>

<script setup>
import { usePage, Head, useForm } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  page:         { type: Object, default: () => ({}) },
  owner:        { type: Object, default: () => ({}) },
  settings:     { type: Object, default: () => ({}) },
  workspaces:   { type: Object, default: () => ({}) },
  auth:         { type: Object, default: () => ({}) },
  dbSkills:     { type: Array,  default: null },
  dbAbout:      { type: Object, default: null },
  dbExperience: { type: Array,  default: null },
})

const { props: pageProps } = usePage()
const isLoggedIn = computed(() => !!pageProps.auth?.user)
const isAdmin    = computed(() => pageProps.auth?.roles?.includes('admin') ?? false)

// ── Nav active section tracking ──────────────────────────────────────────────
const scrolled        = ref(false)
const activeSection   = ref('home')

const blockTypes = computed(() => new Set((props.page.blocks ?? []).map(b => b.type)))
const navLinks = computed(() => {
  const sectionMap = {
    hero:         { label: 'Home',       section: 'home' },
    about:        { label: 'About',      section: 'about' },
    skills:       { label: 'Skills',     section: 'skills' },
    project_grid: { label: 'Projects',   section: 'projects' },
    experience:   { label: 'Experience', section: 'experience' },
    contact_form: { label: 'Contact',    section: 'contact' },
  }
  return (props.page.blocks ?? [])
    .filter(b => sectionMap[b.type])
    .map(b => ({ ...sectionMap[b.type], href: `#${sectionMap[b.type].section}` }))
})

const initials = computed(() => {
  const name = props.settings.site_name || props.owner.name || ''
  return name.split(' ').map(w => w[0]).join('').slice(0, 4).toUpperCase()
})

// ── Rotating text ────────────────────────────────────────────────────────────
const rotatingIndex = ref(0)
const rotatingTexts = computed(() => {
  const b = (props.page.blocks ?? []).find(b => b.type === 'hero')
  return b?.data?.rotating_text ?? []
})
const currentRotatingText = computed(() => rotatingTexts.value[rotatingIndex.value] ?? '')
let rotatingTimer = null

// ── Scroll handler: nav shadow + active section ──────────────────────────────
const onScroll = () => {
  scrolled.value = window.scrollY > 20
  const sections = ['home','about','skills','projects','experience','contact']
  for (const id of [...sections].reverse()) {
    const el = document.getElementById(id)
    if (el && window.scrollY >= el.offsetTop - 100) {
      activeSection.value = id
      break
    }
  }
}

// ── Scroll-reveal (IntersectionObserver) ────────────────────────────────────
let revealObserver = null
const setupReveal = () => {
  revealObserver = new IntersectionObserver(
    (entries) => entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('revealed')
        revealObserver.unobserve(e.target)
      }
    }),
    { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
  )
  document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el))
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
  onScroll()

  if (rotatingTexts.value.length > 1) {
    rotatingTimer = setInterval(() => {
      rotatingIndex.value = (rotatingIndex.value + 1) % rotatingTexts.value.length
    }, 2500)
  }

  setTimeout(setupReveal, 80)
})

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
  clearInterval(rotatingTimer)
  revealObserver?.disconnect()
})

// ── Helpers ──────────────────────────────────────────────────────────────────
const aboutParagraphs = (bio) => (bio ?? '').split('\n').filter(p => p.trim())

const pageTitle = computed(() => {
  const site = props.settings.site_name || props.owner.name
  return props.page.is_home ? site : `${props.page.name} — ${site}`
})
const pageDescription = computed(() => {
  const b = (props.page.blocks ?? []).find(b => b.type === 'hero')
  return b?.data?.subheading || `${props.owner.name}'s portfolio`
})

// ── Helpers ──────────────────────────────────────────────────────────────────
const openUrl = (url) => window.open(url, '_blank', 'noopener,noreferrer')

// ── Contact form ─────────────────────────────────────────────────────────────
const contactForm = useForm({ name: '', email: '', message: '', user_id: '', page_slug: '' })
const submitContact = () => {
  contactForm.user_id   = props.owner.id ?? ''
  contactForm.page_slug = props.page?.slug ?? ''
  contactForm.post('/contact', { preserveScroll: true, onSuccess: () => contactForm.reset() })
}
</script>

<style>
/* ── Keyframe animations ─────────────────────────────────────────────────── */
@keyframes fadeUp {
  from { opacity:0; transform:translateY(28px) }
  to   { opacity:1; transform:translateY(0) }
}
@keyframes fadeLeft {
  from { opacity:0; transform:translateX(40px) }
  to   { opacity:1; transform:translateX(0) }
}
@keyframes fadeDown {
  from { opacity:0; transform:translateY(-16px) }
  to   { opacity:1; transform:translateY(0) }
}
@keyframes float {
  0%,100% { transform:translateY(0) }
  50%      { transform:translateY(-12px) }
}
@keyframes pulseSlow {
  0%,100% { opacity:.45; transform:scale(1.08) }
  50%      { opacity:.65; transform:scale(1.14) }
}

/* ── Utility animation classes ───────────────────────────────────────────── */
.animate-fade-up   { animation: fadeUp   0.7s cubic-bezier(.22,1,.36,1) both }
.animate-fade-left { animation: fadeLeft 0.7s cubic-bezier(.22,1,.36,1) both }
.animate-fade-down { animation: fadeDown 0.5s cubic-bezier(.22,1,.36,1) both }
.animate-float     { animation: float    4s ease-in-out infinite }
.animate-pulse-slow{ animation: pulseSlow 3s ease-in-out infinite }

/* ── Scroll-reveal base state ────────────────────────────────────────────── */
.reveal {
  opacity: 0;
  transform: translateY(32px);
  transition: opacity 0.65s cubic-bezier(.22,1,.36,1),
              transform 0.65s cubic-bezier(.22,1,.36,1);
}
.reveal.revealed {
  opacity: 1;
  transform: translateY(0);
}

/* ── Skill card hover ────────────────────────────────────────────────────── */
.skill-card {
  transition: transform 0.25s cubic-bezier(.22,1,.36,1),
              box-shadow 0.25s ease;
}
.skill-card:hover {
  transform: translateY(-4px) scale(1.04);
  box-shadow: 0 10px 28px -6px rgba(249,115,22,.18);
}

/* ── Rotating text transition ────────────────────────────────────────────── */
.slide-up-enter-active { transition: all 0.35s cubic-bezier(.22,1,.36,1) }
.slide-up-leave-active { transition: all 0.25s cubic-bezier(.55,0,1,.45) }
.slide-up-enter-from   { opacity:0; transform:translateY(14px) }
.slide-up-leave-to     { opacity:0; transform:translateY(-14px) }

/* ── Nav active underline ────────────────────────────────────────────────── */
nav a span { pointer-events:none }
</style>

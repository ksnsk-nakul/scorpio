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
    <link v-if="settings.og_image" rel="icon" :href="settings.og_image" type="image/png" />
  </Head>

  <div class="min-h-screen bg-white text-slate-900 font-sans">

    <!-- Nav -->
    <nav class="fixed top-0 inset-x-0 z-50 bg-white/90 backdrop-blur border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="#" class="font-semibold text-slate-800 tracking-tight flex items-center gap-2">
          <span class="text-xs font-bold bg-orange-500 text-white px-1.5 py-0.5 rounded uppercase tracking-wider">{{ initials }}</span>
          {{ settings.site_name || owner.name }}
        </a>
        <div class="hidden md:flex items-center gap-6">
          <a v-for="link in navLinks" :key="link.href" :href="link.href"
            class="text-sm text-slate-600 hover:text-orange-500 transition-colors font-medium">
            {{ link.label }}
          </a>
        </div>
        <div class="flex items-center gap-3">
          <template v-if="isLoggedIn">
            <a v-if="isAdmin" href="/admin/dashboard"
              class="text-sm px-4 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-700 transition-colors">
              Dashboard →
            </a>
          </template>
          <template v-else>
            <a href="/login" class="text-sm px-4 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
              Login
            </a>
            <a href="/register" class="text-sm px-4 py-1.5 rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition-colors">
              Sign up
            </a>
          </template>
        </div>
      </div>
    </nav>

    <!-- Page content -->
    <main class="pt-14">

      <!-- Donate banner (opt-in via Settings) -->
      <div v-if="settings.show_donate_banner" class="bg-gradient-to-r from-pink-50 to-rose-50 border-b border-pink-100 px-6 py-4 text-center">
        <p class="text-sm text-slate-700">
          Enjoying my work?
          <a href="/donate" class="font-medium text-pink-600 hover:text-pink-800 underline ml-1">Support me with a donation ♥</a>
        </p>
      </div>

      <template v-if="page" v-for="block in (page.blocks ?? [])" :key="block.order">

        <!-- Hero -->
        <section v-if="block.type === 'hero'" id="home"
          class="min-h-[90vh] flex items-center px-6 max-w-6xl mx-auto py-20">
          <div class="flex-1">
            <p class="text-orange-500 font-semibold text-sm uppercase tracking-widest mb-3">Hello, I'm</p>
            <h1 class="text-5xl md:text-6xl font-extrabold leading-tight text-slate-900 mb-4">
              {{ block.data.heading?.replace(/^Hi, I'm /, '') || owner.name }}
            </h1>
            <p class="text-xl text-slate-500 mb-8">{{ block.data.subheading }}</p>
            <div v-if="block.data.rotating_text?.length" class="text-orange-500 font-semibold mb-8 h-6 overflow-hidden">
              <span>{{ currentRotatingText }}</span>
            </div>
            <div class="flex gap-4">
              <a href="#projects"
                class="px-6 py-3 bg-orange-500 text-white rounded-lg font-semibold hover:bg-orange-600 transition-colors text-sm">
                View Projects
              </a>
              <a href="#contact"
                class="px-6 py-3 border border-slate-300 text-slate-700 rounded-lg font-semibold hover:border-orange-400 hover:text-orange-500 transition-colors text-sm">
                Contact
              </a>
            </div>
          </div>
          <div v-if="block.data.image" class="flex-1 hidden md:flex justify-center">
            <img :src="block.data.image" :alt="owner.name" class="w-80 h-80 object-cover rounded-full shadow-2xl" />
          </div>
          <div v-else class="flex-1 hidden md:flex justify-center">
            <div class="w-72 h-72 rounded-2xl bg-gradient-to-br from-orange-100 to-orange-50 flex items-center justify-center text-8xl select-none">
              👨‍💻
            </div>
          </div>
        </section>

        <!-- About -->
        <section v-else-if="block.type === 'about'" id="about"
          class="py-20 px-6 max-w-6xl mx-auto">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-start">
            <div>
              <h2 class="text-3xl font-bold text-slate-900 mb-6">About</h2>
              <div class="space-y-4 text-slate-600 leading-relaxed">
                <p v-for="(para, i) in aboutParagraphs(block.data.bio)" :key="i">{{ para }}</p>
              </div>
            </div>
            <div>
              <h3 class="text-orange-500 font-bold text-lg mb-4">Skills Overview</h3>
              <ul class="space-y-2">
                <li v-for="item in (block.data.overview ?? [])" :key="item"
                  class="text-slate-600">{{ item }}</li>
              </ul>
            </div>
          </div>
        </section>

        <!-- Skills grid -->
        <section v-else-if="block.type === 'skills'" id="skills"
          class="py-20 px-6 max-w-6xl mx-auto bg-slate-50 rounded-3xl">
          <h2 class="text-3xl font-bold text-slate-900 mb-10">{{ block.data.heading ?? 'Skills' }}</h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            <div v-for="skill in (block.data.skills ?? [])" :key="skill.name"
              class="bg-white rounded-2xl p-6 flex flex-col items-center gap-2 shadow-sm hover:shadow-md transition-shadow">
              <span class="text-3xl">{{ skill.icon }}</span>
              <span class="text-sm font-medium text-slate-700">{{ skill.name }}</span>
            </div>
          </div>
        </section>

        <!-- Experience timeline -->
        <section v-else-if="block.type === 'experience'" id="experience"
          class="py-20 px-6 max-w-6xl mx-auto">
          <h2 class="text-3xl font-bold text-slate-900 mb-10">{{ block.data.heading ?? 'Experience' }}</h2>
          <div class="space-y-8">
            <div v-for="exp in (block.data.items ?? [])" :key="exp.title"
              class="flex gap-6">
              <div class="w-1 bg-orange-400 rounded-full flex-shrink-0"></div>
              <div>
                <p class="text-orange-500 font-semibold text-sm mb-1">{{ exp.period }}</p>
                <h3 class="font-bold text-slate-900 text-lg">{{ exp.title }}</h3>
                <p v-if="exp.company" class="text-slate-500 text-sm mb-1">{{ exp.company }}</p>
                <p class="text-slate-600 text-sm">{{ exp.description }}</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Text -->
        <section v-else-if="block.type === 'text'" class="py-16 px-6 max-w-3xl mx-auto prose prose-slate">
          <p class="whitespace-pre-line">{{ block.data.content }}</p>
        </section>

        <!-- Text + Image -->
        <section v-else-if="block.type === 'text_image'" class="py-16 px-6 max-w-5xl mx-auto flex flex-col md:flex-row gap-12 items-center">
          <div class="flex-1 prose prose-slate">
            <p class="whitespace-pre-line">{{ block.data.text }}</p>
          </div>
          <div v-if="block.data.image" class="flex-1">
            <img :src="block.data.image" :alt="block.data.alt ?? ''" class="rounded-2xl shadow-md w-full object-cover" />
          </div>
        </section>

        <!-- Service Cards -->
        <section v-else-if="block.type === 'service_cards'" class="py-20 px-6 max-w-6xl mx-auto">
          <div v-if="block.data.heading" class="text-center mb-10">
            <h2 class="text-3xl font-bold text-slate-900">{{ block.data.heading }}</h2>
          </div>
          <div v-if="page.service_cards?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
              v-for="card in page.service_cards"
              :key="card.id"
              class="rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition-shadow"
            >
              <div v-if="card.icon" class="text-3xl mb-3">{{ card.icon }}</div>
              <h3 class="font-semibold text-slate-900 mb-2">{{ card.title }}</h3>
              <p class="text-sm text-slate-500">{{ card.description }}</p>
            </div>
          </div>
        </section>

        <!-- Project Grid -->
        <section v-else-if="block.type === 'project_grid'" id="projects" class="py-20 px-6 max-w-6xl mx-auto">
          <div v-if="block.data.heading" class="mb-10">
            <h2 class="text-3xl font-bold text-slate-900">{{ block.data.heading }}</h2>
          </div>
          <!-- DB workspace products (when workspace linked) -->
          <div v-if="block.data.workspace_id && workspaces[block.data.workspace_id]?.projects?.length"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="project in workspaces[block.data.workspace_id].projects"
              :key="project.id"
              class="rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition-shadow flex flex-col">
              <h3 class="font-bold text-slate-900 text-lg mb-2">{{ project.name }}</h3>
              <p class="text-sm text-slate-500 flex-1 mb-4">{{ project.description }}</p>
              <p v-if="project.tags?.length" class="text-xs text-slate-400 mb-4">Tech: {{ project.tags.join(', ') }}</p>
              <div class="flex gap-2 mt-auto">
                <a v-if="project.github_repo"
                  :href="`https://github.com/${project.github_repo}`"
                  target="_blank" rel="noopener"
                  class="px-4 py-1.5 border border-slate-300 text-slate-700 rounded-lg text-xs font-medium hover:border-orange-400 hover:text-orange-500 transition-colors">
                  GitHub
                </a>
                <a :href="project.url ?? '#'"
                  class="px-4 py-1.5 bg-orange-500 text-white rounded-lg text-xs font-medium hover:bg-orange-600 transition-colors">
                  View Details
                </a>
              </div>
            </div>
          </div>
          <!-- Inline JSON fallback -->
          <div v-else-if="block.data.projects?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="project in block.data.projects"
              :key="project.id ?? project.title"
              class="rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition-shadow flex flex-col">
              <h3 class="font-bold text-slate-900 text-lg mb-2">{{ project.title }}</h3>
              <p class="text-sm text-slate-500 flex-1 mb-4">{{ project.description }}</p>
              <p v-if="project.tech" class="text-xs text-slate-400 mb-4">Tech: {{ project.tech }}</p>
              <div class="flex gap-2 mt-auto">
                <a v-if="project.github" :href="project.github" target="_blank" rel="noopener"
                  class="px-4 py-1.5 border border-slate-300 text-slate-700 rounded-lg text-xs font-medium hover:border-orange-400 hover:text-orange-500 transition-colors">
                  GitHub
                </a>
                <a :href="project.url ?? '#'"
                  class="px-4 py-1.5 bg-orange-500 text-white rounded-lg text-xs font-medium hover:bg-orange-600 transition-colors">
                  View Details
                </a>
              </div>
            </div>
          </div>
        </section>

        <!-- Contact Form -->
        <section v-else-if="block.type === 'contact_form'" id="contact" class="py-20 px-6 max-w-5xl mx-auto">
          <h2 class="text-3xl font-bold text-slate-900 mb-10">{{ block.data.heading ?? 'Contact' }}</h2>

          <!-- Success flash -->
          <div v-if="$page.props.flash?.contact_success"
            class="mb-6 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
            {{ $page.props.flash.contact_success }}
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-start">
            <!-- Left: contact info + donate -->
            <div class="space-y-6">
              <div v-if="block.data.email" class="flex items-center gap-3 text-sm text-slate-600">
                <span class="text-orange-500 text-lg">✉️</span>
                <a :href="`mailto:${block.data.email}`" class="hover:text-orange-500 transition-colors break-all">
                  {{ block.data.email }}
                </a>
              </div>
              <div v-if="block.data.phone" class="flex items-center gap-3 text-sm text-slate-600">
                <span class="text-orange-500 text-lg">📞</span>
                <a :href="`tel:${block.data.phone}`" class="hover:text-orange-500 transition-colors">{{ block.data.phone }}</a>
              </div>
              <div v-for="link in (block.data.links ?? [])" :key="link.label"
                class="flex items-center gap-3 text-sm text-slate-600">
                <span class="text-orange-500 text-lg">{{ link.icon ?? '🔗' }}</span>
                <a :href="link.url" target="_blank" rel="noopener" class="hover:text-orange-500 transition-colors">
                  {{ link.label }}
                </a>
              </div>

              <!-- Donate card -->
              <div class="mt-8 bg-gradient-to-br from-orange-50 to-amber-50 border border-orange-200 rounded-2xl p-6">
                <p class="font-semibold text-slate-800 mb-1">Support my work</p>
                <p class="text-sm text-slate-500 mb-4">If you find my projects helpful, consider buying me a coffee.</p>
                <a href="/donate"
                  class="inline-flex items-center gap-2 px-5 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 transition-colors">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                  </svg>
                  Donate ♥
                </a>
              </div>
            </div>

            <!-- Right: form -->
            <form @submit.prevent="submitContact(block)" class="space-y-4">
              <div>
                <input v-model="contactForm.name" type="text" placeholder="Your name"
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-orange-400 transition"
                  :class="contactForm.errors.name ? 'border-red-300' : ''" />
                <p v-if="contactForm.errors.name" class="text-xs text-red-500 mt-1">{{ contactForm.errors.name }}</p>
              </div>
              <div>
                <input v-model="contactForm.email" type="email" placeholder="Your email"
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-orange-400 transition"
                  :class="contactForm.errors.email ? 'border-red-300' : ''" />
                <p v-if="contactForm.errors.email" class="text-xs text-red-500 mt-1">{{ contactForm.errors.email }}</p>
              </div>
              <div>
                <textarea v-model="contactForm.message" rows="5" placeholder="Your message"
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-orange-400 transition resize-none"
                  :class="contactForm.errors.message ? 'border-red-300' : ''" />
                <p v-if="contactForm.errors.message" class="text-xs text-red-500 mt-1">{{ contactForm.errors.message }}</p>
              </div>
              <button type="submit" :disabled="contactForm.processing"
                class="w-full bg-orange-500 text-white py-3 rounded-xl text-sm font-semibold hover:bg-orange-600 disabled:opacity-50 transition-colors">
                {{ contactForm.processing ? 'Sending…' : 'Send message' }}
              </button>
            </form>
          </div>
        </section>

      </template>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-100 py-8 text-center text-xs text-slate-400 space-y-2">
      <p>{{ settings.site_name || owner.name }}</p>
      <a href="/donate" class="inline-flex items-center gap-1 text-pink-400 hover:text-pink-600 transition-colors">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
        </svg>
        Support my work
      </a>
    </footer>
  </div>
</template>

<script setup>
import { usePage, Head, useForm } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  page:       { type: Object, default: () => ({}) },
  owner:      { type: Object, default: () => ({}) },
  settings:   { type: Object, default: () => ({}) },
  workspaces: { type: Object, default: () => ({}) },
  auth:       { type: Object, default: () => ({}) },
})

const { props: pageProps } = usePage()

const isLoggedIn = computed(() => !!pageProps.auth?.user)
const isAdmin    = computed(() => pageProps.auth?.roles?.includes('admin') ?? false)

const initials = computed(() => {
  const name = props.settings.site_name || props.owner.name || ''
  return name.split(' ').map(w => w[0]).join('').slice(0, 4).toUpperCase()
})

const blockTypes = computed(() => new Set((props.page.blocks ?? []).map(b => b.type)))

const navLinks = computed(() => {
  const links = []
  if (blockTypes.value.has('hero'))       links.push({ label: 'Home',       href: '#home' })
  if (blockTypes.value.has('about'))      links.push({ label: 'About',      href: '#about' })
  if (blockTypes.value.has('skills'))     links.push({ label: 'Skills',     href: '#skills' })
  if (blockTypes.value.has('project_grid')) links.push({ label: 'Projects', href: '#projects' })
  if (blockTypes.value.has('experience')) links.push({ label: 'Experience', href: '#experience' })
  if (blockTypes.value.has('contact_form')) links.push({ label: 'Contact',  href: '#contact' })
  return links
})

// Rotating text animation
const rotatingBlock = computed(() => (props.page.blocks ?? []).find(b => b.type === 'hero'))
const rotatingTexts = computed(() => rotatingBlock.value?.data?.rotating_text ?? [])
const rotatingIndex  = ref(0)
const currentRotatingText = computed(() => rotatingTexts.value[rotatingIndex.value] ?? '')
let rotatingTimer = null
onMounted(() => {
  if (rotatingTexts.value.length > 1) {
    rotatingTimer = setInterval(() => {
      rotatingIndex.value = (rotatingIndex.value + 1) % rotatingTexts.value.length
    }, 2500)
  }
})
onUnmounted(() => clearInterval(rotatingTimer))

const aboutParagraphs = (bio) => (bio ?? '').split('\n').filter(p => p.trim())

const contactForm = useForm({ name: '', email: '', message: '', user_id: '', page_slug: '' })
const submitContact = (block) => {
  contactForm.user_id   = props.owner.id ?? ''
  contactForm.page_slug = props.page?.slug ?? ''
  contactForm.post('/contact', {
    preserveScroll: true,
    onSuccess: () => contactForm.reset(),
  })
}

const pageTitle = computed(() => {
  const site = props.settings.site_name || props.owner.name
  return props.page.is_home ? site : `${props.page.name} — ${site}`
})

const pageDescription = computed(() => {
  const heroBlock = (props.page.blocks ?? []).find(b => b.type === 'hero')
  return heroBlock?.data?.subheading || `${props.owner.name}'s portfolio`
})
</script>

<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">

      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Content</h1>
        <span v-if="isDemo" class="text-xs px-3 py-1 rounded-full bg-amber-100 text-amber-700 font-medium">
          Demo — seeded content is locked
        </span>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="mb-5 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.flash.success }}
      </div>

      <!-- Tabs -->
      <div class="flex border-b border-slate-200 mb-8 gap-1">
        <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
          class="px-5 py-2.5 text-sm font-medium rounded-t-lg transition-colors"
          :class="activeTab === tab.id
            ? 'bg-white border border-b-white border-slate-200 text-slate-900 -mb-px'
            : 'text-slate-500 hover:text-slate-700'">
          {{ tab.label }}
        </button>
      </div>

      <!-- ═══════════════════ SKILLS TAB ═══════════════════ -->
      <div v-if="activeTab === 'skills'">

        <!-- Template picker -->
        <div class="mb-6">
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-3">Quick-add from templates</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="t in availableTemplates" :key="t.name"
              @click="quickAddSkill(t)"
              class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-orange-50 hover:border-orange-300 border border-slate-200 rounded-lg text-xs font-medium text-slate-600 hover:text-orange-600 transition-colors">
              <span>{{ t.icon }}</span> {{ t.name }}
            </button>
          </div>
        </div>

        <!-- Add custom skill -->
        <form @submit.prevent="submitSkill" class="flex gap-3 mb-8">
          <div class="w-16">
            <input v-model="skillForm.icon" type="text" placeholder="🔧" maxlength="4"
              class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-center text-lg outline-none focus:ring-2 focus:ring-orange-400" />
          </div>
          <div class="flex-1">
            <input v-model="skillForm.name" type="text" placeholder="Skill name (e.g. TypeScript)"
              class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-orange-400"
              :class="skillForm.errors.name ? 'border-red-300' : ''" />
            <p v-if="skillForm.errors.name" class="text-xs text-red-500 mt-1">{{ skillForm.errors.name }}</p>
          </div>
          <button type="submit" :disabled="skillForm.processing"
            class="px-5 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 disabled:opacity-50 transition-colors whitespace-nowrap">
            + Add skill
          </button>
        </form>

        <!-- Skill cards grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
          <div v-for="skill in skills" :key="skill.id"
            class="group relative bg-white border rounded-2xl p-4 text-center shadow-sm transition-all"
            :class="isLockedSkill(skill) ? 'opacity-60 border-slate-100' : 'border-slate-200 hover:shadow-md'">

            <!-- Seeded badge -->
            <span v-if="skill.is_seeded"
              class="absolute top-2 right-2 text-[9px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-400 font-medium uppercase tracking-wide">
              Seeded
            </span>

            <div v-if="editingSkill?.id === skill.id" class="space-y-2">
              <input v-model="editSkillForm.icon" type="text" maxlength="4"
                class="w-12 border border-slate-200 rounded-lg px-2 py-1 text-center text-lg outline-none mx-auto block" />
              <input v-model="editSkillForm.name" type="text"
                class="w-full border border-slate-200 rounded-lg px-2 py-1 text-xs outline-none" />
              <p v-if="editSkillForm.errors.name" class="text-xs text-red-500">{{ editSkillForm.errors.name }}</p>
              <div class="flex gap-1 justify-center">
                <button @click="saveSkill(skill)" class="px-2 py-1 bg-green-500 text-white rounded-lg text-xs hover:bg-green-600">Save</button>
                <button @click="editingSkill = null" class="px-2 py-1 bg-slate-200 text-slate-600 rounded-lg text-xs hover:bg-slate-300">Cancel</button>
              </div>
            </div>
            <div v-else>
              <div class="text-3xl mb-2">{{ skill.icon }}</div>
              <p class="text-xs font-medium text-slate-700 truncate">{{ skill.name }}</p>
              <div v-if="!isLockedSkill(skill)"
                class="mt-3 flex gap-1 justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                <button @click="startEditSkill(skill)"
                  class="px-2 py-1 text-xs bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200">Edit</button>
                <button @click="deleteSkill(skill)"
                  class="px-2 py-1 text-xs bg-red-50 text-red-500 rounded-lg hover:bg-red-100">✕</button>
              </div>
            </div>
          </div>

          <!-- Empty -->
          <div v-if="!skills.length" class="col-span-4 text-center py-12 text-slate-400 text-sm">
            No skills yet. Add from templates above or type a custom one.
          </div>
        </div>
      </div>

      <!-- ═══════════════════ ABOUT TAB ═══════════════════ -->
      <div v-if="activeTab === 'about'">
        <div class="mb-6 flex items-center justify-between">
          <p class="text-sm text-slate-500">Your bio and skills overview shown on the public portfolio.</p>
          <span v-if="about?.is_seeded && isDemo"
            class="text-xs px-2 py-1 rounded-full bg-amber-100 text-amber-700 font-medium flex items-center gap-1">
            🔒 Seeded — read only in demo
          </span>
        </div>

        <!-- Templates -->
        <div class="mb-6">
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Bio templates</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="t in bioTemplates" :key="t.label" @click="applyBioTemplate(t)"
              :disabled="isLockedAbout"
              class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs text-slate-600 hover:border-orange-300 hover:text-orange-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
              {{ t.label }}
            </button>
          </div>
        </div>

        <form @submit.prevent="submitAbout" class="space-y-6" :class="isLockedAbout ? 'opacity-60 pointer-events-none' : ''">
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Bio</label>
            <textarea v-model="aboutForm.bio" rows="6" placeholder="Write a short bio about yourself…"
              class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-orange-400 resize-none" />
          </div>

          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-xs font-medium text-slate-500 uppercase tracking-wide">Skills overview bullets</label>
              <button type="button" @click="addOverviewBullet"
                class="text-xs text-orange-500 hover:text-orange-700 font-medium">+ Add bullet</button>
            </div>
            <div class="space-y-2">
              <div v-for="(item, i) in aboutForm.overview" :key="i" class="flex gap-2">
                <input v-model="aboutForm.overview[i]" type="text" :placeholder="`Bullet ${i + 1}`"
                  class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-orange-400" />
                <button type="button" @click="removeOverviewBullet(i)"
                  class="px-3 py-2 text-red-400 hover:text-red-600 text-sm">✕</button>
              </div>
              <button v-if="!aboutForm.overview.length" type="button" @click="addOverviewBullet"
                class="w-full border border-dashed border-slate-300 rounded-xl py-3 text-sm text-slate-400 hover:border-orange-300 hover:text-orange-400 transition-colors">
                + Add first bullet
              </button>
            </div>
          </div>

          <button type="submit" :disabled="aboutForm.processing"
            class="px-6 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 disabled:opacity-50 transition-colors">
            {{ aboutForm.processing ? 'Saving…' : 'Save About' }}
          </button>
        </form>
      </div>

      <!-- ═══════════════════ EXPERIENCE TAB ═══════════════════ -->
      <div v-if="activeTab === 'experience'">

        <!-- Templates -->
        <div class="mb-6">
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Role templates</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="t in expTemplates" :key="t.title" @click="applyExpTemplate(t)"
              class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs text-slate-600 hover:border-orange-300 hover:text-orange-600 transition-colors">
              {{ t.title }}
            </button>
          </div>
        </div>

        <!-- Add form -->
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 mb-8">
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-4">
            {{ editingExp ? 'Edit entry' : 'Add new entry' }}
          </p>
          <form @submit.prevent="submitExp" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs text-slate-500 mb-1 block">Period</label>
                <input v-model="expForm.period" type="text" placeholder="e.g. 2023 – Present"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-orange-400" />
              </div>
              <div>
                <label class="text-xs text-slate-500 mb-1 block">Company <span class="text-slate-300">(optional)</span></label>
                <input v-model="expForm.company" type="text" placeholder="Company or client name"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-orange-400" />
              </div>
            </div>
            <div>
              <label class="text-xs text-slate-500 mb-1 block">Title / Role</label>
              <input v-model="expForm.title" type="text" placeholder="e.g. Full Stack Developer"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-orange-400"
                :class="expForm.errors.title ? 'border-red-300' : ''" />
              <p v-if="expForm.errors.title" class="text-xs text-red-500 mt-1">{{ expForm.errors.title }}</p>
            </div>
            <div>
              <label class="text-xs text-slate-500 mb-1 block">Description</label>
              <textarea v-model="expForm.description" rows="3" placeholder="Brief description of what you did…"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-orange-400 resize-none" />
            </div>
            <div class="flex gap-3">
              <button type="submit" :disabled="expForm.processing"
                class="px-5 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 disabled:opacity-50 transition-colors">
                {{ expForm.processing ? 'Saving…' : (editingExp ? 'Update' : 'Add entry') }}
              </button>
              <button v-if="editingExp" type="button" @click="cancelEditExp"
                class="px-5 py-2.5 bg-slate-200 text-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-300 transition-colors">
                Cancel
              </button>
            </div>
          </form>
        </div>

        <!-- Experience timeline cards -->
        <div class="space-y-4">
          <div v-for="exp in experiences" :key="exp.id"
            class="flex gap-4 bg-white border rounded-2xl p-5 shadow-sm"
            :class="isLockedExp(exp) ? 'opacity-60 border-slate-100' : 'border-slate-200'">
            <div class="w-1 bg-orange-400 rounded-full flex-shrink-0 self-stretch min-h-12"></div>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-orange-500 font-semibold text-xs mb-0.5">{{ exp.period }}</p>
                  <h3 class="font-bold text-slate-900">{{ exp.title }}</h3>
                  <p v-if="exp.company" class="text-sm text-slate-500">{{ exp.company }}</p>
                  <p v-if="exp.description" class="text-sm text-slate-600 mt-1">{{ exp.description }}</p>
                </div>
                <div class="flex gap-1 flex-shrink-0">
                  <span v-if="exp.is_seeded"
                    class="text-[9px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-400 font-medium uppercase tracking-wide self-start">
                    Seeded
                  </span>
                  <template v-if="!isLockedExp(exp)">
                    <button @click="startEditExp(exp)"
                      class="px-3 py-1.5 text-xs bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200">Edit</button>
                    <button @click="deleteExp(exp)"
                      class="px-3 py-1.5 text-xs bg-red-50 text-red-500 rounded-lg hover:bg-red-100">✕</button>
                  </template>
                </div>
              </div>
            </div>
          </div>

          <div v-if="!experiences.length" class="text-center py-12 text-slate-400 text-sm">
            No experience entries yet. Add one above or pick a template.
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  skills:      { type: Array,  default: () => [] },
  about:       { type: Object, default: null },
  experiences: { type: Array,  default: () => [] },
  isDemo:      { type: Boolean, default: false },
})

const activeTab = ref('skills')
const tabs = [
  { id: 'skills',     label: 'Skills' },
  { id: 'about',      label: 'About' },
  { id: 'experience', label: 'Experience' },
]

// ─── Demo lock helpers ────────────────────────────────────────────────────────
const isLockedSkill = (s)   => props.isDemo && s.is_seeded
const isLockedExp   = (e)   => props.isDemo && e.is_seeded
const isLockedAbout = computed(() => props.isDemo && props.about?.is_seeded)

// ─── Skills ───────────────────────────────────────────────────────────────────
const skillTemplates = [
  { name: 'Laravel',      icon: '🧱' }, { name: 'PHP',          icon: '🐘' },
  { name: 'Vue.js',       icon: '💚' }, { name: 'React',        icon: '⚛️' },
  { name: 'JavaScript',   icon: '🟨' }, { name: 'TypeScript',   icon: '🔷' },
  { name: 'Node.js',      icon: '🟩' }, { name: 'Python',       icon: '🐍' },
  { name: 'HTML5',        icon: '🌐' }, { name: 'CSS3',         icon: '🎨' },
  { name: 'Tailwind CSS', icon: '💨' }, { name: 'MySQL',        icon: '🗄️' },
  { name: 'PostgreSQL',   icon: '🐘' }, { name: 'SQLite',       icon: '💾' },
  { name: 'MongoDB',      icon: '🍃' }, { name: 'Redis',        icon: '🔴' },
  { name: 'REST API',     icon: '🔌' }, { name: 'GraphQL',      icon: '🔷' },
  { name: 'Docker',       icon: '🐳' }, { name: 'Linux',        icon: '🐧' },
  { name: 'AWS',          icon: '☁️' }, { name: 'Git',          icon: '🔀' },
  { name: 'Inertia.js',  icon: '⚡' }, { name: 'Vite',         icon: '⚡' },
  { name: 'Figma',        icon: '🖼️' }, { name: 'Postman',      icon: '📮' },
  { name: 'CI/CD',        icon: '🚀' }, { name: 'Nginx',        icon: '🌿' },
]

const existingNames = computed(() => new Set(props.skills.map(s => s.name.toLowerCase())))
const availableTemplates = computed(() =>
  skillTemplates.filter(t => !existingNames.value.has(t.name.toLowerCase()))
)

const skillForm = useForm({ name: '', icon: '🔧' })
const submitSkill = () => {
  skillForm.post('/admin/content/skills', { preserveScroll: true, onSuccess: () => skillForm.reset() })
}
const quickAddSkill = (t) => {
  const f = useForm({ name: t.name, icon: t.icon })
  f.post('/admin/content/skills', { preserveScroll: true })
}

const editingSkill = ref(null)
const editSkillForm = useForm({ name: '', icon: '' })
const startEditSkill = (skill) => {
  editingSkill.value = skill
  editSkillForm.name = skill.name
  editSkillForm.icon = skill.icon
}
const saveSkill = (skill) => {
  editSkillForm.patch(`/admin/content/skills/${skill.id}`, {
    preserveScroll: true,
    onSuccess: () => { editingSkill.value = null },
  })
}
const deleteSkill = (skill) => {
  if (!confirm(`Remove "${skill.name}"?`)) return
  router.delete(`/admin/content/skills/${skill.id}`, { preserveScroll: true })
}

// ─── About ────────────────────────────────────────────────────────────────────
const aboutForm = useForm({
  bio:      props.about?.bio ?? '',
  overview: props.about?.overview ? [...props.about.overview] : [],
})

const addOverviewBullet    = () => aboutForm.overview.push('')
const removeOverviewBullet = (i) => aboutForm.overview.splice(i, 1)

const submitAbout = () => {
  aboutForm.patch('/admin/content/about', { preserveScroll: true })
}

const bioTemplates = [
  {
    label: 'Full Stack Developer',
    bio: "I'm a full stack developer specializing in Laravel and Vue.js. I focus on clean architecture, maintainable codebases, and API-first development that scales.\n\nMy workflow blends backend reliability with frontend performance, ensuring the product remains fast and secure from MVP to production.",
    overview: ['Backend systems with Laravel and RESTful APIs', 'Responsive Vue-based UIs and component-driven design', 'DevOps workflows with Docker, Linux, and cloud platforms'],
  },
  {
    label: 'Frontend Engineer',
    bio: "I'm a frontend engineer who loves building pixel-perfect, accessible web interfaces with modern JavaScript frameworks.\n\nI care deeply about user experience, performance, and clean component architecture.",
    overview: ['React and Vue component-driven development', 'Performance optimization and Core Web Vitals', 'Accessible, responsive design systems'],
  },
  {
    label: 'Backend Engineer',
    bio: "I'm a backend engineer focused on designing and building robust, scalable APIs and server-side systems.\n\nI thrive on solving complex technical problems and delivering reliable software infrastructure.",
    overview: ['RESTful and GraphQL API design', 'Database architecture and query optimization', 'Cloud infrastructure and CI/CD pipelines'],
  },
]
const applyBioTemplate = (t) => {
  aboutForm.bio = t.bio
  aboutForm.overview = [...t.overview]
}

// ─── Experience ───────────────────────────────────────────────────────────────
const editingExp = ref(null)
const expForm = useForm({ period: '', title: '', company: '', description: '' })

const expTemplates = [
  { period: '2024 – Present', title: 'Full Stack Developer (Freelance)', company: '', description: 'Building web applications and APIs for clients across various industries.' },
  { period: '2022 – 2024',   title: 'Full Stack Developer',             company: '',  description: 'Developed and maintained web applications using Laravel, Vue.js, and MySQL.' },
  { period: '2021 – 2022',   title: 'Junior Developer',                 company: '',  description: 'Learnt fundamentals of web development and contributed to team projects.' },
  { period: '2020 – 2021',   title: 'Intern — Web Development',         company: '',  description: 'Assisted senior developers with frontend tasks and bug fixing.' },
]
const applyExpTemplate = (t) => {
  editingExp.value = null
  expForm.period      = t.period
  expForm.title       = t.title
  expForm.company     = t.company
  expForm.description = t.description
}

const startEditExp = (exp) => {
  editingExp.value    = exp
  expForm.period      = exp.period
  expForm.title       = exp.title
  expForm.company     = exp.company ?? ''
  expForm.description = exp.description ?? ''
}
const cancelEditExp = () => {
  editingExp.value = null
  expForm.reset()
}

const submitExp = () => {
  if (editingExp.value) {
    expForm.patch(`/admin/content/experience/${editingExp.value.id}`, {
      preserveScroll: true,
      onSuccess: () => cancelEditExp(),
    })
  } else {
    expForm.post('/admin/content/experience', {
      preserveScroll: true,
      onSuccess: () => expForm.reset(),
    })
  }
}

const deleteExp = (exp) => {
  if (!confirm(`Remove "${exp.title}"?`)) return
  router.delete(`/admin/content/experience/${exp.id}`, { preserveScroll: true })
}
</script>
